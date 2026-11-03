<?php

/**
 * Rutas del componente Páginas (auto-descubiertas).
 *
 * Dos grupos bien separados (contrato §4):
 *   - `paginas`  → administración. Todo bajo `auth` + `permission:pag.*`.
 *   - `pagina`   → público. La página publicada y el envío del formulario de
 *                  contacto; sin sesión (el acceso lo gobierna `status`).
 *
 * Los borrados y los cambios de estado son POST con CSRF, nunca enlaces GET.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('paginas', ['namespace' => 'Components\Paginas\Controllers', 'filter' => 'auth'], static function ($routes) {
    // --- Páginas ---------------------------------------------------------
    $routes->get('/', 'Pages::index', ['filter' => 'permission:pag.view']);
    // Alta: **solo POST**. Ya no hay pantalla que pedir el título —se entra
    // directo al editor—, y crear una página es una mutación: va por POST con
    // CSRF, como el resto de las que cambian datos.
    $routes->post('nueva', 'Pages::create', ['filter' => 'permission:pag.create']);
    // `ajustes/{id}` se retiró: sus campos viven en la pestaña «Página» del
    // inspector y se guardan por `editor/{id}/ajustes` (JSON).
    $routes->get('editor/(:num)', 'Pages::editor/$1', ['filter' => 'permission:pag.edit']);
    $routes->post('editor/(:num)', 'Pages::saveBlocks/$1', ['filter' => 'permission:pag.edit']);
    // Ajustes desde dentro del editor (JSON): los mismos que la pantalla
    // `ajustes/{id}`, pero sin salir de la página que se está montando.
    $routes->post('editor/(:num)/ajustes', 'Pages::savePageSettings/$1', ['filter' => 'permission:pag.edit']);
    $routes->get('previsualizar/(:num)', 'Pages::preview/$1', ['filter' => 'permission:pag.view']);
    $routes->post('estado/(:num)', 'Pages::changeStatus/$1', ['filter' => 'permission:pag.edit']);
    $routes->post('duplicar/(:num)', 'Pages::duplicate/$1', ['filter' => 'permission:pag.create']);
    $routes->post('eliminar/(:num)', 'Pages::delete/$1', ['filter' => 'permission:pag.delete']);
    $routes->post('demo', 'Pages::seedDemo', ['filter' => 'permission:pag.create']);

    // Token de CSRF fresco para el editor (GET: no consume ni rota nada).
    $routes->get('token', 'Pages::token', ['filter' => 'permission:pag.edit']);

    // --- Biblioteca de medios de la empresa ------------------------------
    // La subida es la MISMA ruta para el editor y para la pantalla: una sola
    // política de tipos, tamaño y optimización.
    $routes->get('medios', 'Media::index', ['filter' => 'permission:pag.view']);
    $routes->get('medios/lista', 'Media::library', ['filter' => 'permission:pag.view']);
    $routes->post('medios/eliminar/(:num)', 'Media::delete/$1', ['filter' => 'permission:pag.delete']);
    $routes->post('subir', 'Media::upload', ['filter' => 'permission:pag.edit']);

    // --- Plantillas reutilizables ---------------------------------------
    $routes->get('plantillas', 'Templates::index', ['filter' => 'permission:pag.view']);
    $routes->get('plantillas/(:num)', 'Templates::preview/$1', ['filter' => 'permission:pag.view']);
    $routes->post('plantillas/desde/(:num)', 'Templates::fromPage/$1', ['filter' => 'permission:pag.configure']);
    $routes->post('plantillas/usar/(:num)', 'Templates::useTemplate/$1', ['filter' => 'permission:pag.create']);
    $routes->post('plantillas/sembrar', 'Templates::seed', ['filter' => 'permission:pag.configure']);
    $routes->post('plantillas/eliminar/(:num)', 'Templates::delete/$1', ['filter' => 'permission:pag.configure']);

    // --- Bloques personalizados ------------------------------------------
    $routes->get('bloques', 'Blocks::index', ['filter' => 'permission:pag.view']);
    $routes->post('bloques/guardar', 'Blocks::store', ['filter' => 'permission:pag.configure']);
    $routes->post('bloques/eliminar/(:num)', 'Blocks::delete/$1', ['filter' => 'permission:pag.configure']);

    // --- Mensajes del formulario de contacto -----------------------------
    $routes->get('mensajes', 'Submissions::index', ['filter' => 'permission:pag.view']);
    $routes->get('mensajes/(:num)', 'Submissions::show/$1', ['filter' => 'permission:pag.view']);
    $routes->post('mensajes/leido/(:num)', 'Submissions::markRead/$1', ['filter' => 'permission:pag.edit']);
    $routes->post('mensajes/eliminar/(:num)', 'Submissions::delete/$1', ['filter' => 'permission:pag.delete']);
});

/*
 * Público: la URL que se comparte es `/pagina/{empresa}/{pagina}`. Se usa el
 * slug de la empresa (único en el núcleo) porque el de la página solo es único
 * dentro de ella: dos empresas pueden tener su «nosotros».
 */
$routes->group('pagina', ['namespace' => 'Components\Paginas\Controllers'], static function ($routes) {
    // Assets del editor. Van aquí (fuera de `auth`) porque son estáticos y
    // cacheables; el contenido no depende del usuario.
    $routes->get('recurso/(:segment)', 'Asset::serve/$1');

    $routes->get('(:segment)/(:segment)', 'PublicPage::show/$1/$2');
    $routes->post('(:segment)/(:segment)/enviar', 'PublicPage::submit/$1/$2');
});
