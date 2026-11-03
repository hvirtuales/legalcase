<?php

/**
 * Rutas del componente Marketplace (auto-descubiertas). Prefijo `marketplace`,
 * con los filtros del núcleo (auth + permission).
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('marketplace', ['namespace' => 'Components\Marketplace\Controllers', 'filter' => 'auth'], static function ($routes) {
    // Catálogo común (oferta y demanda de todas las empresas)
    $routes->get('/', 'Catalog::index', ['filter' => 'permission:mkt.view']);
    $routes->get('publicacion/(:num)', 'Catalog::show/$1', ['filter' => 'permission:mkt.view']);
    $routes->post('publicacion/(:num)/interes', 'Interests::send/$1', ['filter' => 'permission:mkt.create']);

    // Publicaciones de la empresa activa
    $routes->get('mis-publicaciones', 'Listings::mine', ['filter' => 'permission:mkt.view']);
    $routes->match(['GET', 'POST'], 'nueva', 'Listings::create', ['filter' => 'permission:mkt.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Listings::edit/$1', ['filter' => 'permission:mkt.edit']);
    $routes->post('demo', 'Listings::seedDemo', ['filter' => 'permission:mkt.create']);
    $routes->post('estado/(:num)', 'Listings::changeStatus/$1', ['filter' => 'permission:mkt.edit']);
    $routes->post('eliminar/(:num)', 'Listings::delete/$1', ['filter' => 'permission:mkt.delete']);

    // Bandeja de interesados en lo que publica la empresa activa
    $routes->get('interesados', 'Interests::inbox', ['filter' => 'permission:mkt.view']);
    $routes->post('interesados/(:num)/estado', 'Interests::changeStatus/$1', ['filter' => 'permission:mkt.edit']);
});
