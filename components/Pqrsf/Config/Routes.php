<?php

/**
 * Rutas del componente PQRSF (auto-descubiertas). Prefijo `pqrsf`, con los
 * filtros del núcleo (auth + permission). Todo se acota a la empresa activa
 * dentro de los controladores.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

// --- Público: el CIUDADANO radica desde el QR y consulta su ticket. Sin `auth`
// a propósito; el acceso lo gobierna el token (del punto o del ticket) y el
// controlador lo vuelve a validar en cada método. CSRF sigue activo.
$routes->group('pqrsf', ['namespace' => 'Components\Pqrsf\Controllers'], static function ($routes) {
    $routes->get('p/(:segment)', 'PublicPqr::form/$1');
    $routes->post('p/(:segment)', 'PublicPqr::submit/$1');
    $routes->get('captcha/(:segment)', 'PublicPqr::captcha/$1');
    $routes->get('t/(:segment)', 'PublicPqr::ticket/$1');
    // Soporte de la respuesta: el token del ticket es el permiso.
    $routes->get('t/(:segment)/adjunto/(:num)', 'PublicPqr::ticketAttachment/$1/$2');
});

$routes->group('pqrsf', ['namespace' => 'Components\Pqrsf\Controllers', 'filter' => 'auth'], static function ($routes) {
    // Bandeja y gestión
    $routes->get('/', 'Requests::index', ['filter' => 'permission:pqr.view']);
    $routes->get('ver/(:num)', 'Requests::show/$1', ['filter' => 'permission:pqr.view']);
    $routes->post('asignar/(:num)', 'Requests::assign/$1', ['filter' => 'permission:pqr.edit']);
    $routes->post('estado/(:num)', 'Requests::status/$1', ['filter' => 'permission:pqr.edit']);
    $routes->post('prioridad/(:num)', 'Requests::priority/$1', ['filter' => 'permission:pqr.edit']);
    $routes->post('nota/(:num)', 'Requests::note/$1', ['filter' => 'permission:pqr.edit']);
    $routes->post('ia/(:num)', 'Requests::draft/$1', ['filter' => 'permission:pqr.edit']);
    $routes->post('responder/(:num)', 'Requests::answer/$1', ['filter' => 'permission:pqr.edit']);
    $routes->post('eliminar/(:num)', 'Requests::delete/$1', ['filter' => 'permission:pqr.delete']);
    $routes->post('demo', 'Requests::seedDemo', ['filter' => 'permission:pqr.create']);

    // Adjuntos (la descarga comprueba permiso y empresa)
    $routes->post('adjunto/(:num)', 'Requests::attach/$1', ['filter' => 'permission:pqr.edit']);
    $routes->get('adjunto/ver/(:num)', 'Requests::download/$1', ['filter' => 'permission:pqr.view']);
    $routes->post('adjunto/publicar/(:num)', 'Requests::toggleAttachment/$1', ['filter' => 'permission:pqr.edit']);
    $routes->post('adjunto/eliminar/(:num)', 'Requests::deleteAttachment/$1', ['filter' => 'permission:pqr.delete']);

    // Catálogo de tipos (con su plazo de respuesta)
    $routes->get('tipos', 'Types::index', ['filter' => 'permission:pqr.configure']);
    $routes->match(['GET', 'POST'], 'tipos/nuevo', 'Types::create', ['filter' => 'permission:pqr.configure']);
    $routes->match(['GET', 'POST'], 'tipos/editar/(:num)', 'Types::edit/$1', ['filter' => 'permission:pqr.configure']);
    $routes->post('tipos/eliminar/(:num)', 'Types::delete/$1', ['filter' => 'permission:pqr.configure']);
    $routes->post('tipos/restaurar', 'Types::restore', ['filter' => 'permission:pqr.configure']);

    // Puntos de publicación
    $routes->get('puntos', 'Points::index', ['filter' => 'permission:pqr.view']);
    $routes->match(['GET', 'POST'], 'puntos/nuevo', 'Points::create', ['filter' => 'permission:pqr.create']);
    $routes->match(['GET', 'POST'], 'puntos/editar/(:num)', 'Points::edit/$1', ['filter' => 'permission:pqr.edit']);
    $routes->post('puntos/eliminar/(:num)', 'Points::delete/$1', ['filter' => 'permission:pqr.delete']);
    $routes->get('puntos/cartel/(:num)', 'Points::poster/$1', ['filter' => 'permission:pqr.view']);

    // Configuración
    $routes->match(['GET', 'POST'], 'configuracion', 'Settings::index', ['filter' => 'permission:pqr.configure']);
});
