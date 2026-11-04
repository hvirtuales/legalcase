<?php

/**
 * Rutas del componente Mailer (auto-descubiertas). Admin bajo el prefijo
 * `mailer` (auth + permission del núcleo). La baja del suscriptor y el webhook
 * de SNS son PÚBLICOS (sin auth). El webhook va bajo `api/v1/*` para quedar
 * exento de CSRF (SNS no envía token).
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

// Campañas
$routes->group('mailer/campanas', ['namespace' => 'Components\Mailer\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Campaigns::index', ['filter' => 'permission:mailer.view']);
    $routes->match(['GET', 'POST'], 'crear', 'Campaigns::create', ['filter' => 'permission:mailer.create']);
    // El asistente de tres pasos: destinatarios, contenido y revisión.
    $routes->match(['GET', 'POST'], 'asistente/(:num)', 'Campaigns::wizard/$1', ['filter' => 'permission:mailer.edit']);
    $routes->get('editar/(:num)', 'Campaigns::edit/$1', ['filter' => 'permission:mailer.edit']);
    $routes->get('(:num)/preview', 'Campaigns::preview/$1', ['filter' => 'permission:mailer.view']);
    $routes->get('(:num)/reporte', 'Campaigns::report/$1', ['filter' => 'permission:mailer.view']);
    $routes->get('(:num)/reporte/csv', 'Campaigns::reportCsv/$1', ['filter' => 'permission:mailer.view']);
    $routes->post('prueba/(:num)', 'Campaigns::test/$1', ['filter' => 'permission:mailer.send']);
    $routes->post('enviar/(:num)', 'Campaigns::send/$1', ['filter' => 'permission:mailer.send']);
    $routes->post('encolar/(:num)', 'Campaigns::queue/$1', ['filter' => 'permission:mailer.send']);
    $routes->post('eliminar/(:num)', 'Campaigns::delete/$1', ['filter' => 'permission:mailer.delete']);
    $routes->get('(:num)', 'Campaigns::show/$1', ['filter' => 'permission:mailer.view']);
});

// Listas de contactos («bases de datos»)
$routes->group('mailer/listas', ['namespace' => 'Components\Mailer\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Lists::index', ['filter' => 'permission:mailer.view']);
    $routes->match(['GET', 'POST'], 'crear', 'Lists::create', ['filter' => 'permission:mailer.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Lists::edit/$1', ['filter' => 'permission:mailer.edit']);
    $routes->post('eliminar/(:num)', 'Lists::delete/$1', ['filter' => 'permission:mailer.delete']);
    // Contactos de una lista
    $routes->post('(:num)/contactos/agregar', 'Contacts::add/$1', ['filter' => 'permission:mailer.edit']);
    $routes->post('(:num)/verificar-dominios', 'Contacts::verifyDomains/$1', ['filter' => 'permission:mailer.edit']);
    $routes->post('(:num)/eliminar-duplicados', 'Contacts::removeDuplicates/$1', ['filter' => 'permission:mailer.delete']);
    $routes->match(['GET', 'POST'], '(:num)/contactos/importar', 'Contacts::import/$1', ['filter' => 'permission:mailer.create']);
    $routes->match(['GET', 'POST'], '(:num)/contactos/revisar', 'Contacts::review/$1', ['filter' => 'permission:mailer.create']);
    $routes->get('(:num)', 'Lists::show/$1', ['filter' => 'permission:mailer.view']);
});

// Contactos (acciones sueltas + plantilla)
$routes->group('mailer/contactos', ['namespace' => 'Components\Mailer\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('plantilla', 'Contacts::template', ['filter' => 'permission:mailer.view']);
    $routes->post('baja/(:num)', 'Contacts::unsubscribe/$1', ['filter' => 'permission:mailer.edit']);
    $routes->post('eliminar/(:num)', 'Contacts::delete/$1', ['filter' => 'permission:mailer.delete']);
});

// Subida de imágenes del editor. Lleva permiso: `auth` a secas dejaba subir
// archivos a cualquiera con sesión, tuviera o no nada que ver con los correos.
$routes->post('mailer/media/subir', 'Media::upload', ['namespace' => 'Components\Mailer\Controllers', 'filter' => ['auth', 'permission:mailer.edit']]);

// Assets del editor: los sirve el componente desde su carpeta, porque el núcleo
// nunca implementó la publicación a `public/components/<code>/` (contrato 2).
// Fuera de `auth` porque son estáticos y cacheables.
$routes->get('mailer-publico/recurso/(:segment)', 'Asset::serve/$1', ['namespace' => 'Components\Mailer\Controllers']);

// Plantillas
$routes->group('mailer/plantillas', ['namespace' => 'Components\Mailer\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Templates::index', ['filter' => 'permission:mailer.view']);
    $routes->get('(:num)/preview', 'Templates::preview/$1', ['filter' => 'permission:mailer.view']);
    $routes->post('prueba/(:num)', 'Templates::test/$1', ['filter' => 'permission:mailer.send']);
    $routes->match(['GET', 'POST'], 'crear', 'Templates::create', ['filter' => 'permission:mailer.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Templates::edit/$1', ['filter' => 'permission:mailer.edit']);
    $routes->post('eliminar/(:num)', 'Templates::delete/$1', ['filter' => 'permission:mailer.delete']);
});

// Bajas y rebotes (supresión)
$routes->group('mailer/supresiones', ['namespace' => 'Components\Mailer\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Suppressions::index', ['filter' => 'permission:mailer.view']);
    $routes->post('agregar', 'Suppressions::add', ['filter' => 'permission:mailer.edit']);
    $routes->post('eliminar/(:num)', 'Suppressions::delete/$1', ['filter' => 'permission:mailer.delete']);
});

// PÚBLICO: baja del suscriptor (enlace del pie del correo)
$routes->match(['GET', 'POST'], 'mailer-publico/baja/(:segment)', 'Publico::unsubscribe/$1', ['namespace' => 'Components\Mailer\Controllers']);

// PÚBLICO: alta a una lista, con confirmación por correo (doble opt-in).
// El token es el de la LISTA, no su id: si no, se podrían recorrer las listas
// de todas las empresas de la plataforma.
$routes->match(['GET', 'POST'], 'mailer-publico/suscribir/(:segment)', 'Publico::subscribe/$1', ['namespace' => 'Components\Mailer\Controllers']);
$routes->get('mailer-publico/confirmar/(:segment)', 'Publico::confirmSignup/$1', ['namespace' => 'Components\Mailer\Controllers']);

// PÚBLICO: tracking de apertura (píxel) y de clics (redirector)
$routes->get('mailer-publico/ver/(:segment)', 'Publico::view/$1', ['namespace' => 'Components\Mailer\Controllers']);
$routes->get('mailer-publico/abrir/(:segment)', 'Publico::open/$1', ['namespace' => 'Components\Mailer\Controllers']);
$routes->get('mailer-publico/clic/(:segment)', 'Publico::click/$1', ['namespace' => 'Components\Mailer\Controllers']);

// PÚBLICO: baja en un clic (List-Unsubscribe-Post) — bajo api/v1/* (exento de CSRF)
$routes->match(['GET', 'POST'], 'api/v1/mailer/unsub/(:segment)', 'Publico::oneClick/$1', ['namespace' => 'Components\Mailer\Controllers']);

// PÚBLICO: webhook SNS (rebotes/quejas) — bajo api/v1/* para quedar exento de CSRF
$routes->post('api/v1/mailer/webhook', 'Publico::webhook', ['namespace' => 'Components\Mailer\Controllers']);
