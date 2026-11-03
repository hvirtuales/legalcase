<?php

/**
 * Rutas del componente Formularios (auto-descubiertas).
 *
 * Dos grupos bien separados (contrato 4):
 *   - `formularios` administración. Todo bajo `auth` + `permission:frm.*`.
 *   - `formulario`  público. El formulario publicado, su envío, el comprobante
 *                   de la respuesta y los assets. Sin sesión: el acceso lo
 *                   gobiernan el token y el estado del formulario.
 *
 * Los borrados y los cambios de estado son POST con CSRF, nunca enlaces GET.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('formularios', ['namespace' => 'Components\Formularios\Controllers', 'filter' => 'auth'], static function ($routes) {
    // --- Formularios -----------------------------------------------------
    $routes->get('/', 'Forms::index', ['filter' => 'permission:frm.view']);
    // Alta: SOLO POST. Es una mutación, y el diálogo del listado es donde se
    // elige la presentación (ficha o pantalla completa).
    $routes->post('nuevo', 'Forms::create', ['filter' => 'permission:frm.create']);
    $routes->post('duplicar/(:num)', 'Forms::duplicate/$1', ['filter' => 'permission:frm.create']);
    $routes->post('publicar/(:num)', 'Forms::publish/$1', ['filter' => 'permission:frm.edit']);
    $routes->post('estado/(:num)', 'Forms::changeStatus/$1', ['filter' => 'permission:frm.edit']);
    $routes->post('eliminar/(:num)', 'Forms::delete/$1', ['filter' => 'permission:frm.delete']);
    $routes->post('demo', 'Forms::seedDemo', ['filter' => 'permission:frm.create']);
    $routes->get('compartir/(:num)', 'Forms::share/$1', ['filter' => 'permission:frm.view']);

    // --- Editor ----------------------------------------------------------
    $routes->get('editor/(:num)', 'Editor::index/$1', ['filter' => 'permission:frm.edit']);
    $routes->post('editor/(:num)', 'Editor::save/$1', ['filter' => 'permission:frm.edit']);
    $routes->post('editor/(:num)/ajustes', 'Editor::saveSettings/$1', ['filter' => 'permission:frm.edit']);
    // Token de CSRF fresco para el editor (GET: no consume ni rota nada).
    $routes->get('token', 'Editor::token', ['filter' => 'permission:frm.edit']);

    // --- Respuestas ------------------------------------------------------
    $routes->get('respuestas', 'Responses::all', ['filter' => 'permission:frm.view']);
    $routes->get('respuestas/(:num)', 'Responses::index/$1', ['filter' => 'permission:frm.view']);
    $routes->get('respuesta/(:num)', 'Responses::show/$1', ['filter' => 'permission:frm.view']);
    $routes->get('informe/(:num)', 'Responses::report/$1', ['filter' => 'permission:frm.view']);
    $routes->get('adjunto/(:num)', 'Responses::attachment/$1', ['filter' => 'permission:frm.view']);
    $routes->post('respuesta/leida/(:num)', 'Responses::markRead/$1', ['filter' => 'permission:frm.edit']);
    $routes->post('respuesta/eliminar/(:num)', 'Responses::delete/$1', ['filter' => 'permission:frm.delete']);
    // «todo» va antes que el id: son dos rutas distintas y `(:num)` no lo
    // atraparía, pero leerlas en este orden explica que existen las dos.
    $routes->get('exportar/todo/(:segment)', 'Responses::exportAll/$1', ['filter' => 'permission:frm.export']);
    $routes->get('exportar/(:num)/(:segment)', 'Responses::export/$1/$2', ['filter' => 'permission:frm.export']);

    // --- Plantillas y configuración --------------------------------------
    $routes->get('plantillas', 'Templates::index', ['filter' => 'permission:frm.configure']);
    $routes->post('plantillas/usar/(:num)', 'Templates::useTemplate/$1', ['filter' => 'permission:frm.create']);
    $routes->post('plantillas/desde/(:num)', 'Templates::fromForm/$1', ['filter' => 'permission:frm.configure']);
    $routes->post('plantillas/sembrar', 'Templates::seed', ['filter' => 'permission:frm.configure']);
    $routes->post('plantillas/eliminar/(:num)', 'Templates::delete/$1', ['filter' => 'permission:frm.configure']);
    $routes->get('configuracion', 'Settings::index', ['filter' => 'permission:frm.configure']);
    $routes->post('configuracion', 'Settings::save', ['filter' => 'permission:frm.configure']);
});

/*
 * Público. El formulario se identifica por su TOKEN aleatorio y no por su id:
 * con el id se podrían recorrer los formularios de todas las empresas.
 */
$routes->group('formulario', ['namespace' => 'Components\Formularios\Controllers'], static function ($routes) {
    // Assets del editor y del formulario. Fuera de `auth` porque son estáticos
    // y cacheables: el contenido no depende del usuario.
    $routes->get('recurso/(:segment)', 'Asset::serve/$1');

    $routes->get('r/(:segment)', 'PublicForm::receipt/$1');
    $routes->get('r/(:segment)/pdf', 'PublicForm::receiptPdf/$1');
    $routes->get('(:segment)', 'PublicForm::show/$1');
    $routes->post('(:segment)', 'PublicForm::submit/$1');
});
