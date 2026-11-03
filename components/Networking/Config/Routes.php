<?php

/**
 * Rutas del componente Networking (auto-descubiertas). Prefijo `networking`, con
 * los filtros del núcleo (auth + permission). La red es global: no hay filtro por
 * empresa activa.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('networking', ['namespace' => 'Components\Networking\Controllers', 'filter' => 'auth'], static function ($routes) {
    // Pestañas
    $routes->get('/', 'Discover::index', ['filter' => 'permission:net.view']);
    $routes->get('descubrir', 'Discover::index', ['filter' => 'permission:net.view']);
    $routes->get('recibidas', 'Requests::received', ['filter' => 'permission:net.view']);
    $routes->get('enviadas', 'Requests::sent', ['filter' => 'permission:net.view']);
    $routes->get('conexiones', 'Connections::index', ['filter' => 'permission:net.view']);

    // Perfiles
    $routes->match(['GET', 'POST'], 'mi-perfil', 'Profiles::mine', ['filter' => 'permission:net.edit']);
    $routes->get('perfil/(:num)', 'Profiles::show/$1', ['filter' => 'permission:net.view']);

    // Solicitudes
    $routes->post('solicitar/(:num)', 'Requests::send/$1', ['filter' => 'permission:net.create']);
    $routes->post('responder/(:num)', 'Requests::respond/$1', ['filter' => 'permission:net.edit']);
    $routes->post('cancelar/(:num)', 'Requests::cancel/$1', ['filter' => 'permission:net.delete']);

    // Mensajes y conexiones
    $routes->get('mensajes/(:num)', 'Messages::thread/$1', ['filter' => 'permission:net.view']);
    $routes->post('mensajes/(:num)', 'Messages::send/$1', ['filter' => 'permission:net.edit']);
    $routes->post('conexiones/eliminar/(:num)', 'Connections::remove/$1', ['filter' => 'permission:net.delete']);
});
