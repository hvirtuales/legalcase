<?php

/**
 * Rutas del componente Mapas.
 *
 * Todo lo de gestión va bajo `mapas` con `auth` + `permission:map.*`; ninguna
 * ruta del grupo se queda sin permiso, ni las que solo muestran. Los borrados
 * son POST, nunca un enlace GET.
 *
 * La página del mapa (`mapa/{token}`) es PÚBLICA: la abre un visitante del
 * centro comercial, sin cuenta. El token va en la URL y es lo único que hace
 * falta; por eso es aleatorio y no el id.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('mapas', ['namespace' => 'Components\Mapas\Controllers', 'filter' => 'auth'], static function ($routes) {
    // --- Mapas
    $routes->get('/', 'Maps::index', ['filter' => 'permission:map.view']);
    $routes->match(['get', 'post'], 'crear', 'Maps::create', ['filter' => 'permission:map.create']);
    $routes->match(['get', 'post'], 'editar/(:num)', 'Maps::edit/$1', ['filter' => 'permission:map.edit']);
    $routes->post('eliminar/(:num)', 'Maps::delete/$1', ['filter' => 'permission:map.delete']);
    // Duplicar es crear: por eso lleva "map.create" y no "map.edit", y por eso
    // pasa por el mismo cupo del plan que el alta.
    $routes->post('duplicar/(:num)', 'Maps::duplicate/$1', ['filter' => 'permission:map.create']);
    $routes->post('publicar/(:num)', 'Maps::publish/$1', ['filter' => 'permission:map.edit']);

    // --- El editor: el lienzo donde se colocan los puntos
    $routes->get('editor/(:num)', 'Editor::index/$1', ['filter' => 'permission:map.view']);
    $routes->get('editor/(:num)/datos', 'Editor::payload/$1', ['filter' => 'permission:map.view']);
    $routes->post('editor/(:num)/ajustes', 'Editor::settings/$1', ['filter' => 'permission:map.edit']);

    // --- Niveles
    $routes->post('(:num)/niveles', 'Levels::store/$1', ['filter' => 'permission:map.create']);
    $routes->post('niveles/(:num)', 'Levels::update/$1', ['filter' => 'permission:map.edit']);
    $routes->post('niveles/(:num)/orden', 'Levels::reorder/$1', ['filter' => 'permission:map.edit']);
    $routes->post('niveles/(:num)/eliminar', 'Levels::delete/$1', ['filter' => 'permission:map.delete']);

    // --- Puntos (los mueve y los edita el lienzo, por JSON)
    $routes->post('niveles/(:num)/puntos', 'Points::store/$1', ['filter' => 'permission:map.create']);
    $routes->post('puntos/(:num)', 'Points::update/$1', ['filter' => 'permission:map.edit']);
    $routes->post('puntos/(:num)/mover', 'Points::move/$1', ['filter' => 'permission:map.edit']);
    // La geometría cambia por su propia ruta, no con el formulario: el editor
    // manda el formulario entero al ocultar un elemento, y ahí se llevaría el
    // dibujo por delante.
    $routes->post('puntos/(:num)/geometria', 'Points::geometry/$1', ['filter' => 'permission:map.edit']);
    // Duplicar un elemento es crear uno: `map.create`, igual que el mapa.
    $routes->post('puntos/(:num)/duplicar', 'Points::duplicate/$1', ['filter' => 'permission:map.create']);
    $routes->post('puntos/(:num)/eliminar', 'Points::delete/$1', ['filter' => 'permission:map.delete']);

    // --- Categorías: nombre y color del pin
    $routes->get('categorias', 'Categories::index', ['filter' => 'permission:map.configure']);
    $routes->match(['get', 'post'], 'categorias/crear', 'Categories::create', ['filter' => 'permission:map.configure']);
    $routes->match(['get', 'post'], 'categorias/editar/(:num)', 'Categories::edit/$1', ['filter' => 'permission:map.configure']);
    $routes->post('categorias/eliminar/(:num)', 'Categories::delete/$1', ['filter' => 'permission:map.configure']);
});

// Assets del componente. Los sirve él mismo con lista blanca fija: la
// publicación a `public/components/<code>/` que describe el contrato nunca se
// implementó en el núcleo, así que esto es lo que lo mantiene drop-in.
$routes->get('mapa/recurso/(:segment)', 'Asset::serve/$1', ['namespace' => 'Components\Mapas\Controllers']);

// PÚBLICO: el mapa que ve el visitante.
$routes->get('mapa/(:segment)', 'Publico::show/$1', ['namespace' => 'Components\Mapas\Controllers']);
$routes->get('mapa/(:segment)/datos', 'Publico::payload/$1', ['namespace' => 'Components\Mapas\Controllers']);