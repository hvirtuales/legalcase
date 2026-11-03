<?php

/**
 * Componente PQRSF — peticiones, quejas, reclamos, sugerencias y felicitaciones
 * que llegan desde **QR publicados en cada área**.
 *
 * El ciudadano radica sin cuenta (puede hacerlo **anónimo**), recibe un radicado
 * y un enlace para seguir su caso; adentro entra a una gestión colaborativa con
 * estados, responsable y hilo. La **IA redacta un borrador** pero **no responde
 * sola**: la respuesta sale cuando una persona la aprueba, y ahí se le avisa al
 * ciudadano por correo, WhatsApp o SMS —solo si autorizó comunicaciones—.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 * Ver plan: proyectos/PLAN_COMPONENTE_PQRSF.md
 */

return [
    'code'          => 'pqr',
    'name'          => 'PQRSF',
    'name_key'      => 'Pqr.compName',
    'version'       => '1.0.0',
    'description'   => 'Solicitudes PQRSF desde QR por área, con gestión colaborativa, borrador de IA y aviso al ciudadano.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-chat',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_pqr_',
    'plan_flag'     => 'pqrsf_enabled',
    'plan_limits'   => [
        ['column' => 'max_requests', 'label' => 'Solicitudes', 'label_key' => 'Pqr.limRequests', 'table' => 'cmp_pqr_requests'],
    ],
    'permissions'   => [
        'pqr.view'      => 'Ver la bandeja de PQRSF y los puntos de publicación',
        'pqr.create'    => 'Crear puntos de publicación',
        'pqr.edit'      => 'Gestionar solicitudes: asignar, cambiar estado, responder y aprobar',
        'pqr.delete'    => 'Eliminar puntos y adjuntos',
        'pqr.configure' => 'Configurar plazos, anonimato, políticas, captcha e IA',
    ],
    'menus'         => [
        ['label' => 'PQRSF', 'label_key' => 'Pqr.navInbox', 'icon' => 'icon-chat', 'route' => 'pqrsf', 'permission' => 'pqr.view', 'order' => 200],
        ['label' => 'Puntos QR', 'label_key' => 'Pqr.navPoints', 'icon' => 'icon-qr-code', 'route' => 'pqrsf/puntos', 'permission' => 'pqr.view', 'order' => 201],
        ['label' => 'Tipos', 'label_key' => 'Pqr.navTypes', 'icon' => 'icon-list-check', 'route' => 'pqrsf/tipos', 'permission' => 'pqr.configure', 'order' => 202],
        ['label' => 'Configuración', 'label_key' => 'Pqr.navSettings', 'icon' => 'icon-gear', 'route' => 'pqrsf/configuracion', 'permission' => 'pqr.configure', 'order' => 203],
    ],
    'routes'        => ['pqrsf'],
    'settings'      => [],
];
