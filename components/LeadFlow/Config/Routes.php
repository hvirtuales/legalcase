<?php

/**
 * Rutas del componente LeadFlow Legal.
 *
 * Dos grupos, separados y evidentes (contrato §4):
 *
 *  · `leadflow` — el panel de la firma. Todo pasa por `auth` + `permission:lfl.*`,
 *    ni una ruta sin permiso. Verbos: `view` consultar, `create` dar de alta,
 *    `edit` modificar y cambiar el estado de un registro (mover de etapa,
 *    completar, confirmar), `delete` bajas, `configure` lo estructural,
 *    `convert` la conversión a Docketwise e `import` el CSV.
 *  · `leadflow-publico` — el formulario que llena el prospecto, sin sesión. El
 *    acceso lo gobierna el token aleatorio del formulario; CSRF sigue activo.
 *
 * Además del permiso, cada acción comprueba en el servicio que el lead sea de la
 * empresa activa **y** visible para la función comercial del usuario.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('leadflow', ['namespace' => 'Components\LeadFlow\Controllers', 'filter' => 'auth'], static function ($routes) {
    // ── Inicio y reportes ────────────────────────────────────────────────────
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:lfl.view']);
    $routes->get('reportes', 'Dashboard::reports', ['filter' => 'permission:lfl.view']);

    // ── Leads ────────────────────────────────────────────────────────────────
    $routes->get('leads', 'Leads::index', ['filter' => 'permission:lfl.view']);
    $routes->match(['get', 'post'], 'leads/nuevo', 'Leads::create', ['filter' => 'permission:lfl.create']);
    $routes->get('leads/ficha/(:num)', 'Leads::show/$1', ['filter' => 'permission:lfl.view']);
    $routes->match(['get', 'post'], 'leads/editar/(:num)', 'Leads::edit/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('leads/etapa/(:num)', 'Leads::stage/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('leads/asignar/(:num)', 'Leads::assign/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('leads/prioridad/(:num)', 'Leads::priority/$1', ['filter' => 'permission:lfl.edit']);
    // Consultar el caso ahora: gasta cupo, así que es una acción explícita.
    $routes->post('leads/caso/(:num)', 'Leads::caseRefresh/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('leads/eliminar/(:num)', 'Leads::delete/$1', ['filter' => 'permission:lfl.delete']);
    $routes->post('leads/demo', 'Leads::demo', ['filter' => 'permission:lfl.create']);

    // Importación CSV
    $routes->get('leads/importar', 'Import::index', ['filter' => 'permission:lfl.import']);
    $routes->post('leads/importar', 'Import::process', ['filter' => 'permission:lfl.import']);
    $routes->get('leads/plantilla', 'Import::template', ['filter' => 'permission:lfl.import']);

    // Precalificación: llenar un formulario desde la ficha (llamada, entrevista)
    $routes->match(['get', 'post'], 'leads/precalificar/(:num)/(:num)', 'Forms::fill/$1/$2', ['filter' => 'permission:lfl.edit']);

    // Notas y comunicaciones
    $routes->post('leads/nota/(:num)', 'Engagement::note/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('leads/nota/eliminar/(:num)', 'Engagement::deleteNote/$1', ['filter' => 'permission:lfl.delete']);
    $routes->post('leads/comunicacion/(:num)', 'Engagement::log/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('leads/email/(:num)', 'Engagement::email/$1', ['filter' => 'permission:lfl.create']);

    // ── Pipeline (Kanban) ────────────────────────────────────────────────────
    $routes->get('pipeline', 'Pipeline::index', ['filter' => 'permission:lfl.view']);
    $routes->post('pipeline/mover', 'Pipeline::move', ['filter' => 'permission:lfl.edit']);

    /*
     * ── Expedientes ──────────────────────────────────────────────────────────
     *
     * El segmento es `expedientes` y no `casos` porque `caso` ya está tomado y
     * significa otra cosa: `leads/caso/(:num)` refresca el trámite en USCIS y
     * `firma/(:segment)/caso` es la consulta pública de ese estado.
     *
     * `expedientes/abrir/(:num)` es **la única de este bloque cuyo `(:num)` es un id
     * de LEAD**, porque el expediente todavía no existe cuando se llama. Todas las
     * demás reciben el id del expediente.
     *
     * Abrir lleva `lfl.convert`: ese permiso ya significa «convertir un lead ganado en
     * cliente», y el expediente es esa conversión — Docketwise solo era el destino.
     */
    $routes->get('expedientes', 'Matters::index', ['filter' => 'permission:lfl.view']);
    $routes->get('expedientes/ficha/(:num)', 'Matters::show/$1', ['filter' => 'permission:lfl.view']);
    $routes->match(['get', 'post'], 'expedientes/abrir/(:num)', 'Matters::open/$1', ['filter' => 'permission:lfl.convert']);
    $routes->match(['get', 'post'], 'expedientes/editar/(:num)', 'Matters::edit/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('expedientes/etapa/(:num)', 'Matters::stage/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('expedientes/cerrar/(:num)', 'Matters::close/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('expedientes/reabrir/(:num)', 'Matters::reopen/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('expedientes/eliminar/(:num)', 'Matters::delete/$1', ['filter' => 'permission:lfl.delete']);

    /*
     * Checklist documental y dinero del expediente.
     *
     * Salvo `requisito/(:num)` y `honorario/(:num)` —cuyo `(:num)` es el id del
     * EXPEDIENTE, porque se está creando la fila—, el resto recibe el id de la fila y
     * sube hasta el expediente y el lead para autorizar.
     *
     * Subir un documento y registrar un pago son `create`; aprobar, rechazar o eximir
     * un requisito es `edit`, porque cambian el estado de un registro existente.
     * `estado-cuenta` es GET con `view`: es una vista imprimible que no escribe nada.
     */
    $routes->post('expedientes/requisito/(:num)', 'Requirements::store/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('expedientes/requisito/subir/(:num)', 'Requirements::upload/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('expedientes/requisito/estado/(:num)', 'Requirements::status/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('expedientes/requisito/eliminar/(:num)', 'Requirements::delete/$1', ['filter' => 'permission:lfl.delete']);

    $routes->post('expedientes/honorario/(:num)', 'Fees::store/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('expedientes/honorario/eliminar/(:num)', 'Fees::delete/$1', ['filter' => 'permission:lfl.delete']);
    $routes->post('expedientes/pago/(:num)', 'Fees::pay/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('expedientes/pago/eliminar/(:num)', 'Fees::deletePayment/$1', ['filter' => 'permission:lfl.delete']);
    $routes->get('expedientes/estado-cuenta/(:num)', 'Fees::statement/$1', ['filter' => 'permission:lfl.view']);

    // ── Actividades ──────────────────────────────────────────────────────────
    $routes->get('actividades', 'Activities::index', ['filter' => 'permission:lfl.view']);
    $routes->post('actividades/nueva/(:num)', 'Activities::store/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('actividades/completar/(:num)', 'Activities::complete/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('actividades/reabrir/(:num)', 'Activities::reopen/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('actividades/eliminar/(:num)', 'Activities::delete/$1', ['filter' => 'permission:lfl.delete']);

    // ── Agenda de consultas ──────────────────────────────────────────────────
    $routes->get('consultas', 'Appointments::index', ['filter' => 'permission:lfl.view']);
    $routes->post('consultas/nueva/(:num)', 'Appointments::store/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('consultas/estado/(:num)', 'Appointments::status/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('consultas/resultado/(:num)', 'Appointments::outcome/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('consultas/reprogramar/(:num)', 'Appointments::reschedule/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('consultas/eliminar/(:num)', 'Appointments::delete/$1', ['filter' => 'permission:lfl.delete']);

    // ── Documentos preliminares (privados: fuera de public/) ─────────────────
    $routes->post('documentos/subir/(:num)', 'Documents::upload/$1', ['filter' => 'permission:lfl.create']);
    $routes->get('documentos/(:num)', 'Documents::download/$1', ['filter' => 'permission:lfl.view']);
    $routes->post('documentos/autorizar/(:num)', 'Documents::authorize/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('documentos/eliminar/(:num)', 'Documents::delete/$1', ['filter' => 'permission:lfl.delete']);

    // ── Formularios dinámicos ────────────────────────────────────────────────
    $routes->get('formularios', 'Forms::index', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'formularios/nuevo', 'Forms::create', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'formularios/editar/(:num)', 'Forms::edit/$1', ['filter' => 'permission:lfl.configure']);
    $routes->post('formularios/eliminar/(:num)', 'Forms::delete/$1', ['filter' => 'permission:lfl.delete']);

    // ── Marketing: fuentes y campañas ────────────────────────────────────────
    $routes->get('marketing/fuentes', 'Sources::index', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'marketing/fuentes/nuevo', 'Sources::create', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'marketing/fuentes/editar/(:num)', 'Sources::edit/$1', ['filter' => 'permission:lfl.configure']);
    $routes->post('marketing/fuentes/eliminar/(:num)', 'Sources::delete/$1', ['filter' => 'permission:lfl.delete']);
    $routes->get('marketing/campanas', 'Campaigns::index', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'marketing/campanas/nuevo', 'Campaigns::create', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'marketing/campanas/editar/(:num)', 'Campaigns::edit/$1', ['filter' => 'permission:lfl.configure']);
    $routes->post('marketing/campanas/eliminar/(:num)', 'Campaigns::delete/$1', ['filter' => 'permission:lfl.delete']);

    // ── Automatizaciones (§19) ───────────────────────────────────────────────
    // Configurarlas es estructural (`configure`); la bitácora de lo que hizo el
    // sistema es de consulta (`view`), que es lo que mira un supervisor.
    $routes->get('automatizaciones', 'Automations::index', ['filter' => 'permission:lfl.configure']);
    $routes->get('automatizaciones/ejecuciones', 'Automations::runs', ['filter' => 'permission:lfl.view']);
    $routes->match(['get', 'post'], 'automatizaciones/nueva', 'Automations::create', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'automatizaciones/editar/(:num)', 'Automations::edit/$1', ['filter' => 'permission:lfl.configure']);
    $routes->post('automatizaciones/demo', 'Automations::demo', ['filter' => 'permission:lfl.configure']);
    $routes->post('automatizaciones/estado/(:num)', 'Automations::toggle/$1', ['filter' => 'permission:lfl.configure']);
    $routes->post('automatizaciones/eliminar/(:num)', 'Automations::delete/$1', ['filter' => 'permission:lfl.delete']);

    // ── Directorio público: los perfiles de la firma ─────────────────────────
    // Montar el perfil es estructural (`configure`); publicarlo y despublicarlo son
    // cambios de estado (`edit`), y la baja es `delete`.
    // GET lista, POST guarda las funciones comerciales: esta pantalla absorbió
    // «Configuración → Equipo», porque las dos mitades se indexan por (empresa, usuario).
    $routes->match(['get', 'post'], 'directorio', 'Profiles::index', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'directorio/nuevo', 'Profiles::create', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'directorio/editar/(:num)', 'Profiles::edit/$1', ['filter' => 'permission:lfl.configure']);
    $routes->post('directorio/publicar/(:num)', 'Profiles::publish/$1', ['filter' => 'permission:lfl.edit']);
    $routes->post('directorio/eliminar/(:num)', 'Profiles::delete/$1', ['filter' => 'permission:lfl.delete']);
    // La verificación es de la PLATAFORMA: el permiso es el mismo, pero el controlador
    // exige además ser Super Admin, como la app OAuth de Docketwise.
    // Verificación: su propio permiso. Es una afirmación de la plataforma sobre una
    // firma, así que no puede colgar de `lfl.configure`, que tiene cualquier firma.
    /*
     * ── Moderación ───────────────────────────────────────────────────────────
     *
     * Antes eran dos entradas de menú, «Verificación» y «Denuncias», que revisaban el
     * MISMO objeto —el perfil— con el mismo revisor. Para decidir si verificabas a
     * alguien había que acordarse de ir a la otra a ver si estaba denunciado, que es
     * justo el dato que más pesa en esa decisión.
     *
     * El filtro es `lfl.verify`, que hoy tienen Super Admin y Verificador. Dentro, cada
     * acción conserva su propia guarda: verificar pide `Access::canVerify()` y cerrar
     * una denuncia sigue pidiendo Super Admin, porque leer lo que escribió un ciudadano
     * sobre una firma es otra cosa que saber que existe.
     */
    $routes->get('moderacion', 'Profiles::platform', ['filter' => 'permission:lfl.verify']);
    $routes->post('moderacion/verificar/(:num)', 'Profiles::verify/$1', ['filter' => 'permission:lfl.verify']);
    $routes->get('moderacion/denuncias', 'Complaints::index', ['filter' => 'permission:lfl.verify']);
    $routes->post('moderacion/denuncias/cerrar/(:num)', 'Complaints::close/$1', ['filter' => 'permission:lfl.verify']);

    // Las direcciones viejas siguen vivas: hay enlaces guardados y el manual las citaba.
    $routes->get('verificacion', static fn () => redirect()->to(base_url('leadflow/moderacion'), 301),
        ['filter' => 'permission:lfl.verify']);
    $routes->get('denuncias', static fn () => redirect()->to(base_url('leadflow/moderacion/denuncias'), 301),
        ['filter' => 'permission:lfl.verify']);

    // ── Oportunidades abiertas (fase 3) ──────────────────────────────────────
    // Mirar la bandeja es consultar (`view`); **reclamar es dar de alta un lead**, y
    // por eso lleva `create` y no `edit`.
    // Módulo USCIS: consulta interna, consumo y enlaces. Consultar gasta cupo, así
    // que es POST y no algo que pase al cargar la pantalla.
    $routes->get('uscis', 'Uscis::index', ['filter' => 'permission:lfl.view']);
    $routes->post('uscis/consultar', 'Uscis::lookup', ['filter' => 'permission:lfl.view']);
    $routes->get('oportunidades', 'Opportunities::index', ['filter' => 'permission:lfl.view']);
    $routes->post('oportunidades/reclamar/(:num)', 'Opportunities::claim/$1', ['filter' => 'permission:lfl.create']);

    // ── Opiniones verificadas (fase 4) ───────────────────────────────────────
    // Pedir una opinión es dar de alta una invitación (`create`); responder es `edit`.
    // **Moderar es de la plataforma**: el controlador exige además Super Admin, y no
    // existe ninguna ruta para que una firma borre o edite una opinión.
    $routes->get('opiniones', 'Reviews::index', ['filter' => 'permission:lfl.view']);
    $routes->post('opiniones/pedir/(:num)', 'Reviews::invite/$1', ['filter' => 'permission:lfl.create']);
    $routes->post('opiniones/responder/(:num)', 'Reviews::reply/$1', ['filter' => 'permission:lfl.edit']);
    // Datos de ejemplo: solo si la firma no tiene ninguna opinión todavía.
    $routes->post('opiniones/demo', 'Reviews::demo', ['filter' => 'permission:lfl.configure']);
    $routes->get('opiniones/plataforma', 'Reviews::platform', ['filter' => 'permission:lfl.configure']);
    $routes->post('opiniones/moderar/(:num)', 'Reviews::moderate/$1', ['filter' => 'permission:lfl.configure']);

    // ── Configuración ────────────────────────────────────────────────────────
    // El manual es de lectura: `lfl.view`, para el asesor que entra por primera vez.
    $routes->get('configuracion/ayuda', 'Settings::help', ['filter' => 'permission:lfl.view']);
    $routes->match(['get', 'post'], 'configuracion', 'Settings::general', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'configuracion/equipo', 'Settings::team', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'configuracion/etapas', 'Settings::stages', ['filter' => 'permission:lfl.configure']);
    $routes->post('configuracion/etapas/nueva', 'Settings::addStage', ['filter' => 'permission:lfl.configure']);
    $routes->post('configuracion/etapas/eliminar/(:num)', 'Settings::deleteStage/$1', ['filter' => 'permission:lfl.delete']);
    $routes->match(['get', 'post'], 'configuracion/scoring', 'Settings::scoring', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'configuracion/documentos', 'Settings::docTypes', ['filter' => 'permission:lfl.configure']);
    // Conexión con USCIS, por firma: cada una registra su app y gasta su cupo.
    $routes->match(['get', 'post'], 'configuracion/uscis', 'Settings::uscis', ['filter' => 'permission:lfl.configure']);
    $routes->post('configuracion/uscis/probar', 'Settings::uscisCheck', ['filter' => 'permission:lfl.configure']);
    $routes->post('configuracion/uscis/desconectar', 'Settings::uscisDisconnect', ['filter' => 'permission:lfl.configure']);
    $routes->post('configuracion/documentos/eliminar/(:num)', 'Settings::deleteDocType/$1', ['filter' => 'permission:lfl.delete']);
    $routes->get('configuracion/servicios', 'Services::index', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'configuracion/servicios/nuevo', 'Services::create', ['filter' => 'permission:lfl.configure']);
    $routes->match(['get', 'post'], 'configuracion/servicios/editar/(:num)', 'Services::edit/$1', ['filter' => 'permission:lfl.configure']);
    $routes->post('configuracion/servicios/eliminar/(:num)', 'Services::delete/$1', ['filter' => 'permission:lfl.delete']);

    /*
     * La plantilla de requisitos vive DENTRO del editor del servicio, no en una
     * pestaña propia de Configuración: un checklist no significa nada sin el trámite
     * al que pertenece. Rutas POST propias, igual que `configuracion/etapas/nueva`,
     * para que `Services` —que extiende `BaseCrud`— no tenga que tocar su guardado.
     */
    $routes->post('configuracion/servicios/requisitos/(:num)', 'Services::addRequirement/$1', ['filter' => 'permission:lfl.configure']);
    $routes->post('configuracion/servicios/requisitos/eliminar/(:num)', 'Services::deleteRequirement/$1', ['filter' => 'permission:lfl.delete']);

    // ── Docketwise: conexión, sincronización, conversiones y errores ─────────
    $routes->get('docketwise', 'Docketwise::index', ['filter' => 'permission:lfl.view']);
    $routes->post('docketwise/conectar', 'Docketwise::connect', ['filter' => 'permission:lfl.configure']);
    $routes->get('docketwise/simulado/autorizar', 'Docketwise::simAuthorize', ['filter' => 'permission:lfl.configure']);
    $routes->get('docketwise/callback', 'Docketwise::callback', ['filter' => 'permission:lfl.configure']);
    $routes->post('docketwise/validar', 'Docketwise::check', ['filter' => 'permission:lfl.configure']);
    $routes->post('docketwise/plataforma/validar', 'Docketwise::checkPlatform', ['filter' => 'permission:lfl.configure']);
    $routes->post('docketwise/desconectar', 'Docketwise::disconnect', ['filter' => 'permission:lfl.configure']);
    $routes->post('docketwise/contactos', 'Docketwise::syncContacts', ['filter' => 'permission:lfl.convert']);
    $routes->match(['get', 'post'], 'docketwise/plataforma', 'Docketwise::platform', ['filter' => 'permission:lfl.configure']);

    // Conversión Lead → Contact + Matter (idempotente)
    $routes->get('convertir/(:num)', 'Conversions::prepare/$1', ['filter' => 'permission:lfl.convert']);
    $routes->post('convertir/(:num)', 'Conversions::execute/$1', ['filter' => 'permission:lfl.convert']);
    $routes->post('convertir/reintentar/(:num)', 'Conversions::retry/$1', ['filter' => 'permission:lfl.convert']);
});

// ── Público: el formulario que llena el prospecto ────────────────────────────
$routes->group('leadflow-publico', ['namespace' => 'Components\LeadFlow\Controllers'], static function ($routes) {
    $routes->get('f/(:segment)', 'PublicForm::show/$1');
    $routes->post('f/(:segment)', 'PublicForm::submit/$1');
    $routes->get('f/(:segment)/captcha', 'PublicForm::captcha/$1');
});

/*
 * ─── DIRECTORIO PÚBLICO ──────────────────────────────────────────────────────
 *
 * La otra cara del componente: la vitrina donde el visitante encuentra al abogado
 * (fase 1 de `proyectos/plan_directorio_leads_lawyers.md`). Sin `auth` —es la calle—
 * y con CSRF en el único POST que hay, el de la consulta.
 *
 * Vive bajo `/abogados` y `/abogado/{slug}` y **no toca la raíz del sitio**: `/` es
 * del núcleo y las rutas descubiertas de un componente no pueden sobrescribirla.
 * Cuando se decida que la puerta del sitio es el directorio, será un cambio de una
 * línea en `app/Config/Routes.php`, documentado como la excepción que es.
 *
 * Lo que se ve lo decide `Libraries\Directory\PublicDirectory`: perfil publicado y
 * plan de su empresa. Nada más, y en un solo sitio.
 */
/*
 * **El directorio vive en la RAÍZ, no bajo un prefijo**: es la cara pública de la
 * plataforma, y la puerta de un sitio público no puede ser un formulario de acceso.
 *
 * La raíz en sí la cede el núcleo en `app/Config/Routes.php`, porque CI4 no deja que
 * una ruta auto-descubierta pise una del app. Aquí solo cuelgan las suyas, y ninguna
 * choca con el núcleo: `buscar`, `area` y `sitemap.xml` no existen allí.
 */
$routes->get('buscar', 'Directory::find', ['namespace' => 'Components\LeadFlow\Controllers']);
$routes->get('area/(:segment)', 'Directory::area/$1', ['namespace' => 'Components\LeadFlow\Controllers']);
$routes->get('sitemap.xml', 'Directory::sitemap', ['namespace' => 'Components\LeadFlow\Controllers']);
// La imagen de la portada. Va suelta y no bajo un prefijo porque la sirve la
// página de inicio, que ahora ES la raíz.
$routes->get('portada', 'Directory::cover', ['namespace' => 'Components\LeadFlow\Controllers']);

/*
 * ─── CANAL DE DENUNCIA ───────────────────────────────────────────────────────
 *
 * Público a propósito: quien detecta una suplantación casi nunca es cliente de
 * quien la comete. Lo que lo hace seguro es que **no se publica nada**: va a una
 * bandeja que solo ve la plataforma. Con CSRF y las defensas de siempre.
 *
 * `listo` va ANTES que `(:segment)`, o el acuse se leería como el slug de una firma.
 */
$routes->group('reportar', ['namespace' => 'Components\LeadFlow\Controllers'], static function ($routes) {
    $routes->get('listo', 'Directory::reportDone');
    $routes->match(['get', 'post'], '(:segment)', 'Directory::report/$1');
    $routes->get('(:segment)/captcha', 'Directory::reportCaptcha/$1');
});

/*
 * Las direcciones viejas siguen llevando a su sitio.
 *
 * **301 y no 302**: el traslado es definitivo, hay enlaces repartidos y perfiles ya
 * indexados, y un 301 le pasa al destino la autoridad que tenía el origen. Con un 302
 * los buscadores mantendrían indexada la dirección vieja indefinidamente.
 */
$routes->get('abogados', static fn () => redirect()->to(base_url('/'), 301));
$routes->get('abogados/buscar', static fn () => redirect()->to(base_url('buscar'), 301));
$routes->get('abogados/sitemap.xml', static fn () => redirect()->to(base_url('sitemap.xml'), 301));
$routes->get('abogados/area/(:segment)', static fn ($slug) => redirect()->to(base_url('area/' . rawurlencode((string) $slug)), 301));

/*
 * ─── OPINIÓN DEL CLIENTE ─────────────────────────────────────────────────────
 *
 * La rellena quien ya fue cliente, con el token que le pasó su abogado. Sin `auth`, con
 * CSRF y las defensas de siempre, y **sin indexar**: lleva token y datos de una persona.
 */
$routes->group('opinion', ['namespace' => 'Components\LeadFlow\Controllers'], static function ($routes) {
    $routes->match(['get', 'post'], '(:segment)', 'Directory::review/$1');
    $routes->get('(:segment)/gracias', 'Directory::reviewDone/$1');
    $routes->get('(:segment)/captcha', 'Directory::reviewCaptcha/$1');
});

/*
 * ─── ORIENTACIÓN: LA OPORTUNIDAD ABIERTA ─────────────────────────────────────
 *
 * Para quien NO sabe a quién acudir. No va a ninguna firma: crea una oportunidad que
 * reclaman las firmas del área. Sin `auth` y con CSRF, honeypot, sello firmado, captcha
 * y freno por IP, como el resto de los formularios públicos del componente.
 */
$routes->group('orientacion', ['namespace' => 'Components\LeadFlow\Controllers'], static function ($routes) {
    $routes->match(['get', 'post'], '/', 'Directory::orientation');
    $routes->get('listo', 'Directory::orientationDone');
    $routes->get('captcha', 'Directory::orientationCaptcha');
});

/*
 * ─── ALTA PÚBLICA ────────────────────────────────────────────────────────────
 *
 * La otra mitad del marketplace: un abogado o una firma se registran solos. Sin esto,
 * aparecer en el directorio exige que un Super Admin cree la empresa a mano, y eso no
 * pasa de los primeros diez despachos.
 *
 * Sin `auth` —quien se registra no tiene cuenta todavía— y con CSRF, honeypot, sello de
 * tiempo firmado, captcha y freno por IP. Crea usuario, empresa, membresía, suscripción,
 * función comercial y perfil **en una transacción**, y el perfil nace sin publicar.
 *
 * Se llama `registro-legal` y no `registro` porque `/register` es del núcleo: dos puertas
 * de alta con nombres casi iguales se confunden al leer el enrutador.
 */
$routes->group('registro-legal', ['namespace' => 'Components\LeadFlow\Controllers'], static function ($routes) {
    $routes->get('/', 'Signup::form');
    $routes->post('/', 'Signup::submit');
    $routes->get('listo', 'Signup::done');
    $routes->get('captcha', 'Signup::captcha');
});

// El micrositio de la firma. `firmas` y `firma` son dos primeros segmentos distintos,
// pero van juntos para que se lea que uno es la puerta y el otro el destino.
$routes->get('firmas', 'Directory::firms', ['namespace' => 'Components\LeadFlow\Controllers']);
$routes->group('firma', ['namespace' => 'Components\LeadFlow\Controllers'], static function ($routes) {
    $routes->get('(:segment)', 'Directory::firm/$1');
    // Solo POST: el formulario vive dentro de la ficha y el resultado se cuenta allí,
    // así que no hay un GET que servir.
    $routes->post('(:segment)/consulta', 'Directory::firmInquiry/$1');
    $routes->get('(:segment)/captcha', 'Directory::firmCaptcha/$1');
    // Consulta de estado de casos: solo existe si la firma está conectada a USCIS
    // en modo real. El controlador lo comprueba y devuelve 404 si no.
    $routes->match(['get', 'post'], '(:segment)/caso', 'Directory::caseStatus/$1');
    $routes->get('(:segment)/caso/captcha', 'Directory::caseCaptcha/$1');
});

$routes->group('abogado', ['namespace' => 'Components\LeadFlow\Controllers'], static function ($routes) {
    // El listado va antes que la ficha para que se lea que uno es la puerta del otro.
    $routes->get('(:segment)', 'Directory::profile/$1');
    // La tarjeta digital y su contacto descargable. El `.vcf` va con su propia ruta
    // y no como extensión del slug: así el segmento del perfil sigue siendo el slug.
    $routes->get('(:segment)/tarjeta', 'Directory::card/$1');
    $routes->get('(:segment)/tarjeta.vcf', 'Directory::vcf/$1');
    $routes->match(['get', 'post'], '(:segment)/consulta', 'Directory::inquiry/$1');
    $routes->get('(:segment)/consulta/listo', 'Directory::done/$1');
    $routes->get('(:segment)/captcha', 'Directory::captcha/$1');
});
