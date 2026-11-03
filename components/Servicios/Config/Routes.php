<?php

/**
 * Rutas del componente Servicios (auto-descubiertas). Prefijo `servicios`,
 * con los filtros del núcleo (auth + permission). Todo se acota a la empresa
 * activa dentro de los controladores.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

// --- Compatibilidad: el componente se llamaba «Cotizaciones» y sus rutas
// colgaban de `/cotizaciones`. Los DOS enlaces públicos ya están en manos de
// clientes (cotizaciones compartidas y tarjetas digitales impresas o
// escaneadas), así que el prefijo viejo redirige al nuevo en vez de dar 404.
$routes->get('cotizaciones/p/(:segment)', static fn ($t) => redirect()->to(site_url('servicios/p/' . $t)));
$routes->get('cotizaciones/p/(:segment)/pdf', static fn ($t) => redirect()->to(site_url('servicios/p/' . $t . '/pdf')));
$routes->get('cotizaciones/tarjeta/(:segment)', static fn ($t) => redirect()->to(site_url('servicios/tarjeta/' . $t)));
$routes->get('cotizaciones/tarjeta/(:segment)/vcf', static fn ($t) => redirect()->to(site_url('servicios/tarjeta/' . $t . '/vcf')));

// --- Público: el CLIENTE abre la cotización con su token, sin cuenta ni empresa
// activa. Sin filtro `auth` a propósito; el acceso lo gobierna el token (y la
// contraseña opcional) dentro del controlador.
$routes->group('servicios/p', ['namespace' => 'Components\Servicios\Controllers'], static function ($routes) {
    $routes->get('(:segment)', 'PublicQuote::show/$1');
    $routes->get('(:segment)/pdf', 'PublicQuote::pdf/$1');
    $routes->post('(:segment)/acceso', 'PublicQuote::unlock/$1');
    $routes->post('(:segment)/responder', 'PublicQuote::respond/$1');
});

// --- Público: la tarjeta digital del vendedor. Es lo que se comparte con el
// cliente, así que tampoco lleva `auth`; el token evita que el enlace se pueda
// enumerar y el controlador comprueba que vendedor y empresa sigan activos.
$routes->group('servicios/tarjeta', ['namespace' => 'Components\Servicios\Controllers'], static function ($routes) {
    $routes->get('(:segment)', 'PublicCard::show/$1');
    $routes->get('(:segment)/vcf', 'PublicCard::vcf/$1');
});

// --- Público: el catálogo digital que arma el cliente. El token dice qué
// catálogo es y QUÉ VENDEDOR atiende; el pedido entra como cotización suya.
$routes->group('servicios/catalogo', ['namespace' => 'Components\Servicios\Controllers'], static function ($routes) {
    $routes->get('(:segment)', 'PublicCatalog::show/$1');
    $routes->get('(:segment)/items', 'PublicCatalog::items/$1');   // carga progresiva
    $routes->get('(:segment)/pdf', 'PublicCatalog::pdf/$1');
    $routes->post('(:segment)/pedido', 'PublicCatalog::order/$1');
});

$routes->group('servicios', ['namespace' => 'Components\Servicios\Controllers', 'filter' => 'auth'], static function ($routes) {
    // La raíz del módulo lleva al primer servicio; mañana puede ser un índice
    // de servicios cuando haya más de uno.
    $routes->get('/', static fn () => redirect()->to(site_url('servicios/cotizaciones')));

    // --- Servicio: Cotizaciones (uno más entre los del módulo) ---------------
    // Gestión del enlace público (interna)
    $routes->post('cotizaciones/compartir/(:num)', 'Quotes::share/$1', ['filter' => 'permission:quo.edit']);
    $routes->post('cotizaciones/compartir/(:num)/cerrar', 'Quotes::unshare/$1', ['filter' => 'permission:quo.edit']);

    // Panel y documentos
    $routes->get('cotizaciones', 'Quotes::index', ['filter' => 'permission:quo.view']);
    $routes->match(['GET', 'POST'], 'cotizaciones/nueva', 'Quotes::create', ['filter' => 'permission:quo.create']);
    $routes->match(['GET', 'POST'], 'cotizaciones/editar/(:num)', 'Quotes::edit/$1', ['filter' => 'permission:quo.edit']);
    $routes->get('cotizaciones/ver/(:num)', 'Quotes::show/$1', ['filter' => 'permission:quo.view']);
    $routes->get('cotizaciones/pdf/(:num)', 'Quotes::pdf/$1', ['filter' => 'permission:quo.view']);
    $routes->post('cotizaciones/estado/(:num)', 'Quotes::changeStatus/$1', ['filter' => 'permission:quo.edit']);
    $routes->post('cotizaciones/eliminar/(:num)', 'Quotes::delete/$1', ['filter' => 'permission:quo.delete']);
    $routes->post('cotizaciones/demo', 'Quotes::seedDemo', ['filter' => 'permission:quo.create']);

    // Autocompletado del cotizador (JSON)
    $routes->get('buscar/clientes', 'Search::clients', ['filter' => 'permission:quo.view']);
    $routes->get('buscar/productos', 'Search::products', ['filter' => 'permission:quo.view']);

    // Catálogo digital
    $routes->get('catalogo-digital', 'Catalogs::index', ['filter' => 'permission:quo.view']);
    $routes->match(['GET', 'POST'], 'catalogo-digital/nuevo', 'Catalogs::create', ['filter' => 'permission:quo.create']);
    $routes->match(['GET', 'POST'], 'catalogo-digital/editar/(:num)', 'Catalogs::edit/$1', ['filter' => 'permission:quo.edit']);
    $routes->get('catalogo-digital/enlaces/(:num)', 'Catalogs::links/$1', ['filter' => 'permission:quo.view']);
    $routes->post('catalogo-digital/eliminar/(:num)', 'Catalogs::delete/$1', ['filter' => 'permission:quo.delete']);

    // Maestro de clientes
    $routes->get('clientes', 'Clients::index', ['filter' => 'permission:quo.view']);
    $routes->match(['GET', 'POST'], 'clientes/nuevo', 'Clients::create', ['filter' => 'permission:quo.create']);
    $routes->match(['GET', 'POST'], 'clientes/editar/(:num)', 'Clients::edit/$1', ['filter' => 'permission:quo.edit']);
    $routes->post('clientes/eliminar/(:num)', 'Clients::delete/$1', ['filter' => 'permission:quo.delete']);
    $routes->post('clientes/rapido', 'Clients::quickStore', ['filter' => 'permission:quo.create']); // alta desde el cotizador

    // Categorías del catálogo
    $routes->get('categorias', 'Categories::index', ['filter' => 'permission:quo.view']);
    $routes->match(['GET', 'POST'], 'categorias/nuevo', 'Categories::create', ['filter' => 'permission:quo.create']);
    $routes->match(['GET', 'POST'], 'categorias/editar/(:num)', 'Categories::edit/$1', ['filter' => 'permission:quo.edit']);
    $routes->post('categorias/eliminar/(:num)', 'Categories::delete/$1', ['filter' => 'permission:quo.delete']);
    $routes->post('categorias/rapido', 'Categories::quickStore', ['filter' => 'permission:quo.create']); // alta desde el producto

    // Maestro de productos
    $routes->get('productos', 'Products::index', ['filter' => 'permission:quo.view']);
    $routes->match(['GET', 'POST'], 'productos/nuevo', 'Products::create', ['filter' => 'permission:quo.create']);
    $routes->match(['GET', 'POST'], 'productos/editar/(:num)', 'Products::edit/$1', ['filter' => 'permission:quo.edit']);
    $routes->post('productos/eliminar/(:num)', 'Products::delete/$1', ['filter' => 'permission:quo.delete']);
    $routes->get('productos/plantilla', 'Products::template', ['filter' => 'permission:quo.view']);
    $routes->match(['GET', 'POST'], 'productos/importar', 'Products::import', ['filter' => 'permission:quo.create']);
    $routes->post('productos/rapido', 'Products::quickStore', ['filter' => 'permission:quo.create']);

    // Maestro de vendedores
    $routes->get('vendedores', 'Sellers::index', ['filter' => 'permission:quo.view']);
    $routes->match(['GET', 'POST'], 'vendedores/nuevo', 'Sellers::create', ['filter' => 'permission:quo.create']);
    $routes->match(['GET', 'POST'], 'vendedores/editar/(:num)', 'Sellers::edit/$1', ['filter' => 'permission:quo.edit']);
    $routes->post('vendedores/eliminar/(:num)', 'Sellers::delete/$1', ['filter' => 'permission:quo.delete']);

    // Ajustes del documento
    $routes->match(['GET', 'POST'], 'configuracion', 'Settings::index', ['filter' => 'permission:quo.configure']);
});
