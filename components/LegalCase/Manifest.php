<?php

/**
 * Componente LegalCase — gestión de asuntos jurídicos y comunicación con el cliente
 * (LegalCase Connect).
 *
 * Independiente: no lee ni escribe tablas de otros componentes (`depends` vacío).
 * Todo su estado vive en `cmp_lgc_*` y se relaciona con el núcleo solo por FK a
 * `companies(id)` y `users(id)`.
 *
 * Dos caras:
 *  · `/legalcase`        — el despacho: contactos, asuntos, procesos, actuaciones,
 *                          plazos, tareas, documentos, planes de comunicación,
 *                          actualizaciones con aprobación y bandeja de mensajes.
 *  · `/legalcase-portal` — el cliente: un portal privado con cuentas PROPIAS del
 *                          componente (no son `users` del núcleo), acceso por enlace
 *                          de un solo uso, y solo ve lo que el despacho publicó.
 *
 * Especificación de producto: documento maestro LegalCase v1.0 (§6 LegalCase Connect).
 * Contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'lgc',
    'name'          => 'LegalCase',
    'name_key'      => 'Lgc.compName',
    'version'       => '1.0.0',
    'description'   => 'Asuntos jurídicos, procesos, plazos, documentos y portal del cliente con comunicaciones aprobadas.',
    'author'        => 'Herramientas Virtuales',
    'icon'          => 'icon-briefcase',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_lgc_',
    'scope'         => 'company',

    'plan_flag'     => 'lgc_enabled',
    'plan_limits'   => [
        ['column' => 'lgc_max_matters', 'label' => 'Asuntos jurídicos', 'label_key' => 'Lgc.limMatters', 'table' => 'cmp_lgc_matters'],
        ['column' => 'lgc_max_portal', 'label' => 'Cuentas del portal', 'label_key' => 'Lgc.limPortal', 'table' => 'cmp_lgc_portal_accounts'],
    ],

    'permissions'   => [
        'lgc.view'      => 'Ver asuntos, contactos, agenda, documentos y bandeja de LegalCase',
        'lgc.create'    => 'Registrar contactos, asuntos, procesos, actuaciones, plazos, tareas, documentos y borradores',
        'lgc.edit'      => 'Editar asuntos y sus registros, responder mensajes, revisar documentos y gestionar el acceso al portal',
        'lgc.delete'    => 'Eliminar contactos, asuntos y sus registros',
        'lgc.approve'   => 'Aprobar, publicar y revocar actualizaciones al cliente y validar plazos',
        'lgc.configure' => 'Configurar numeración, políticas de aprobación, plazos y portal',
    ],

    'menus'         => [
        ['label' => 'Mi día', 'label_key' => 'Lgc.navHome', 'icon' => 'icon-speedometer2', 'route' => 'legalcase', 'permission' => 'lgc.view', 'order' => 10],
        ['label' => 'Asuntos', 'label_key' => 'Lgc.navMatters', 'icon' => 'icon-briefcase', 'route' => 'legalcase/asuntos', 'permission' => 'lgc.view', 'order' => 20],
        ['label' => 'Contactos', 'label_key' => 'Lgc.navContacts', 'icon' => 'icon-people', 'route' => 'legalcase/contactos', 'permission' => 'lgc.view', 'order' => 30],
        ['label' => 'Agenda', 'label_key' => 'Lgc.navAgenda', 'icon' => 'icon-clock', 'route' => 'legalcase/agenda', 'permission' => 'lgc.view', 'order' => 40],
        ['label' => 'Comunicaciones', 'label_key' => 'Lgc.navComms', 'icon' => 'icon-send', 'route' => 'legalcase/comunicaciones', 'permission' => 'lgc.view', 'order' => 50],
        ['label' => 'Mensajes', 'label_key' => 'Lgc.navInbox', 'icon' => 'icon-chat', 'route' => 'legalcase/mensajes', 'permission' => 'lgc.view', 'order' => 60],
        ['label' => 'Configuración', 'label_key' => 'Lgc.navSettings', 'icon' => 'icon-gear', 'route' => 'legalcase/configuracion', 'permission' => 'lgc.configure', 'order' => 90],
    ],

    'routes'        => ['legalcase', 'legalcase-portal'],
    'settings'      => [],
];
