<?php

/**
 * Rutas del componente Contabilidad (auto-descubiertas). Todo bajo el prefijo
 * `contabilidad`, con los filtros del núcleo (auth + permission). La
 * verificación de comprobantes es PÚBLICA (sin auth).
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

// Panel / Reportes
$routes->group('contabilidad/panel', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Reports::index', ['filter' => 'permission:cont.view']);
});

// Beneficiarios / Terceros
$routes->group('contabilidad/beneficiarios', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Beneficiaries::index', ['filter' => 'permission:cont.view']);
    $routes->get('plantilla', 'Beneficiaries::template', ['filter' => 'permission:cont.view']);
    $routes->match(['GET', 'POST'], 'importar', 'Beneficiaries::import', ['filter' => 'permission:cont.create']);
    $routes->match(['GET', 'POST'], 'crear', 'Beneficiaries::create', ['filter' => 'permission:cont.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Beneficiaries::edit/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('eliminar/(:num)', 'Beneficiaries::delete/$1', ['filter' => 'permission:cont.delete']);
});

// Comprobantes contables
$routes->group('contabilidad/comprobantes', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Vouchers::index', ['filter' => 'permission:cont.view']);
    $routes->match(['GET', 'POST'], 'nuevo', 'Vouchers::create', ['filter' => 'permission:cont.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Vouchers::edit/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('estado/(:num)', 'Vouchers::status/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('eliminar/(:num)', 'Vouchers::delete/$1', ['filter' => 'permission:cont.delete']);
    $routes->get('(:num)/pdf', 'Vouchers::pdf/$1', ['filter' => 'permission:cont.view']);
    $routes->get('(:num)', 'Vouchers::show/$1', ['filter' => 'permission:cont.view']);
});

// Catálogo de Impuestos (por empresa)
$routes->group('contabilidad/impuestos', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'TaxConfigs::index', ['filter' => 'permission:cont.view']);
    $routes->post('seed', 'TaxConfigs::seed', ['filter' => 'permission:cont.edit']);
    $routes->match(['GET', 'POST'], 'nuevo', 'TaxConfigs::create', ['filter' => 'permission:cont.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'TaxConfigs::edit/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('eliminar/(:num)', 'TaxConfigs::delete/$1', ['filter' => 'permission:cont.delete']);
});

// Catálogo PUC (global)
$routes->group('contabilidad/puc', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Puc::index', ['filter' => 'permission:cont.view']);
    $routes->post('seed', 'Puc::seed', ['filter' => 'permission:cont.edit']);
    $routes->get('plantilla', 'Puc::template', ['filter' => 'permission:cont.view']);
    $routes->match(['GET', 'POST'], 'importar', 'Puc::import', ['filter' => 'permission:cont.create']);
    $routes->match(['GET', 'POST'], 'nueva', 'Puc::create', ['filter' => 'permission:cont.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Puc::edit/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('eliminar/(:num)', 'Puc::delete/$1', ['filter' => 'permission:cont.delete']);
});

// Cuentas bancarias de la empresa (§5.13)
$routes->group('contabilidad/cuentas-bancarias', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'CompanyAccounts::index', ['filter' => 'permission:cont.view']);
    $routes->match(['GET', 'POST'], 'nueva', 'CompanyAccounts::create', ['filter' => 'permission:cont.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'CompanyAccounts::edit/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('eliminar/(:num)', 'CompanyAccounts::delete/$1', ['filter' => 'permission:cont.delete']);
});

// Cajas menores (§5.14)
$routes->group('contabilidad/cajas', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'CashBoxes::index', ['filter' => 'permission:cont.view']);
    $routes->match(['GET', 'POST'], 'nueva', 'CashBoxes::create', ['filter' => 'permission:cont.create']);
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'CashBoxes::edit/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('eliminar/(:num)', 'CashBoxes::delete/$1', ['filter' => 'permission:cont.delete']);
    $routes->post('(:num)/movimiento', 'CashBoxes::addMovement/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('(:num)/movimiento/eliminar/(:num)', 'CashBoxes::deleteMovement/$1/$2', ['filter' => 'permission:cont.edit']);
    $routes->get('(:num)', 'CashBoxes::show/$1', ['filter' => 'permission:cont.view']);
});

// Cuentas de cobro — panel admin
$routes->group('contabilidad/cuentas-cobro', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'PaymentRequests::index', ['filter' => 'permission:cont.view']);
    $routes->post('retencion/(:num)', 'PaymentRequests::setRetencion/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('aprobar/(:num)', 'PaymentRequests::approve/$1', ['filter' => 'permission:cont.edit']);
    $routes->post('rechazar/(:num)', 'PaymentRequests::reject/$1', ['filter' => 'permission:cont.edit']);
    $routes->get('(:num)/pdf', 'PaymentRequests::pdf/$1', ['filter' => 'permission:cont.view']);
    $routes->get('(:num)', 'PaymentRequests::show/$1', ['filter' => 'permission:cont.view']);
});

// Cuenta de cobro — formulario PÚBLICO por empresa (sin login)
$routes->get('contabilidad/solicitud/(:num)', 'PublicPaymentRequest::form/$1', ['namespace' => 'Components\Contabilidad\Controllers']);
$routes->post('contabilidad/solicitud/(:num)', 'PublicPaymentRequest::submit/$1', ['namespace' => 'Components\Contabilidad\Controllers']);

// Adjuntos (polimórficos) — descarga/gestión privada
$routes->group('contabilidad/adjuntos', ['namespace' => 'Components\Contabilidad\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->post('subir', 'Attachments::upload', ['filter' => 'permission:cont.edit']);
    $routes->post('eliminar/(:num)', 'Attachments::delete/$1', ['filter' => 'permission:cont.edit']);
    $routes->get('(:num)', 'Attachments::download/$1', ['filter' => 'permission:cont.view']);
});

// Verificación PÚBLICA de comprobantes (sin login) — destino del QR
$routes->get('contabilidad/verificar/(:num)', 'Verify::show/$1', ['namespace' => 'Components\Contabilidad\Controllers']);
