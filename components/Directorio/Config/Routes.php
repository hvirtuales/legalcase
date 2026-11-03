<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

/**
 * Rutas del Directorio (ámbito tenant).
 *
 * `['auth', 'tenant']` es obligatorio en un componente de grupo: `auth` da la
 * sesión y `tenant` garantiza que hay contexto de grupo, que el usuario lo
 * administra de verdad y que su suscripción está vigente. El `permission:` de
 * cada acción sigue siendo necesario: el filtro dice **sobre qué grupo**, el
 * permiso dice **qué puede hacer**.
 *
 * El componente **es** las vistas: el listado de entradas escritas a mano
 * (`cmp_dir_entries`) se retiró en v3.0.0 al no usarse, así que las vistas
 * ocupan la raíz en vez de colgar de `/directorio/vistas`.
 */
$routes->group('directorio', [
    'namespace' => 'Components\Directorio\Controllers',
    'filter'    => ['auth', 'tenant'],
], static function ($routes) {
    $routes->get('/', 'Boards::index', ['filter' => 'permission:dir.view']);
    $routes->get('nueva', 'Boards::create', ['filter' => 'permission:dir.create']);
    $routes->post('nueva', 'Boards::create', ['filter' => 'permission:dir.create']);
    $routes->get('editar/(:num)', 'Boards::edit/$1', ['filter' => 'permission:dir.edit']);
    $routes->post('editar/(:num)', 'Boards::edit/$1', ['filter' => 'permission:dir.edit']);
    $routes->post('eliminar/(:num)', 'Boards::delete/$1', ['filter' => 'permission:dir.delete']);
});

// Público: sin auth ni contexto de sesión — el grupo se identifica por su slug.
$routes->group('directorio-publico', ['namespace' => 'Components\Directorio\Controllers'], static function ($routes) {
    // Los recursos del componente (Leaflet auto-hospedado) los sirve él mismo:
    // la publicación a `public/components/<code>/` que describe el contrato §2
    // nunca se implementó en el núcleo, así que servirlos desde aquí es lo que
    // mantiene la carpeta drop-in de verdad. Va ANTES del comodín `(:segment)`,
    // o «recurso» se tomaría por el slug de un grupo.
    $routes->get('recurso/(:segment)', 'Asset::show/$1');
    $routes->get('recurso/images/(:segment)', 'Asset::image/$1');
    $routes->get('(:segment)', 'PublicDirectory::show/$1');
    $routes->get('(:segment)/(:segment)', 'PublicDirectory::board/$1/$2');
});
