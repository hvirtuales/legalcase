<?php

/**
 * Componente LeadFlow Legal — captación y conversión de prospectos para firmas
 * jurídicas, integrado con Docketwise.
 *
 * Requerimientos: proyectos/insumos/leadflow_legal_docketwise.md. Esta versión
 * entrega el MVP del §32 siguiendo el flujo del §38:
 *
 *   Formulario → Lead → Pipeline → Precalificación → Actividad → Consulta
 *   → Ganado → Convertir → Contact + Matter en Docketwise
 *
 * Decisiones que explican el diseño:
 *
 * a) **El «tenant» del documento es la EMPRESA de la plataforma.** Cada firma es
 *    una empresa: todas las tablas llevan `company_id` y se filtran por
 *    `session('active_company_id')`. La única tabla global es
 *    `cmp_lfl_providers` (la app OAuth de la plataforma), a propósito.
 *
 * b) **Los roles del documento son una «función comercial» por empresa**
 *    (`cmp_lfl_members`), no roles del núcleo: los roles del núcleo son globales,
 *    y «un asesor solo ve sus leads» es una regla de visibilidad por firma, no un
 *    permiso. Los permisos `lfl.*` siguen decidiendo QUÉ se puede hacer; la
 *    función decide SOBRE QUÉ leads.
 *
 * c) **Docketwise va detrás de un adaptador** (`Libraries/Integrations`). Hoy
 *    funciona el **simulado** (sin acceso a la API real todavía); el adaptador
 *    HTTP sigue la documentación oficial v1 y se activa cambiando el modo en la
 *    configuración de plataforma. La conversión es **idempotente**: cada paso
 *    guarda su id externo antes del siguiente, y un reintento continúa donde
 *    se quedó en vez de duplicar.
 *
 * Portabilidad (contrato §15): no depende de ningún otro componente ni de código
 * añadido al núcleo — `QrService` y `Captcha` son copias propias.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'lfl',
    'name'          => 'LeadFlow Legal',
    'name_key'      => 'Lfl.compName',
    'version'       => '2.3.0',
    'description'   => 'Captación, calificación, seguimiento y conversión de prospectos para firmas jurídicas, con conversión a Contact + Matter en Docketwise.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-briefcase',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_lfl_',
    'scope'         => 'company',

    // Bandera y cupo en `plans`, que añade la migración propia
    // 2026-09-11-100006_AddLeadFlowLimitsToPlans (contrato §11).
    'plan_flag'     => 'leadflow_enabled',
    /*
     * El `hint_key` lo pinta el formulario de planes como tooltip junto a cada
     * casilla. Está aquí y no en la vista del núcleo porque quien sabe qué hace
     * un servicio es el componente que lo presta; si la instalación de destino
     * es anterior y no lee `hint_key`, simplemente no muestra la ayuda.
     */
    'plan_limits'   => [
        ['column' => 'max_leads', 'label' => 'Leads', 'label_key' => 'Lfl.limLeads', 'table' => 'cmp_lfl_leads',
            'hint_key' => 'Lfl.planHintLeads'],
        ['column' => 'max_profiles', 'label' => 'Perfiles publicados', 'label_key' => 'Lfl.limProfiles', 'table' => 'cmp_lfl_profiles',
            'hint_key' => 'Lfl.planHintProfiles'],
        ['column' => 'max_opportunities', 'label' => 'Oportunidades al mes', 'label_key' => 'Lfl.limOpportunities', 'table' => 'cmp_lfl_opportunity_claims',
            'hint_key' => 'Lfl.planHintOpportunities'],
    ],
    'plan_fields'   => [
        ['column' => 'lfl_directory_enabled', 'label' => 'Aparecer en el directorio público', 'label_key' => 'Lfl.planFieldDirectory', 'type' => 'bool',
            'hint_key' => 'Lfl.planHintDirectory'],
        ['column' => 'lfl_featured', 'label' => 'Perfil destacado en el directorio', 'label_key' => 'Lfl.planFieldFeatured', 'type' => 'bool',
            'hint_key' => 'Lfl.planHintFeatured'],
        // Apagado por defecto: en algunas jurisdicciones los testimonios de clientes
        // están restringidos, y encenderlos por omisión sería decidir por la firma.
        ['column' => 'lfl_reviews_enabled', 'label' => 'Opiniones de clientes verificadas', 'label_key' => 'Lfl.planFieldReviews', 'type' => 'bool',
            'hint_key' => 'Lfl.planHintReviews'],
    ],

    /*
     * La portada del directorio se configura en la Configuración DEL SISTEMA, bajo el
     * logo: es una imagen de la plataforma, no de una firma, y ahí es donde alguien la
     * busca. El núcleo expone una ranura genérica para esto, así que el componente no
     * tiene que tocar `app/Views/settings/`.
     */
    'settings_slots' => [
        'general' => [
            'view'    => 'Components\LeadFlow\Views\settings\cover_field',
            'handler' => 'Components\LeadFlow\Libraries\Directory\CoverService::handleSettings',
        ],
    ],

    'permissions'   => [
        'lfl.view'      => 'Ver leads, pipeline, agenda, actividades y reportes',
        'lfl.create'    => 'Registrar leads, actividades, consultas, notas, comunicaciones y documentos',
        'lfl.edit'      => 'Editar leads, moverlos de etapa, asignarlos y gestionar actividades y consultas',
        'lfl.delete'    => 'Eliminar leads y sus registros asociados',
        'lfl.configure' => 'Configurar servicios, etapas, scoring, formularios, marketing, equipo y la conexión con Docketwise',
        'lfl.convert'   => 'Convertir leads ganados en Contact + Matter de Docketwise',
        'lfl.import'    => 'Importar leads desde CSV',
        // Verificar es una afirmación de LA PLATAFORMA sobre una firma, no de la
        // firma sobre sí misma: por eso es un permiso aparte y no cuelga de
        // `lfl.configure`, que lo tiene el administrador de cualquier firma.
        'lfl.verify'    => 'Verificar perfiles de abogados y firmas del directorio',
    ],

    /*
     * `lfl.verify` NO se le concede a «Admin» al instalar.
     *
     * Verificar es una afirmación de la PLATAFORMA sobre una firma —«comprobamos su
     * licencia en la fuente oficial»—, y «Admin» es un rol global que administra
     * empresas: dárselo por omisión convertiría la insignia en algo que una parte
     * interesada puede ponerse a sí misma. Quien deba verificar recibe el permiso en
     * un rol propio («Verificador»), a mano y a sabiendas.
     */
    'permissions_super_only' => ['lfl.verify'],

    'menus'         => [
        ['label' => 'Inicio',        'label_key' => 'Lfl.navHome',      'icon' => 'icon-speedometer2',         'route' => 'leadflow',               'permission' => 'lfl.view',      'order' => 10],
        ['label' => 'Leads',         'label_key' => 'Lfl.navLeads',     'icon' => 'icon-people',               'route' => 'leadflow/leads',         'permission' => 'lfl.view',      'order' => 20],
        ['label' => 'Pipeline',      'label_key' => 'Lfl.navPipeline',  'icon' => 'icon-list-columns-reverse', 'route' => 'leadflow/pipeline',      'permission' => 'lfl.view',      'order' => 30],
        // Entre Pipeline y Consultas: es el paso siguiente del recorrido del lead.
        ['label' => 'Expedientes',   'label_key' => 'Lfl.navMatters',   'icon' => 'icon-folder',               'route' => 'leadflow/expedientes',   'permission' => 'lfl.view',      'order' => 35],
        ['label' => 'Consultas',     'label_key' => 'Lfl.navAppts',     'icon' => 'icon-headset',              'route' => 'leadflow/consultas',     'permission' => 'lfl.view',      'order' => 40],
        ['label' => 'Actividades',   'label_key' => 'Lfl.navActivities', 'icon' => 'icon-list-check',          'route' => 'leadflow/actividades',   'permission' => 'lfl.view',      'order' => 50],
        ['label' => 'Formularios',   'label_key' => 'Lfl.navForms',     'icon' => 'icon-clipboard',            'route' => 'leadflow/formularios',   'permission' => 'lfl.configure', 'order' => 60],
        ['label' => 'Marketing',     'label_key' => 'Lfl.navMarketing', 'icon' => 'icon-signpost',             'route' => 'leadflow/marketing/fuentes', 'permission' => 'lfl.configure', 'order' => 70],
        ['label' => 'Automatizaciones', 'label_key' => 'Lfl.navAutomations', 'icon' => 'icon-robot',            'route' => 'leadflow/automatizaciones', 'permission' => 'lfl.configure', 'order' => 75],
        ['label' => 'Equipo',          'label_key' => 'Lfl.navTeam',      'icon' => 'icon-people',             'route' => 'leadflow/directorio', 'permission' => 'lfl.configure', 'order' => 78],
        ['label' => 'Oportunidades',   'label_key' => 'Lfl.navOpportunities', 'icon' => 'icon-signpost',       'route' => 'leadflow/oportunidades', 'permission' => 'lfl.view', 'order' => 79],
        ['label' => 'Opiniones',       'label_key' => 'Lfl.navReviews', 'icon' => 'icon-star',                 'route' => 'leadflow/opiniones', 'permission' => 'lfl.view', 'order' => 80],
        /*
         * Una sola bandeja de plataforma.
         *
         * Verificar un perfil y leer las denuncias sobre él son el mismo trabajo
         * sobre el mismo objeto; separados, para decidir una verificación había que
         * acordarse de ir a la otra pantalla a mirar el dato que más pesa.
         *
         * Va con `lfl.verify` —Super Admin y el rol «Verificador»—. Dentro, la
         * pestaña de denuncias solo se le enseña al Super Admin: saber que una
         * denuncia existe y leer lo que escribió el ciudadano son cosas distintas.
         */
        ['label' => 'Moderación',    'label_key' => 'Lfl.modTitle',     'icon' => 'icon-shield-check',        'route' => 'leadflow/moderacion',    'permission' => 'lfl.verify',    'order' => 85],
        ['label' => 'Reportes',      'label_key' => 'Lfl.navReports',   'icon' => 'icon-bar-chart',            'route' => 'leadflow/reportes',      'permission' => 'lfl.view',      'order' => 80],
        // Módulo USCIS: la consulta interna y los enlaces de la firma. Junto a
        // Docketwise, que es la otra integración externa.
        ['label' => 'USCIS',        'label_key' => 'Lfl.uscModuleTitle', 'icon' => 'icon-landmark',          'route' => 'leadflow/uscis',     'permission' => 'lfl.view', 'order' => 88],
        ['label' => 'Docketwise',    'label_key' => 'Lfl.navDocketwise', 'icon' => 'icon-plug',                'route' => 'leadflow/docketwise',    'permission' => 'lfl.view',      'order' => 90],
        ['label' => 'Configuración', 'label_key' => 'Lfl.navSettings',  'icon' => 'icon-gear',                 'route' => 'leadflow/configuracion', 'permission' => 'lfl.configure', 'order' => 100],
        // El manual, el último del menú. `lfl.view` y no `lfl.configure`: quien más
        // lo necesita es quien acaba de llegar, que no configura nada.
        ['label' => 'Ayuda',         'label_key' => 'Lfl.navHelp',      'icon' => 'icon-book',                 'route' => 'leadflow/configuracion/ayuda', 'permission' => 'lfl.view', 'order' => 110],
    ],

    'routes'        => ['leadflow', 'leadflow-publico', 'buscar', 'area', 'sitemap.xml', 'abogados', 'abogado', 'firmas', 'firma', 'orientacion', 'registro-legal', 'opinion'],
    'settings'      => [],
];
