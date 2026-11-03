<?php

/**
 * Rutas del componente Copropiedad.
 *
 * Todo cuelga de `/copropiedad` y todo pasa por `auth` + `permission:cop.*`
 * (contrato §4 y §9: ninguna ruta sin permiso, ni siquiera las que solo muestran).
 *
 * El verbo del permiso sigue el criterio del contrato: `view` para consultar,
 * `create` para dar de alta, `edit` para modificar **y para lo que cambia el estado
 * de un registro** (cerrar una relación, revocar una autorización), `delete` para
 * las bajas. Los borrados son **POST** con CSRF, nunca un enlace.
 *
 * `configure` queda para lo estructural —torres, bloques y etapas—: cambiar la
 * estructura del conjunto no es lo mismo que corregir el teléfono de un residente,
 * y hay administraciones que quieren separarlo.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('copropiedad', ['namespace' => 'Components\Copropiedad\Controllers', 'filter' => 'auth'], static function ($routes) {
    // ── Panel ──────────────────────────────────────────────────────────────
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:cop.view']);

    // ── Inmuebles ──────────────────────────────────────────────────────────
    // La FICHA (`ficha/{id}`) es el centro del componente: desde ella se ve y se
    // gestiona todo lo que cuelga del inmueble.
    $routes->get('inmuebles', 'Units::index', ['filter' => 'permission:cop.view']);
    $routes->get('inmuebles/ficha/(:num)', 'Units::show/$1', ['filter' => 'permission:cop.view']);
    $routes->get('inmuebles/historial/(:num)', 'Units::history/$1', ['filter' => 'permission:cop.view']);
    $routes->match(['get', 'post'], 'inmuebles/nuevo', 'Units::create', ['filter' => 'permission:cop.create']);
    $routes->match(['get', 'post'], 'inmuebles/editar/(:num)', 'Units::edit/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('inmuebles/ocupacion/(:num)', 'Units::occupancy/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('inmuebles/eliminar/(:num)', 'Units::delete/$1', ['filter' => 'permission:cop.delete']);
    // Datos de ejemplo. Es un alta masiva, así que va con `create` y por POST.
    $routes->post('demo', 'Units::demo', ['filter' => 'permission:cop.create']);

    // ── Torres, bloques y etapas (estructura) ──────────────────────────────
    $routes->get('torres', 'Blocks::index', ['filter' => 'permission:cop.configure']);
    $routes->match(['get', 'post'], 'torres/nuevo', 'Blocks::create', ['filter' => 'permission:cop.configure']);
    $routes->match(['get', 'post'], 'torres/editar/(:num)', 'Blocks::edit/$1', ['filter' => 'permission:cop.configure']);
    $routes->post('torres/eliminar/(:num)', 'Blocks::delete/$1', ['filter' => 'permission:cop.configure']);

    // ── Personas ───────────────────────────────────────────────────────────
    $routes->get('personas', 'People::index', ['filter' => 'permission:cop.view']);
    $routes->get('personas/ficha/(:num)', 'People::show/$1', ['filter' => 'permission:cop.view']);
    $routes->get('personas/buscar', 'People::search', ['filter' => 'permission:cop.view']);
    $routes->match(['get', 'post'], 'personas/nuevo', 'People::create', ['filter' => 'permission:cop.create']);
    $routes->match(['get', 'post'], 'personas/editar/(:num)', 'People::edit/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('personas/eliminar/(:num)', 'People::delete/$1', ['filter' => 'permission:cop.delete']);

    // ── Grupos familiares ──────────────────────────────────────────────────
    $routes->get('familias', 'Families::index', ['filter' => 'permission:cop.view']);
    $routes->get('familias/ficha/(:num)', 'Families::show/$1', ['filter' => 'permission:cop.view']);
    $routes->match(['get', 'post'], 'familias/nuevo', 'Families::create', ['filter' => 'permission:cop.create']);
    $routes->match(['get', 'post'], 'familias/editar/(:num)', 'Families::edit/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('familias/miembro/(:num)', 'Families::addMember/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('familias/quitar/(:num)', 'Families::removeMember/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('familias/eliminar/(:num)', 'Families::delete/$1', ['filter' => 'permission:cop.delete']);

    // ── Relación persona ↔ inmueble (y, por tanto, el historial) ───────────
    // Cerrar NO es eliminar: cierra el periodo y la fila se queda como historia.
    $routes->post('relaciones/nueva', 'Relations::store', ['filter' => 'permission:cop.create']);
    $routes->post('relaciones/cerrar/(:num)', 'Relations::close/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('relaciones/reabrir/(:num)', 'Relations::reopen/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('relaciones/eliminar/(:num)', 'Relations::delete/$1', ['filter' => 'permission:cop.delete']);

    // ── Vehículos ──────────────────────────────────────────────────────────
    $routes->get('vehiculos', 'Vehicles::index', ['filter' => 'permission:cop.view']);
    $routes->match(['get', 'post'], 'vehiculos/nuevo', 'Vehicles::create', ['filter' => 'permission:cop.create']);
    $routes->match(['get', 'post'], 'vehiculos/editar/(:num)', 'Vehicles::edit/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('vehiculos/eliminar/(:num)', 'Vehicles::delete/$1', ['filter' => 'permission:cop.delete']);

    // ── Mascotas ───────────────────────────────────────────────────────────
    $routes->get('mascotas', 'Pets::index', ['filter' => 'permission:cop.view']);
    $routes->match(['get', 'post'], 'mascotas/nuevo', 'Pets::create', ['filter' => 'permission:cop.create']);
    $routes->match(['get', 'post'], 'mascotas/editar/(:num)', 'Pets::edit/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('mascotas/eliminar/(:num)', 'Pets::delete/$1', ['filter' => 'permission:cop.delete']);

    // ── Contactos de emergencia ────────────────────────────────────────────
    $routes->get('contactos', 'Contacts::index', ['filter' => 'permission:cop.view']);
    $routes->match(['get', 'post'], 'contactos/nuevo', 'Contacts::create', ['filter' => 'permission:cop.create']);
    $routes->match(['get', 'post'], 'contactos/editar/(:num)', 'Contacts::edit/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('contactos/eliminar/(:num)', 'Contacts::delete/$1', ['filter' => 'permission:cop.delete']);

    // ── Autorizaciones especiales ──────────────────────────────────────────
    $routes->get('autorizaciones', 'Authorizations::index', ['filter' => 'permission:cop.view']);
    $routes->match(['get', 'post'], 'autorizaciones/nuevo', 'Authorizations::create', ['filter' => 'permission:cop.create']);
    $routes->match(['get', 'post'], 'autorizaciones/editar/(:num)', 'Authorizations::edit/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('autorizaciones/revocar/(:num)', 'Authorizations::revoke/$1', ['filter' => 'permission:cop.edit']);
    $routes->post('autorizaciones/eliminar/(:num)', 'Authorizations::delete/$1', ['filter' => 'permission:cop.delete']);

    // ── Documentos (privados: fuera de public/, descarga por controlador) ───
    $routes->post('documentos/subir', 'Documents::upload', ['filter' => 'permission:cop.create']);
    $routes->get('documentos/(:num)', 'Documents::download/$1', ['filter' => 'permission:cop.view']);
    $routes->post('documentos/eliminar/(:num)', 'Documents::delete/$1', ['filter' => 'permission:cop.delete']);
});
