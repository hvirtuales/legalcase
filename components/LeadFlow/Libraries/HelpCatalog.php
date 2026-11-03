<?php

namespace Components\LeadFlow\Libraries;

/**
 * Catálogo de funcionalidades del componente: **la única definición**.
 *
 * El manual tenía una guía ordenada por «cómo se usa esto», que sirve para el primer
 * día pero no responde la otra pregunta: *¿qué sabe hacer LeadFlow exactamente?*. Esto
 * es el reverso: una por una, qué hace, dónde está, qué permiso pide y si depende del
 * plan.
 *
 * Está aquí y no incrustado en la vista por el mismo criterio que `AutomationRegistry`,
 * `FieldRegistry` y `BlockRegistry`: **de un solo sitio beben todos**. Añadir una
 * funcionalidad es una entrada aquí y dos claves de idioma, no tocar la plantilla. Y
 * una entrada sin su clave `fn…D` se nota al instante, porque la ficha sale sin texto.
 *
 * Cada entrada declara:
 *  - `key`   → las claves de idioma `Lfl.fn<Key>` (nombre) y `Lfl.fn<Key>D` (detalle).
 *  - `icon`  → un icono **que exista** en `public/css/icons.css`; uno inventado no da
 *              error, simplemente no pinta nada.
 *  - `route` → la ruta interna a la que llevar, o `null` si no es una pantalla propia
 *              (vive dentro de la ficha del lead, es pública, o es un comando).
 *  - `perm`  → el permiso que exige, o `null` si es pública / de línea de comandos.
 *  - `plan`  → el servicio del plan del que depende: `directory`, `reviews` u
 *              `opportunities`. `null` = siempre disponible.
 *  - `where` → una pista de ubicación cuando no hay ruta (clave de idioma).
 */
final class HelpCatalog
{
    /**
     * Los seis bloques, en el orden en que un lead los recorre: entra, se le hace
     * seguimiento, se prioriza, la firma se deja ver, se convierte, y por debajo está
     * lo que hay que haber configurado.
     */
    public const GROUPS = [
        'capture' => 'icon-funnel',
        'track'   => 'icon-list-check',
        'smart'   => 'icon-robot',
        'public'  => 'icon-globe',
        'convert' => 'icon-plug',
        'config'  => 'icon-gear',
    ];

    /** @return array<string,array<int,array<string,mixed>>> funcionalidades por bloque */
    public static function items(): array
    {
        return [
            'capture' => [
                ['key' => 'Leads', 'icon' => 'icon-people', 'route' => 'leadflow/leads', 'perm' => 'lfl.view'],
                ['key' => 'Import', 'icon' => 'icon-upload', 'route' => 'leadflow/leads/importar', 'perm' => 'lfl.import'],
                ['key' => 'Forms', 'icon' => 'icon-clipboard', 'route' => 'leadflow/formularios', 'perm' => 'lfl.configure'],
                ['key' => 'PublicForm', 'icon' => 'icon-send', 'route' => null, 'perm' => null, 'where' => 'fnWherePublicForm'],
                ['key' => 'Sources', 'icon' => 'icon-signpost', 'route' => 'leadflow/marketing/fuentes', 'perm' => 'lfl.configure'],
                ['key' => 'Campaigns', 'icon' => 'icon-bar-chart', 'route' => 'leadflow/marketing/campanas', 'perm' => 'lfl.configure'],
            ],
            'track' => [
                ['key' => 'Pipeline', 'icon' => 'icon-list-columns-reverse', 'route' => 'leadflow/pipeline', 'perm' => 'lfl.view'],
                ['key' => 'Activities', 'icon' => 'icon-list-check', 'route' => 'leadflow/actividades', 'perm' => 'lfl.view'],
                ['key' => 'Appts', 'icon' => 'icon-headset', 'route' => 'leadflow/consultas', 'perm' => 'lfl.view'],
                ['key' => 'Engagement', 'icon' => 'icon-chat', 'route' => null, 'perm' => 'lfl.create', 'where' => 'fnWhereLead'],
                ['key' => 'Documents', 'icon' => 'icon-paperclip', 'route' => null, 'perm' => 'lfl.create', 'where' => 'fnWhereLead'],
                ['key' => 'Timeline', 'icon' => 'icon-history', 'route' => null, 'perm' => 'lfl.view', 'where' => 'fnWhereLead'],
            ],
            'smart' => [
                ['key' => 'Scoring', 'icon' => 'icon-star', 'route' => 'leadflow/configuracion/scoring', 'perm' => 'lfl.configure'],
                ['key' => 'Automations', 'icon' => 'icon-robot', 'route' => 'leadflow/automatizaciones', 'perm' => 'lfl.configure'],
                ['key' => 'Runs', 'icon' => 'icon-clock-history', 'route' => 'leadflow/automatizaciones/ejecuciones', 'perm' => 'lfl.view'],
                ['key' => 'Reports', 'icon' => 'icon-bar-chart', 'route' => 'leadflow/reportes', 'perm' => 'lfl.view'],
                ['key' => 'Tick', 'icon' => 'icon-clock', 'route' => null, 'perm' => null, 'where' => 'fnWhereCli'],
            ],
            'public' => [
                ['key' => 'Verification', 'icon' => 'icon-patch-check', 'route' => 'leadflow/moderacion', 'perm' => 'lfl.verify'],
                ['key' => 'Cover', 'icon' => 'icon-image', 'route' => null, 'perm' => null, 'where' => 'fnWhereCover'],
                ['key' => 'Directory', 'icon' => 'icon-search', 'route' => null, 'perm' => null, 'plan' => 'directory', 'where' => 'fnWhereDirectory'],
                ['key' => 'FirmSite', 'icon' => 'icon-buildings', 'route' => null, 'perm' => null, 'plan' => 'directory', 'where' => 'fnWhereFirm'],
                ['key' => 'Card', 'icon' => 'icon-qr-code', 'route' => null, 'perm' => null, 'plan' => 'directory', 'where' => 'fnWhereCard'],
                ['key' => 'Opportunities', 'icon' => 'icon-shop', 'route' => 'leadflow/oportunidades', 'perm' => 'lfl.view', 'plan' => 'opportunities'],
                ['key' => 'Reviews', 'icon' => 'icon-patch-check', 'route' => 'leadflow/opiniones', 'perm' => 'lfl.view', 'plan' => 'reviews'],
                ['key' => 'Complaints', 'icon' => 'icon-shield-check', 'route' => 'leadflow/moderacion/denuncias', 'perm' => 'lfl.verify'],
                ['key' => 'Signup', 'icon' => 'icon-person-plus', 'route' => null, 'perm' => null, 'where' => 'fnWhereSignup'],
            ],
            'convert' => [
                ['key' => 'Uscis', 'icon' => 'icon-landmark', 'route' => 'leadflow/uscis', 'perm' => 'lfl.view'],
                ['key' => 'Docketwise', 'icon' => 'icon-plug', 'route' => 'leadflow/docketwise', 'perm' => 'lfl.view'],
                ['key' => 'Convert', 'icon' => 'icon-box-arrow-up-right', 'route' => null, 'perm' => 'lfl.convert', 'where' => 'fnWhereLead'],
                ['key' => 'DwPlatform', 'icon' => 'icon-shield-lock', 'route' => 'leadflow/docketwise/plataforma', 'perm' => 'lfl.configure'],
            ],
            'config' => [
                // Equipo y perfiles del directorio son UNA pantalla: se indexan por
                // (empresa, usuario) y separarlos ya había descuadrado los datos.
                ['key' => 'Team', 'icon' => 'icon-people', 'route' => 'leadflow/directorio', 'perm' => 'lfl.configure'],
                ['key' => 'Services', 'icon' => 'icon-briefcase', 'route' => 'leadflow/configuracion/servicios', 'perm' => 'lfl.configure'],
                ['key' => 'Stages', 'icon' => 'icon-list-columns-reverse', 'route' => 'leadflow/configuracion/etapas', 'perm' => 'lfl.configure'],
                ['key' => 'DocTypes', 'icon' => 'icon-file-earmark', 'route' => 'leadflow/configuracion/documentos', 'perm' => 'lfl.configure'],
                ['key' => 'General', 'icon' => 'icon-gear', 'route' => 'leadflow/configuracion', 'perm' => 'lfl.configure'],
                ['key' => 'Plan', 'icon' => 'icon-puzzle', 'route' => null, 'perm' => null, 'where' => 'fnWherePlan'],
            ],
        ];
    }

    /**
     * Qué servicios del plan tiene contratados ESTA firma.
     *
     * El manual dice lo que el componente sabe hacer; esto dice lo que esta firma puede
     * usar hoy. Sin el dato, alguien leería la ficha de las opiniones y no entendería
     * por qué su pantalla aparece vacía.
     *
     * @return array<string,bool>
     */
    public static function planStatus(int $companyId): array
    {
        $guard = new PlanGuard();

        return [
            'directory' => $guard->directoryEnabled($companyId),
            'reviews'   => $guard->reviewsEnabled($companyId),
            /*
             * Las oportunidades NO tienen interruptor propio: cuelgan del directorio, y
             * `max_opportunities` es solo el cupo del mes. Por eso aquí se pregunta por
             * el directorio y no por `opportunityBlockReason()`, que también dice «no»
             * cuando el cupo se agotó: un cupo agotado no es un servicio que no se
             * tenga, se repone el día 1, y etiquetarlo como «no incluido en el plan»
             * mandaría a alguien a pedir una ampliación que no necesita.
             */
            'opportunities' => $guard->directoryEnabled($companyId),
        ];
    }
}
