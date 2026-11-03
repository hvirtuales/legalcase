<?php

/**
 * Componente Networking — red de contactos entre usuarios de la plataforma.
 *
 * Cada usuario decide si comparte su perfil; los demás pueden solicitarle conexión
 * explicando el motivo y, una vez aceptada, disponen de un hilo de mensajes interno
 * y accesos directos a correo y WhatsApp.
 *
 * NOTA (excepción consciente al §6 del contrato): la red es GLOBAL — las solicitudes
 * se dan entre usuarios con o sin relación de empresa, así que estas tablas NO llevan
 * `company_id` ni se filtran por la empresa activa. Lo que se expone de cada usuario
 * lo controla él mismo desde «Mi perfil de red».
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'net',
    'name'          => 'Networking',
    'name_key'      => 'Net.compName',
    'version'       => '1.0.0',
    'description'   => 'Red de contactos: descubre usuarios, envía solicitudes de conexión y conversa con tus contactos.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-people', // icono del grupo en el sidebar
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_net_',
    // Columna booleana de `plans` que habilita el componente: si el plan de la
    // empresa activa la tiene en 0, el núcleo oculta el menú y `PlanGuard` bloquea
    // las altas. El cupo va en `plans.max_connections` (0 = ilimitado).
    'plan_flag'     => 'networking_enabled',
    'permissions'   => [
        'net.view'   => 'Ver la red de contactos (descubrir, solicitudes, conexiones)',
        'net.create' => 'Enviar solicitudes de conexión',
        'net.edit'   => 'Responder solicitudes, escribir mensajes y editar el perfil de red',
        'net.delete' => 'Cancelar solicitudes y eliminar conexiones',
    ],
    'menus'         => [
        ['label' => 'Descubrir', 'label_key' => 'Net.navDiscover', 'icon' => 'icon-search', 'route' => 'networking/descubrir', 'permission' => 'net.view', 'order' => 80],
        ['label' => 'Recibidas', 'label_key' => 'Net.navReceived', 'icon' => 'icon-box-arrow-in-right', 'route' => 'networking/recibidas', 'permission' => 'net.view', 'order' => 81],
        ['label' => 'Enviadas', 'label_key' => 'Net.navSent', 'icon' => 'icon-send', 'route' => 'networking/enviadas', 'permission' => 'net.view', 'order' => 82],
        ['label' => 'Mis conexiones', 'label_key' => 'Net.navConnections', 'icon' => 'icon-chat', 'route' => 'networking/conexiones', 'permission' => 'net.view', 'order' => 83],
    ],
    'routes'        => ['networking'],
    'settings'      => [],
];
