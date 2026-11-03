<?php

/**
 * Rutas del componente LegalCase (auto-descubiertas por el namespace).
 *
 *  · `legalcase`        — el despacho. Grupo `auth`, y CADA ruta con su `permission:`.
 *                         Verbos: view consulta; create da de alta; edit modifica y cambia
 *                         el estado de registros (responder, revisar, conceder acceso);
 *                         approve aprueba/publica/revoca y valida plazos; delete da de baja.
 *                         Además del permiso, cada acción resuelve el asunto por empresa
 *                         Y por visibilidad (`Access`) antes de tocar nada.
 *  · `legalcase-portal` — el cliente. Sin `auth` del núcleo a propósito: su identidad es
 *                         la cuenta del portal (`Libraries/Portal`), que el controlador
 *                         exige en cada método. CSRF sigue activo en todos los POST.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('legalcase', ['namespace' => 'Components\LegalCase\Controllers', 'filter' => 'auth'], static function ($routes) {
    // Mi día y vistas transversales
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:lgc.view']);
    $routes->get('agenda', 'Dashboard::agenda', ['filter' => 'permission:lgc.view']);
    $routes->get('comunicaciones', 'Dashboard::communications', ['filter' => 'permission:lgc.view']);

    // Contactos
    $routes->get('contactos', 'Contacts::index', ['filter' => 'permission:lgc.view']);
    $routes->match(['GET', 'POST'], 'contactos/nuevo', 'Contacts::create', ['filter' => 'permission:lgc.create']);
    $routes->match(['GET', 'POST'], 'contactos/editar/(:num)', 'Contacts::edit/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('contactos/eliminar/(:num)', 'Contacts::delete/$1', ['filter' => 'permission:lgc.delete']);

    // Asuntos
    $routes->get('asuntos', 'Matters::index', ['filter' => 'permission:lgc.view']);
    $routes->match(['GET', 'POST'], 'asuntos/nuevo', 'Matters::create', ['filter' => 'permission:lgc.create']);
    $routes->get('asuntos/ficha/(:num)', 'Matters::show/$1', ['filter' => 'permission:lgc.view']);
    $routes->match(['GET', 'POST'], 'asuntos/editar/(:num)', 'Matters::edit/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/cerrar/(:num)', 'Matters::close/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/reabrir/(:num)', 'Matters::reopen/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/pausa/(:num)', 'Matters::hold/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/eliminar/(:num)', 'Matters::delete/$1', ['filter' => 'permission:lgc.delete']);

    // Equipo, partes y acceso al portal
    $routes->post('asuntos/equipo/(:num)', 'Matters::addTeam/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/equipo/quitar/(:num)/(:num)', 'Matters::removeTeam/$1/$2', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/parte/(:num)', 'Matters::addParticipant/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/parte/quitar/(:num)/(:num)', 'Matters::removeParticipant/$1/$2', ['filter' => 'permission:lgc.edit']);
    // Dar o quitar acceso al portal es decisión del abogado (lgc.share), no del personal de apoyo.
    $routes->post('asuntos/portal/(:num)/(:num)', 'Matters::portalAccess/$1/$2', ['filter' => 'permission:lgc.share']);
    $routes->post('portal/bloquear/(:num)', 'Matters::blockAccount/$1', ['filter' => 'permission:lgc.share']);
    $routes->post('asuntos/parte/poder/(:num)/(:num)', 'Matters::authority/$1/$2', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/bloqueo/(:num)', 'Matters::legalHold/$1', ['filter' => 'permission:lgc.configure']);

    // Procesos, actuaciones, plazos y tareas
    $routes->post('asuntos/proceso/(:num)', 'Work::saveProceeding/$1', ['filter' => 'permission:lgc.create']);
    $routes->post('procesos/editar/(:num)', 'Work::updateProceeding/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('procesos/eliminar/(:num)', 'Work::deleteProceeding/$1', ['filter' => 'permission:lgc.delete']);
    $routes->post('asuntos/actuacion/(:num)', 'Work::saveEvent/$1', ['filter' => 'permission:lgc.create']);
    $routes->post('actuaciones/eliminar/(:num)', 'Work::deleteEvent/$1', ['filter' => 'permission:lgc.delete']);
    $routes->post('asuntos/plazo/(:num)', 'Work::saveDeadline/$1', ['filter' => 'permission:lgc.create']);
    $routes->post('plazos/validar/(:num)', 'Work::validateDeadline/$1', ['filter' => 'permission:lgc.approve']);
    $routes->post('plazos/estado/(:num)', 'Work::deadlineStatus/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('asuntos/tarea/(:num)', 'Work::saveTask/$1', ['filter' => 'permission:lgc.create']);
    $routes->post('tareas/estado/(:num)', 'Work::taskStatus/$1', ['filter' => 'permission:lgc.edit']);

    // Documentos y solicitudes al cliente
    $routes->post('asuntos/documento/(:num)', 'Documents::upload/$1', ['filter' => 'permission:lgc.create']);
    $routes->get('documentos/descargar/(:num)', 'Documents::download/$1', ['filter' => 'permission:lgc.view']);
    $routes->post('documentos/clasificar/(:num)', 'Documents::classify/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('documentos/eliminar/(:num)', 'Documents::delete/$1', ['filter' => 'permission:lgc.delete']);
    $routes->post('asuntos/solicitud/(:num)', 'Documents::request/$1', ['filter' => 'permission:lgc.create']);
    $routes->post('solicitudes/revisar/(:num)', 'Documents::review/$1', ['filter' => 'permission:lgc.edit']);

    // Comunicación: plan y actualizaciones al cliente
    $routes->post('asuntos/plan/(:num)', 'Updates::plan/$1', ['filter' => 'permission:lgc.edit']);
    $routes->match(['GET', 'POST'], 'asuntos/actualizacion/(:num)', 'Updates::create/$1', ['filter' => 'permission:lgc.create']);
    $routes->get('actualizaciones/ver/(:num)', 'Updates::show/$1', ['filter' => 'permission:lgc.view']);
    $routes->match(['GET', 'POST'], 'actualizaciones/editar/(:num)', 'Updates::edit/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('actualizaciones/enviar/(:num)', 'Updates::submit/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('actualizaciones/revisar/(:num)', 'Updates::revise/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('actualizaciones/aprobar/(:num)', 'Updates::approve/$1', ['filter' => 'permission:lgc.approve']);
    $routes->post('actualizaciones/devolver/(:num)', 'Updates::sendBack/$1', ['filter' => 'permission:lgc.approve']);
    $routes->post('actualizaciones/publicar/(:num)', 'Updates::publish/$1', ['filter' => 'permission:lgc.approve']);
    $routes->post('actualizaciones/revocar/(:num)', 'Updates::revoke/$1', ['filter' => 'permission:lgc.approve']);
    $routes->post('actualizaciones/eliminar/(:num)', 'Updates::delete/$1', ['filter' => 'permission:lgc.delete']);

    // Bandeja de mensajes
    $routes->get('mensajes', 'Inbox::index', ['filter' => 'permission:lgc.view']);
    $routes->get('mensajes/hilo/(:num)', 'Inbox::show/$1', ['filter' => 'permission:lgc.view']);
    $routes->post('asuntos/mensaje/(:num)', 'Inbox::start/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('mensajes/responder/(:num)', 'Inbox::reply/$1', ['filter' => 'permission:lgc.edit']);
    $routes->post('mensajes/estado/(:num)', 'Inbox::status/$1', ['filter' => 'permission:lgc.edit']);
    // Aprobar o descartar lo que redactó alguien sin lgc.share (supervisión del personal no abogado).
    $routes->post('mensajes/revisar/(:num)', 'Inbox::review/$1', ['filter' => 'permission:lgc.share']);

    // Configuración
    $routes->match(['GET', 'POST'], 'configuracion', 'Settings::index', ['filter' => 'permission:lgc.configure']);
    $routes->post('configuracion/soporte', 'Settings::grantSupport', ['filter' => 'permission:lgc.configure']);
    $routes->post('configuracion/soporte/revocar/(:num)', 'Settings::revokeSupport/$1', ['filter' => 'permission:lgc.configure']);
    $routes->post('configuracion/reasignar', 'Settings::reassign', ['filter' => 'permission:lgc.configure']);
});

$routes->group('legalcase-portal', ['namespace' => 'Components\LegalCase\Controllers'], static function ($routes) {
    $routes->get('/', 'ClientPortal::index');
    $routes->post('acceso', 'ClientPortal::requestLink');
    $routes->get('entrar/(:segment)', 'ClientPortal::confirm/$1');
    $routes->post('entrar/(:segment)', 'ClientPortal::enter/$1');
    $routes->post('salir', 'ClientPortal::logout');
    $routes->get('asunto/(:num)', 'ClientPortal::matter/$1');
    $routes->post('asunto/(:num)/mensaje', 'ClientPortal::message/$1');
    $routes->post('solicitud/(:num)/entregar', 'ClientPortal::upload/$1');
    $routes->get('documento/(:num)', 'ClientPortal::download/$1');
    $routes->post('preferencias', 'ClientPortal::preferences');
    $routes->post('privacidad', 'ClientPortal::privacy');
    $routes->post('datos', 'ClientPortal::dataRights');
});
