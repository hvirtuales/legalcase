<?php
/**
 * Una **vista** del directorio: mitad listado, mitad mapa.
 *
 * Autosuficiente (contrato base §7.3 y tenant §7): toda la hoja de estilos va
 * en línea, los iconos son SVG en línea, la tipografía es la del sistema y
 * **Leaflet se sirve desde la propia carpeta del componente** (`/directorio-
 * publico/recurso/…`), no desde un CDN.
 *
 * ⚠️ **La única excepción, y es inevitable:** los *teselados* del mapa —las
 * imágenes del terreno— vienen de `tile.openstreetmap.org`. Un mapa del mundo
 * real necesita imágenes del mundo real, y eso no se puede empaquetar en una
 * carpeta. Está aislado en una sola constante (`TILE_URL`) para poder apuntar a
 * un servidor propio, y **degrada solo**: si los teselados no cargan, el listado
 * —que es HTML del servidor— sigue funcionando entero.
 *
 * @var array $tenant
 * @var array $board
 * @var array $companies  ya resueltas por `BoardService`
 */

/**
 * Color de marca del **grupo** (no el de ninguna de sus empresas). Se valida y
 * se emite **crudo**: `esc($hex,'css')` produce `\23 …` y `esc($hex,'attr')`
 * `&#x23;…`, y ninguno de los dos es un color válido.
 */
$hex = (string) ($tenant['brand_color'] ?? '');
if (!preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
    $hex = '#1f2937';
}
[$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
$ink = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) > 150 ? '#111827' : '#ffffff';

/** Un hex válido, o '' — para el color del sector en la ficha y el marcador. */
$color = static function ($v): string {
    $v = trim((string) $v);

    return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) ? $v : '';
};

/**
 * Lo que necesita el mapa, y **solo eso**.
 *
 * No se vuelca la empresa entera en el JSON: el correo y el teléfono ya están
 * en las fichas, y meterlos otra vez en un bloque de datos los deja a mano de
 * cualquier raspador que ni siquiera tenga que leer el HTML.
 */
$puntos = [];

foreach ($companies as $i => $c) {
    if ($c['latitude'] === null || $c['longitude'] === null) {
        continue;   // sin coordenadas: sale en el listado, no en el mapa
    }
    $puntos[] = [
        'i'   => $i,
        'lat' => (float) $c['latitude'],
        'lng' => (float) $c['longitude'],
        'n'   => (string) $c['name'],
        's'   => (string) ($c['sector_name'] ?? ''),
        'c'   => $color($c['sector_color'] ?? '') ?: $hex,
        'd'   => trim((string) ($c['address'] ?? '')),
        // El logo, para el globo del marcador. Es la misma imagen que ya se
        // ve en la tarjeta: no expone nada nuevo.
        'l'   => !empty($c['logo']) ? media_url($c['logo']) : '',
        // La inicial, para cuando no hay logo.
        'x'   => mb_strtoupper(mb_substr((string) $c['name'], 0, 1)),
    ];
}

/*
 * Qué enseña esta vista: el listado, el mapa, o los dos.
 *
 * `conMapa` exige las dos cosas: que la vista lo pida **y** que haya algún
 * punto que pintar. Sin eso se cargaría Leaflet para enseñar un rectángulo
 * vacío, que es peor que no enseñar mapa.
 */
$layout  = in_array($board['layout'] ?? 'both', ['both', 'list', 'map'], true) ? $board['layout'] : 'both';
/*
 * Los sectores que hay **en esta vista**, no el catálogo entero: ofrecer
 * sectores sin una sola empresa aquí es invitar a un desplegable que solo
 * sirve para vaciar la pantalla.
 */
$sectoresEnVista = [];
foreach ($companies as $c) {
    if (!empty($c['sector_id']) && !empty($c['sector_name'])) {
        $sectoresEnVista[(int) $c['sector_id']] = (string) $c['sector_name'];
    }
}
asort($sectoresEnVista);

$conMapa = $layout !== 'list' && $puntos !== [];
// Si la vista es «solo mapa» pero ninguna empresa tiene ubicación, se cae al
// listado: una página vacía no le sirve a nadie.
$soloMapa = $layout === 'map' && $conMapa;
$soloLista = $layout === 'list' || ($layout === 'map' && !$conMapa);
$base    = rtrim(base_url(), '/');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($board['title']) ?> — <?= esc($tenant['name']) ?></title>
<meta name="description" content="<?= esc(mb_substr(trim((string) ($board['intro'] ?? '')) ?: $tenant['name'], 0, 160), 'attr') ?>">

<?php if ($conMapa): ?>
    <link rel="stylesheet" href="<?= $base ?>/directorio-publico/recurso/leaflet.css">
<?php endif; ?>

<style>
:root {
    --d-brand: <?= $hex ?>;
    --d-ink: <?= $ink ?>;
    --d-bg: #f6f7f9;
    --d-surface: #ffffff;
    --d-text: #111827;
    --d-muted: #6b7280;
    --d-line: #e5e7eb;
    --d-radius: 12px;
    --d-head: 132px;   /* alto de la cabecera fija, lo usa el alto del mapa */
}
@media (prefers-color-scheme: dark) {
    :root {
        --d-bg: #0f1216;
        --d-surface: #171b21;
        --d-text: #e5e7eb;
        --d-muted: #9aa3af;
        --d-line: #2b323c;
    }
}

* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
    background: var(--d-bg);
    color: var(--d-text);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.5;
    -webkit-text-size-adjust: 100%;
}
a { color: inherit; }

/* ------------------------------------------------------------- cabecera */
.d-head {
    background: var(--d-brand);
    color: var(--d-ink);
    padding: 14px 18px;
}
.d-head-in { display: flex; align-items: center; gap: 14px; max-width: 1600px; margin: 0 auto; }
.d-logo { height: 44px; width: auto; max-width: 150px; object-fit: contain; flex: none; }
.d-titles { min-width: 0; flex: 1; }
.d-group { margin: 0; font-size: .8rem; opacity: .85; }
.d-title { margin: 0; font-size: 1.25rem; font-weight: 700; letter-spacing: -.01em; }
.d-intro { margin: 2px 0 0; font-size: .85rem; opacity: .9; }

/* Buscador: arriba del todo, es lo primero que se usa. */
.d-search { max-width: 1600px; margin: 0 auto; padding: 10px 18px; }
.d-search-box { position: relative; }
.d-search input {
    width: 100%;
    padding: 11px 14px 11px 40px;
    border: 1px solid var(--d-line);
    border-radius: 999px;
    background: var(--d-surface);
    color: var(--d-text);
    font: inherit;
    font-size: .95rem;
    /* Sin `appearance:none` iOS pinta su propio marco encima del nuestro. */
    appearance: none;
    -webkit-appearance: none;
}
.d-search input:focus {
    outline: none;
    border-color: var(--d-brand);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--d-brand) 25%, transparent);
}
.d-search svg { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; opacity: .5; }
/* El contador, detrás del título: una pastilla con el número. La línea que
   ocupaba bajo el buscador se recupera para el contenido. */
.d-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.9em;
    padding: 1px 8px;
    margin-left: 6px;
    border-radius: 999px;
    /* Sobre la banda de marca: un velo de la propia tinta, que funciona con
       cualquier color de empresa sin tener que calcular otro. */
    background: color-mix(in srgb, var(--d-ink) 22%, transparent);
    font-size: .78rem;
    font-weight: 700;
    vertical-align: middle;
}
/* `color-mix` no existe en navegadores viejos: sin él la pastilla se quedaría
   sin fondo. Este respaldo va ANTES y lo pisa el de arriba donde se admite. */
@supports not (background: color-mix(in srgb, red 50%, transparent)) {
    .d-count { background: rgba(127, 127, 127, .35); }
}

/* Buscador y filtro, en una fila. */
.d-search-row { display: flex; gap: 8px; align-items: center; }
.d-search-box { flex: 1 1 auto; min-width: 0; }
.d-select {
    flex: 0 0 auto;
    max-width: 45%;
    padding: 11px 34px 11px 14px;
    border: 1px solid var(--d-line);
    border-radius: 999px;
    background: var(--d-surface);
    color: var(--d-text);
    font: inherit;
    font-size: .9rem;
    /* Sin `appearance:none` iOS pinta su propio marco encima del nuestro; con
       él hay que reponer la flecha, que va en línea (nada de imágenes). */
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 15px;
}
.d-select:focus {
    outline: none;
    border-color: var(--d-brand);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--d-brand) 25%, transparent);
}

/* --------------------------------------------------- reparto 50 / 50 --- */
.d-split { display: flex; max-width: 1600px; margin: 0 auto; gap: 0; }
.d-col-list { flex: 1 1 50%; min-width: 0; padding: 4px 18px 24px; }
.d-col-map  { flex: 1 1 50%; min-width: 0; padding: 4px 18px 24px; }

/* El mapa acompaña al desplazamiento del listado: en un directorio se lee la
   lista y se mira dónde cae cada uno, y perder el mapa al bajar rompe eso. */
.d-map-sticky { position: sticky; top: 12px; }
.d-map {
    height: calc(100vh - var(--d-head));
    min-height: 420px;
    border-radius: var(--d-radius);
    border: 1px solid var(--d-line);
    overflow: hidden;
    background: var(--d-surface);
}

/*
 * QUÉ ENSEÑA LA VISTA: listado, mapa o los dos.
 *
 * Lo decide el servidor con una clase en el `<body>`. Con uno solo de los dos
 * paneles, el que queda ocupa el ancho entero — partir la pantalla para dejar
 * media vacía sería lo peor de las dos opciones.
 *
 * En «solo mapa» las tarjetas **se siguen pintando**, escondidas: son las que
 * alimentan el buscador (que filtra marcadores) y las que llevan el detalle
 * que abre el globo. Quitarlas obligaría a un segundo camino para lo mismo.
 */
body.mode-list .d-col-map { display: none; }
body.mode-list .d-col-list { flex: 1 1 100%; }
body.mode-map .d-col-list { display: none; }
body.mode-map .d-col-map { flex: 1 1 100%; }
/* Sin lista al lado, el mapa se queda con toda la ventana. */
body.mode-map .d-map { height: calc(100vh - var(--d-head)); }

/* Con el ancho entero caben más columnas de tarjetas que en media pantalla. */
@media (min-width: 1101px) {
    body.mode-list .d-cards { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
}

/* Dos columnas de tarjetas — también en el teléfono (ver más abajo). */
.d-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }

/*
 * Por debajo de 1100 px el 50/50 deja de tener sentido: media pantalla de mapa
 * en un teléfono son 180 px. En vez de apilar los dos —que obliga a desplazar
 * media pantalla para pasar de uno a otro— se enseña **uno cada vez** y se
 * cambia con el botón flotante. Cada uno se queda con el ancho entero.
 *
 * El interruptor es la clase `is-map` del `<body>`. Vive dentro de la consulta
 * de medios a propósito: en escritorio no significa nada, porque ahí se ven los
 * dos a la vez y no hay nada que alternar.
 */
@media (max-width: 1100px) {
    .d-split { flex-direction: column; }
    .d-col-list, .d-col-map { flex: 1 1 auto; }
    .d-map-sticky { position: static; }
    /* Solo hay un panel a la vista: que ocupe lo que queda de ventana. */
    .d-map { height: calc(100vh - var(--d-head)); min-height: 320px; }

    /* Alternar SOLO tiene sentido cuando la vista lleva los dos paneles. Sin
       acotarlo a `mode-both`, una vista de «solo mapa» —que nunca recibe la
       clase `is-map`— se quedaría con el mapa escondido en el teléfono. */
    body.mode-both:not(.is-map) .d-col-map { display: none; }
    body.mode-both.is-map .d-col-list { display: none; }

    body.mode-both .d-fab { display: inline-flex; }
    /* Sitio para que el botón flotante no tape la última tarjeta. */
    body.mode-both .d-col-list { padding-bottom: 86px; }
}

/* Las tarjetas se quedan en DOS columnas también en el teléfono: en un
   directorio se recorre con la vista, y una sola columna obliga a desplazar el
   triple para ver lo mismo. Lo que se recorta es el relleno, no el reparto. */
@media (max-width: 560px) {
    .d-head-in { gap: 10px; }
    .d-logo { height: 34px; }
    .d-title { font-size: 1.05rem; }
    .d-col-list, .d-col-map { padding-left: 12px; padding-right: 12px; }
    .d-search { padding-left: 12px; padding-right: 12px; }
    .d-cards { gap: 9px; }
    /* Todo un punto más pequeño: con dos columnas en un teléfono, la
       portada y el logo a tamaño de escritorio dejan la tarjeta larguísima. */
    .d-card-logo, .d-card-fallback { width: 46px; height: 46px; border-radius: 10px; }
    .d-card-fallback { font-size: 1.05rem; }
    .d-card-body { padding: 31px 8px 8px; }
    .d-card-name { font-size: .84rem; }
    .d-card-desc { font-size: .75rem; }
    .d-actions { padding: 8px 8px 10px; }
}

/* --------------------------------------------------------- tarjetas --- */
.d-card {
    display: flex;
    flex-direction: column;
    background: var(--d-surface);
    border: 1px solid var(--d-line);
    border-radius: var(--d-radius);
    overflow: hidden;
    transition: box-shadow .15s ease, transform .15s ease;
}
.d-card:hover { box-shadow: 0 6px 20px rgba(15, 23, 42, .1); transform: translateY(-1px); }
.d-card.is-active { border-color: var(--d-brand); box-shadow: 0 0 0 2px color-mix(in srgb, var(--d-brand) 35%, transparent); }
.d-card[hidden] { display: none; }

/*
 * PORTADA a todo el ancho + logo centrado encima.
 *
 * Es el mismo patrón que la tarjeta del componente Networking: la portada da
 * color y contexto, y el logo montado sobre ella es lo que identifica. Sin
 * banner se pinta un degradado con el color del sector, no un gris: con doce
 * sectores, el color es lo que distingue una tarjeta de otra de un vistazo.
 */
.d-cover {
    position: relative;
    width: 100%;
    aspect-ratio: 8 / 3;
    background-size: cover;
    background-position: center;
}
/* El logo sobresale por debajo de la portada, así que el cuerpo deja hueco. */
.d-card-logo, .d-card-fallback {
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translate(-50%, 50%);
    width: 58px; height: 58px;
    border-radius: 12px;
    border: 2px solid var(--d-surface);
    object-fit: contain;
    background: #fff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .16);
    transition: transform .15s ease;
}
.d-card-fallback {
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1.3rem;
    color: #fff;
}
/* El botón que abre el detalle envuelve al logo: hereda su posición. */
.d-open { position: static; }

.d-card-body { padding: 38px 12px 10px; text-align: center; }
.d-card-name {
    margin: 0;
    font-size: .95rem; font-weight: 700; line-height: 1.3;
    /* Un nombre largo no puede empujar la tarjeta a otra altura que sus
       vecinas: se corta en dos líneas. */
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}
.d-sector {
    display: inline-flex; align-items: center; gap: 5px;
    margin-top: 4px;
    font-size: .72rem; font-weight: 600;
    color: var(--d-muted);
}
.d-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
.d-card-desc {
    margin: 6px 0 0;
    font-size: .8rem;
    color: var(--d-muted);
    line-height: 1.4;
}

/* Botones centrados, y pegados abajo para que todas las tarjetas los alineen
   aunque sus nombres ocupen distinto. */
.d-actions {
    display: flex; gap: 6px;
    padding: 10px 12px 12px;
    margin-top: auto;
    flex-wrap: wrap;
    justify-content: center;
}
.d-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 11px;
    border-radius: 999px;
    border: 1px solid var(--d-line);
    font-size: .76rem; font-weight: 600;
    text-decoration: none;
    color: var(--d-text);
    background: var(--d-surface);
}
.d-btn:hover { border-color: var(--d-brand); color: var(--d-brand); }
.d-btn svg { width: 13px; height: 13px; }
.d-btn-main { background: var(--d-brand); color: var(--d-ink); border-color: var(--d-brand); }
.d-btn-main:hover { color: var(--d-ink); opacity: .92; }

/*
 * En el teléfono los botones se quedan **solo con el icono**: con dos columnas
 * de tarjetas no caben tres etiquetas, y partidas en dos renglones desmontan la
 * ficha. El nombre no se pierde —sigue en el `title` y en el `aria-label`—, así
 * que un lector de pantalla lee lo mismo que antes.
 */
.d-btn-t { display: inline; }
@media (max-width: 700px) {
    .d-btn-t { display: none; }
    .d-btn { padding: 8px; border-radius: 50%; }
    .d-btn svg { width: 15px; height: 15px; }
    .d-actions { gap: 8px; }
}

/* ------------------------------------------------------ detalle (popup) */
/* El logo es el disparador: botón de verdad, sin aspecto de botón. */
.d-open {
    padding: 0;
    border: 0;
    background: none;
    cursor: pointer;
    flex: none;
    border-radius: 9px;
    line-height: 0;
}
.d-open:focus-visible { outline: 2px solid var(--d-brand); outline-offset: 2px; }
.d-open:hover .d-card-logo, .d-open:hover .d-card-fallback { transform: translate(-50%, 50%) scale(1.06); }


/* El detalle vive dentro de su tarjeta, escondido, hasta que se abre. */
.d-detail[hidden] { display: none; }

.d-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.d-modal[hidden] { display: none; }
.d-modal-back { position: absolute; inset: 0; background: rgba(10, 14, 22, .55); }
.d-modal-box {
    position: relative;
    width: min(100%, 30rem);
    max-height: min(88vh, 40rem);
    overflow-y: auto;
    padding: 20px;
    border-radius: 16px;
    background: var(--d-surface);
    box-shadow: 0 24px 60px rgba(10, 14, 22, .4);
}
.d-modal-close {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 50%;
    background: var(--d-bg);
    color: var(--d-text);
    cursor: pointer;
}
.d-modal-close svg { width: 17px; height: 17px; }
.d-modal-close:hover { background: var(--d-line); }

.d-det-head { display: flex; gap: 14px; align-items: center; padding-right: 34px; }
.d-det-logo, .d-det-fallback {
    width: 72px; height: 72px; flex: none;
    border-radius: 14px;
    object-fit: contain;
    background: #fff;
    border: 1px solid var(--d-line);
}
.d-det-fallback {
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 1.7rem;
    border: 0;
}
.d-det-name { margin: 0; font-size: 1.15rem; font-weight: 700; line-height: 1.25; }
.d-det-desc { margin: 14px 0 0; font-size: .9rem; color: var(--d-muted); }

.d-det-rows { margin-top: 14px; display: flex; flex-direction: column; gap: 2px; }
.d-det-row {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 9px 10px;
    border-radius: 9px;
    font-size: .86rem;
    color: var(--d-text);
    text-decoration: none;
}
a.d-det-row:hover { background: var(--d-bg); color: var(--d-brand); }
.d-det-row svg { width: 15px; height: 15px; flex: none; margin-top: 3px; opacity: .65; }
.d-det-row span { min-width: 0; overflow-wrap: anywhere; }

/* Las acciones del popup: aquí SÍ con su etiqueta, hay sitio de sobra. */
.d-det-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
.d-det-actions .d-btn { padding: 9px 14px; border-radius: 999px; font-size: .82rem; }
.d-det-actions .d-btn-t { display: inline !important; }
.d-det-actions .d-btn svg { width: 14px; height: 14px; }

/* --------------------------------------------------- botón flotante --- */
/*
 * Cambia entre el listado y el mapa. Solo existe por debajo de 1100 px: arriba
 * se ven los dos a la vez y no hay nada que alternar.
 *
 * Abajo a la IZQUIERDA: a la derecha es donde cae el pulgar cuando se desplaza
 * la lista, y ahí el botón se pulsa sin querer. `env(safe-area-inset-*)` para
 * que no quede debajo de la barra de gestos de un iPhone.
 */
.d-fab {
    display: none;   /* la consulta de medios lo enciende */
    position: fixed;
    left: calc(16px + env(safe-area-inset-left, 0px));
    bottom: calc(16px + env(safe-area-inset-bottom, 0px));
    z-index: 900;
    align-items: center;
    gap: 8px;
    padding: 13px 18px;
    border: 0;
    border-radius: 999px;
    background: var(--d-brand);
    color: var(--d-ink);
    font: inherit;
    font-size: .88rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(0, 0, 0, .28);
}
.d-fab:active { transform: translateY(1px); }
.d-fab svg { width: 18px; height: 18px; }
/* Se enseña la etiqueta del destino, no la del sitio donde ya se está. */
.d-fab .d-fab-map, .d-fab .d-fab-list { display: none; }
body:not(.is-map) .d-fab .d-fab-map { display: inline-flex; align-items: center; gap: 8px; }
body.is-map .d-fab .d-fab-list { display: inline-flex; align-items: center; gap: 8px; }

.d-empty { padding: 28px 12px; text-align: center; color: var(--d-muted); font-size: .9rem; }
.d-nomap { padding: 20px; text-align: center; color: var(--d-muted); font-size: .85rem; }

/* ------------------------------------------------------------ marcador */
/* El marcador se dibuja con CSS y toma el color del SECTOR: es lo que hace
   legible un mapa con locales de tipos distintos. */
.d-pin {
    width: 22px; height: 22px;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    border: 2px solid #fff;
    box-shadow: 0 1px 4px rgba(0, 0, 0, .4);
}
.d-pin.is-active { width: 28px; height: 28px; z-index: 500; }

.leaflet-popup-content { margin: 10px 12px; font-family: inherit; }
/* El globo del marcador enseña el logo, igual que la tarjeta: es lo que hace
   reconocible el punto sin tener que leer. */
.d-pop { display: flex; gap: 10px; align-items: center; }
.d-pop-logo {
    width: 40px; height: 40px; flex: none;
    border-radius: 9px;
    object-fit: contain;
    background: #fff;
    border: 1px solid #e5e7eb;
}
.d-pop-ini {
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 1.05rem;
    border: 0;
}
.d-pop-txt { display: flex; flex-direction: column; min-width: 0; }
/* El botón que abre el detalle desde el globo. Ocupa el ancho para que sea
   fácil de acertar con el pulgar, que es donde se usa un mapa. */
.d-pop-btn {
    display: block;
    width: 100%;
    margin-top: 10px;
    padding: 8px 12px;
    border: 0;
    border-radius: 999px;
    background: var(--d-brand);
    color: var(--d-ink);
    font: inherit;
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
}
.d-pop-btn:active { transform: translateY(1px); }
.d-pop-name { font-weight: 700; font-size: .9rem; line-height: 1.25; }
.d-pop-sector { font-size: .75rem; color: #6b7280; }

/* ------------------------------------------------------------- pie --- */
.d-foot {
    max-width: 1600px; margin: 0 auto;
    padding: 16px 18px 28px;
    font-size: .78rem; color: var(--d-muted);
    text-align: center;
}
</style>
</head>
<body class="<?= $soloMapa ? 'mode-map' : ($soloLista ? 'mode-list' : 'mode-both') ?>">

<?php
// Iconos SVG en línea: nada de fuentes de iconos ni peticiones a terceros.
$svg = [
    'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
    'pin'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>',
    'phone'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92"/></svg>',
    'mail'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
    'x'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    'globe'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10"/></svg>',
    'list'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>',
    'map'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/></svg>',
    'wa'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.2-.8 1-.9 1.2-.3.2-.6.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2c-.2-.3 0-.5.1-.6l.5-.5.3-.5v-.5l-.9-2.2c-.3-.6-.5-.5-.7-.5h-.6a1.1 1.1 0 0 0-.8.4A3.3 3.3 0 0 0 6 9c0 1.5 1.1 2.9 1.2 3.1a12 12 0 0 0 5.1 4.5 8 8 0 0 0 1.7.6 4 4 0 0 0 1.9.1 3 3 0 0 0 2-1.4 2.5 2.5 0 0 0 .2-1.4zM12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2m0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3.1.8.8-3-.2-.3A8.2 8.2 0 1 1 12 20.2"/></svg>',
];
?>

<header class="d-head">
    <div class="d-head-in">
        <?php if (!empty($tenant['logo'])): ?>
            <img class="d-logo" src="<?= esc(media_url($tenant['logo'])) ?>" alt="<?= esc($tenant['name'], 'attr') ?>">
        <?php endif; ?>
        <div class="d-titles">
            <p class="d-group"><?= esc($tenant['name']) ?></p>
            <?php
            /*
             * El contador va **detrás del título**, no en una línea propia bajo
             * el buscador: ahí ocupaba un renglón entero de la pantalla para
             * decir un número. El texto completo queda en el `title`.
             */
            ?>
            <h1 class="d-title">
                <?= esc($board['title']) ?>
                <span class="d-count" id="dCount"
                    title="<?= esc(lang('Dir.countAll', [count($companies)])) ?>"><?= count($companies) ?></span>
            </h1>
            <?php if (!empty($board['intro'])): ?>
                <p class="d-intro"><?= esc($board['intro']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="d-search">
    <div class="d-search-row">
        <div class="d-search-box">
            <?= $svg['search'] ?>
            <input type="search" id="dSearch" autocomplete="off"
                placeholder="<?= esc(lang('Dir.searchCompanies')) ?>"
                aria-label="<?= esc(lang('Dir.searchCompanies')) ?>">
        </div>
        <?php // Solo si hay más de un sector: con uno, el desplegable no filtra nada. ?>
        <?php if (count($sectoresEnVista) > 1): ?>
            <select id="dSector" class="d-select" aria-label="<?= esc(lang('Dir.filterBySector')) ?>">
                <option value=""><?= lang('Dir.allSectors') ?></option>
                <?php foreach ($sectoresEnVista as $sid => $sname): ?>
                    <option value="<?= (int) $sid ?>"><?= esc($sname) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
</div>

<div class="d-split">
    <!-- Listado: dos columnas de tarjetas dentro de su mitad -->
    <div class="d-col-list">
        <?php if (!$companies): ?>
            <p class="d-empty"><?= lang('Dir.boardEmptyPublic') ?></p>
        <?php else: ?>
            <div class="d-cards" id="dCards">
                <?php foreach ($companies as $i => $c): ?>
                    <?php
                    $sc      = $color($c['sector_color'] ?? '') ?: $hex;
                    $tienePos = $c['latitude'] !== null && $c['longitude'] !== null;
                    // Lo que busca el buscador. Se compone en el servidor y va
                    // ya sin acentos: comparar en el navegador con `localeCompare`
                    // por cada tecla es lento con cientos de fichas.
                    $busca = mb_strtolower(trim(implode(' ', array_filter([
                        $c['name'], $c['sector_name'] ?? '', $c['city'] ?? '',
                        $c['address'] ?? '', $c['description'] ?? '',
                    ]))));
                    $busca = strtr($busca, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
                    ?>
                    <article class="d-card" data-i="<?= (int) $i ?>" data-s="<?= esc($busca, 'attr') ?>"
                        data-sec="<?= (int) ($c['sector_id'] ?? 0) ?>"
                        <?= $tienePos ? 'data-pos="1"' : '' ?>>
                        <?php
                        $donde = trim(implode(', ', array_filter([$c['address'] ?? '', $c['city'] ?? ''])));

                        /*
                         * Descripción **corta**: 50 caracteres.
                         *
                         * Se corta en el último espacio antes del tope, no a la
                         * brava: partir una palabra por la mitad se lee como un
                         * error de la página, no como un resumen. Se cuenta con
                         * `mb_*` porque una eñe o una tilde son un carácter, no
                         * dos, y con `strlen` el corte caería a mitad de letra.
                         */
                        $corta = trim((string) ($c['description'] ?? ''));
                        if (mb_strlen($corta) > 50) {
                            $trozo = mb_substr($corta, 0, 50);
                            $esp   = mb_strrpos($trozo, ' ');
                            $corta = rtrim($esp !== false && $esp > 25 ? mb_substr($trozo, 0, $esp) : $trozo, " ,.;:") . '…';
                        }

                        /*
                         * Portada a todo el ancho. Sin banner, un degradado con el
                         * color de su sector: con doce sectores el color es lo que
                         * distingue una tarjeta de otra de un vistazo, y un gris
                         * plano las haría todas iguales.
                         */
                        $cover = !empty($c['banner'])
                            ? 'background-image:url(&quot;' . esc(media_url($c['banner'])) . '&quot;)'
                            : 'background-image:linear-gradient(135deg,' . $sc . ',' . $sc . '99)';
                        ?>
                        <div class="d-cover" style="<?= $cover ?>">
                            <?php
                            /*
                             * El logo es un BOTÓN, no una imagen suelta: abre el
                             * detalle, así que tiene que ser pulsable con el
                             * teclado y anunciarse como lo que hace. Una imagen
                             * con un `onclick` no la alcanza quien navega con
                             * tabulador.
                             */
                            ?>
                            <button type="button" class="d-open" data-open="<?= (int) $i ?>"
                                title="<?= esc(lang('Dir.seeDetail')) ?>"
                                aria-label="<?= esc(lang('Dir.seeDetailOf', [$c['name']])) ?>">
                                <?php if (!empty($c['logo'])): ?>
                                    <img class="d-card-logo" src="<?= esc(media_url($c['logo'])) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <?php // Sin logo, la inicial sobre el color de su sector. ?>
                                    <div class="d-card-fallback" style="background-color:<?= $sc ?>">
                                        <?= esc(mb_strtoupper(mb_substr((string) $c['name'], 0, 1))) ?>
                                    </div>
                                <?php endif; ?>
                            </button>
                        </div>

                        <div class="d-card-body">
                            <h2 class="d-card-name"><?= esc($c['name']) ?></h2>
                            <?php if (!empty($c['sector_name'])): ?>
                                <span class="d-sector">
                                    <span class="d-dot" style="background-color:<?= $sc ?>"></span>
                                    <?= esc($c['sector_name']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($corta !== ''): ?>
                                <p class="d-card-desc"><?= esc($corta) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php
                        /*
                         * El detalle, ya montado por el servidor y escondido: al
                         * abrir el popup se **mueve** este bloque dentro y al
                         * cerrar se devuelve a su tarjeta.
                         *
                         * Se hace así y no con un JSON aparte por dos motivos: no
                         * hay que volver a escapar nada en el navegador, y el
                         * contenido ya está en el HTML aunque el JavaScript no
                         * llegue a ejecutarse.
                         */
                        ?>
                        <div class="d-detail" id="dDet<?= (int) $i ?>" hidden>
                            <div class="d-det-head">
                                <?php if (!empty($c['logo'])): ?>
                                    <img class="d-det-logo" src="<?= esc(media_url($c['logo'])) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="d-det-fallback" style="background-color:<?= $sc ?>">
                                        <?= esc(mb_strtoupper(mb_substr((string) $c['name'], 0, 1))) ?>
                                    </div>
                                <?php endif; ?>
                                <div style="min-width:0">
                                    <h3 class="d-det-name"><?= esc($c['name']) ?></h3>
                                    <?php if (!empty($c['sector_name'])): ?>
                                        <span class="d-sector">
                                            <span class="d-dot" style="background-color:<?= $sc ?>"></span>
                                            <?= esc($c['sector_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($c['description'])): ?>
                                <p class="d-det-desc"><?= nl2br(esc($c['description'])) ?></p>
                            <?php endif; ?>

                            <div class="d-det-rows">
                                <?php if ($donde !== ''): ?>
                                    <div class="d-det-row"><?= $svg['pin'] ?><span><?= esc($donde) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($c['contact_phone'])): ?>
                                    <a class="d-det-row" href="tel:<?= esc(preg_replace('/\s+/', '', (string) $c['contact_phone'])) ?>">
                                        <?= $svg['phone'] ?><span><?= esc($c['contact_phone']) ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($c['contact_email'])): ?>
                                    <a class="d-det-row" href="mailto:<?= esc($c['contact_email']) ?>">
                                        <?= $svg['mail'] ?><span><?= esc($c['contact_email']) ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($c['website'])): ?>
                                    <a class="d-det-row" href="<?= esc($c['website']) ?>" target="_blank" rel="noopener noreferrer nofollow">
                                        <?= $svg['globe'] ?><span><?= esc(preg_replace('#^https?://(www\.)?#', '', (string) $c['website'])) ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php
                        /*
                         * Cada botón lleva su nombre en `title` **y** en
                         * `aria-label`: en el teléfono la etiqueta visible se
                         * esconde y solo queda el icono, así que sin esto el
                         * botón se quedaría sin nombre accesible.
                         */
                        ?>
                        <div class="d-actions">
                            <?php if ($tienePos): ?>
                                <?php // No es un enlace: mueve el mapa, no navega. ?>
                                <button type="button" class="d-btn d-btn-main" data-goto="<?= (int) $i ?>"
                                    title="<?= esc(lang('Dir.showOnMap')) ?>"
                                    aria-label="<?= esc(lang('Dir.showOnMap')) ?>">
                                    <?= $svg['pin'] ?><span class="d-btn-t"> <?= lang('Dir.showOnMap') ?></span>
                                </button>
                            <?php endif; ?>
                            <?php if (!empty($c['website'])): ?>
                                <a class="d-btn" href="<?= esc($c['website']) ?>" target="_blank" rel="noopener noreferrer nofollow"
                                    title="<?= esc(lang('Dir.website')) ?>"
                                    aria-label="<?= esc(lang('Dir.website')) ?>">
                                    <?= $svg['globe'] ?><span class="d-btn-t"> <?= lang('Dir.website') ?></span>
                                </a>
                            <?php endif; ?>
                            <?php
                            $wa = preg_replace('/\D+/', '', (string) ($c['whatsapp'] ?? '')) ?: '';
                            ?>
                            <?php if (strlen($wa) >= 8 && strlen($wa) <= 15): ?>
                                <a class="d-btn" href="https://wa.me/<?= esc($wa) ?>" target="_blank" rel="noopener noreferrer"
                                    title="WhatsApp" aria-label="WhatsApp">
                                    <?= $svg['wa'] ?><span class="d-btn-t"> WhatsApp</span>
                                </a>
                            <?php elseif (!empty($c['contact_phone'])): ?>
                                <a class="d-btn" href="tel:<?= esc(preg_replace('/\s+/', '', (string) $c['contact_phone'])) ?>"
                                    title="<?= esc(lang('Dir.call')) ?>"
                                    aria-label="<?= esc(lang('Dir.call')) ?>">
                                    <?= $svg['phone'] ?><span class="d-btn-t"> <?= lang('Dir.call') ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="d-empty" id="dNoResults" hidden><?= lang('Dir.noResults') ?></p>
        <?php endif; ?>
    </div>

    <!-- Mapa -->
    <div class="d-col-map">
        <div class="d-map-sticky">
            <div class="d-map" id="dMap">
                <?php if (!$conMapa): ?>
                    <p class="d-nomap"><?= lang('Dir.noCoordinates') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
/*
 * Detalle de una empresa.
 *
 * Un solo diálogo para toda la página: al abrirlo se **mueve** dentro el
 * bloque `.d-detail` de la tarjeta y al cerrarlo se devuelve a su sitio. Así
 * no hay que duplicar el contenido ni volver a escaparlo en el navegador, y
 * el detalle sigue en el HTML aunque el guion no llegue a ejecutarse.
 */
?>
<div class="d-modal" id="dModal" hidden>
    <div class="d-modal-back" data-close-modal></div>
    <div class="d-modal-box" role="dialog" aria-modal="true" aria-labelledby="dModalTitle">
        <button type="button" class="d-modal-close" data-close-modal
            title="<?= esc(lang('Dir.close')) ?>" aria-label="<?= esc(lang('Dir.close')) ?>">
            <?= $svg['x'] ?>
        </button>
        <div id="dModalBody"></div>
        <div class="d-det-actions" id="dModalActions"></div>
    </div>
</div>

<?php
/*
 * Cambia entre el listado y el mapa. Solo se ve por debajo de 1100 px (lo
 * decide el CSS): arriba se ven los dos a la vez y no habría nada que
 * alternar. Se pinta únicamente si hay mapa que enseñar.
 */
?>
<?php if ($conMapa && !$soloMapa): ?>
    <button type="button" class="d-fab" id="dToggle" aria-pressed="false">
        <span class="d-fab-map"><?= $svg['map'] ?> <?= lang('Dir.seeMap') ?></span>
        <span class="d-fab-list"><?= $svg['list'] ?> <?= lang('Dir.seeList') ?></span>
    </button>
<?php endif; ?>

<footer class="d-foot">
    <?= esc($tenant['name']) ?>
    <?php if (!empty($tenant['city'])): ?> · <?= esc($tenant['city']) ?><?php endif; ?>
</footer>

<?php if ($conMapa): ?>
<script type="application/json" id="dPoints"><?= json_encode($puntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= $base ?>/directorio-publico/recurso/leaflet.js"></script>
<?php endif; ?>

<script>
(function () {
    'use strict';

    var cards = Array.prototype.slice.call(document.querySelectorAll('.d-card'));
    var input = document.getElementById('dSearch');
    var selSector = document.getElementById('dSector');
    var count = document.getElementById('dCount');
    var vacio = document.getElementById('dNoResults');
    var marcadores = {};
    var POP_DETALLE = <?= json_encode(lang('Dir.seeDetail')) ?>;
    var mapa = null;
    var activo = null;

    // ------------------------------------------------------------- mapa
    var puntos = [];
    var raw = document.getElementById('dPoints');
    if (raw) { try { puntos = JSON.parse(raw.textContent) || []; } catch (e) { puntos = []; } }

    if (window.L && puntos.length) {
        // Leaflet compone la ruta de sus imágenes a partir de aquí; sin esto
        // las pediría a `/images/marker-icon.png`, que en esta app no existe.
        L.Icon.Default.imagePath = <?= json_encode($base . '/directorio-publico/recurso/images/') ?>;

        mapa = L.map('dMap', { scrollWheelZoom: false });

        // ⚠️ ÚNICA petición a un tercero de toda la página: los teselados. Un
        // mapa del mundo real necesita imágenes del mundo real. Cambiar esta
        // constante por un servidor propio es todo lo que hace falta para que
        // la página no hable con nadie de fuera.
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
        }).addTo(mapa);

        var grupo = [];
        puntos.forEach(function (p) {
            var icono = L.divIcon({
                className: '',
                html: '<div class="d-pin" style="background-color:' + p.c + '"></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 22],
                popupAnchor: [0, -20]
            });
            var m = L.marker([p.lat, p.lng], { icon: icono, title: p.n }).addTo(mapa);
            m.bindPopup(
                '<div class="d-pop">'
                + (p.l
                    ? '<img class="d-pop-logo" src="' + esc(p.l) + '" alt="">'
                    : '<span class="d-pop-logo d-pop-ini" style="background-color:' + p.c + '">' + esc(p.x) + '</span>')
                + '<span class="d-pop-txt">'
                + '<span class="d-pop-name">' + esc(p.n) + '</span>'
                + (p.s ? '<span class="d-pop-sector">' + esc(p.s) + '</span>' : '')
                + (p.d ? '<span class="d-pop-sector">' + esc(p.d) + '</span>' : '')
                + '</span></div>'
                /*
                 * Botón al detalle. Lo recoge el mismo manejador delegado que
                 * el logo de la tarjeta (`[data-open]`) — funciona desde dentro
                 * del globo porque Leaflet detiene `mousedown/touchstart/
                 * dblclick/contextmenu` en sus popups, pero **no `click`**, que
                 * es el que sube hasta `document`.
                 */
                + '<button type="button" class="d-pop-btn" data-open="' + p.i + '">'
                + '' + POP_DETALLE + '</button>'
            );
            m.on('click', function () { destaca(p.i, false); });
            marcadores[p.i] = m;
            grupo.push([p.lat, p.lng]);
        });

        encuadra(grupo);
    }

    /** Encuadre inicial: el que se haya fijado, o el que abarque los puntos. */
    function encuadra(coords) {
        if (!mapa || !coords.length) { return; }
        var lat = <?= $board['center_lat'] !== null ? (float) $board['center_lat'] : 'null' ?>;
        var lng = <?= $board['center_lng'] !== null ? (float) $board['center_lng'] : 'null' ?>;
        var z   = <?= $board['zoom'] !== null ? (int) $board['zoom'] : 'null' ?>;

        if (lat !== null && lng !== null) {
            mapa.setView([lat, lng], z || 14);
            return;
        }
        if (coords.length === 1) {
            // `fitBounds` con un solo punto se va al zoom máximo y se ve el
            // tejado: con uno solo, un zoom de barrio es más útil.
            mapa.setView(coords[0], z || 15);
            return;
        }
        mapa.fitBounds(coords, { padding: [40, 40] });
        if (z) { mapa.setZoom(z); }
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // --------------------------------------------------- lista ↔ mapa
    /** Destaca una ficha y su marcador. `mover` centra el mapa en él. */
    function destaca(i, mover) {
        if (activo !== null) {
            var antes = document.querySelector('.d-card[data-i="' + activo + '"]');
            if (antes) { antes.classList.remove('is-active'); }
            if (marcadores[activo]) {
                var el = marcadores[activo].getElement();
                if (el && el.firstChild) { el.firstChild.classList.remove('is-active'); }
            }
        }
        activo = i;

        var card = document.querySelector('.d-card[data-i="' + i + '"]');
        if (card) {
            card.classList.add('is-active');
            if (!mover) { card.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
        }
        var m = marcadores[i];
        if (m) {
            var el2 = m.getElement();
            if (el2 && el2.firstChild) { el2.firstChild.classList.add('is-active'); }
            if (mover && mapa) {
                mapa.setView(m.getLatLng(), Math.max(mapa.getZoom(), 16), { animate: true });
                m.openPopup();
            }
        }
    }

    // ---------------------------------------------------------- buscador
    /** Sin acentos y en minúsculas, igual que el `data-s` del servidor. */
    function normaliza(s) {
        return String(s).toLowerCase()
            .replace(/[áàäâã]/g, 'a').replace(/[éèëê]/g, 'e').replace(/[íìïî]/g, 'i')
            .replace(/[óòöôõ]/g, 'o').replace(/[úùüû]/g, 'u').replace(/ñ/g, 'n');
    }

    function filtra() {
        var q = normaliza(input ? input.value.trim() : '');
        var sec = selSector ? selSector.value : '';
        var n = 0;

        cards.forEach(function (card) {
            // Las dos condiciones se suman: el desplegable acota y el texto
            // busca dentro de lo acotado, que es lo que se espera de los dos
            // controles juntos.
            var ok = (q === '' || card.dataset.s.indexOf(q) !== -1)
                && (sec === '' || card.dataset.sec === sec);
            card.hidden = !ok;
            if (ok) { n++; }

            // El mapa enseña lo mismo que la lista: si un marcador se quedara
            // al filtrar, señalaría a alguien que ya no está en el listado.
            var m = marcadores[card.dataset.i];
            if (m && mapa) {
                if (ok && !mapa.hasLayer(m)) { m.addTo(mapa); }
                if (!ok && mapa.hasLayer(m)) { mapa.removeLayer(m); }
            }
        });

        if (vacio) { vacio.hidden = n !== 0; }
        if (count) {
            // En la pastilla solo cabe el número; el texto entero va al `title`.
            count.textContent = n;
            count.title = (n === cards.length)
                ? <?= json_encode(lang('Dir.countAll')) ?>.replace('{0}', cards.length)
                : <?= json_encode(lang('Dir.countFiltered')) ?>.replace('{0}', n).replace('{1}', cards.length);
        }
    }

    if (input) { input.addEventListener('input', filtra); }
    if (selSector) { selSector.addEventListener('change', filtra); }
    filtra();


    // ------------------------------------------------- detalle (popup)
    /*
     * Al abrir se **mueve** el `.d-detail` de la tarjeta dentro del diálogo, y
     * al cerrar se devuelve. Mover en vez de clonar evita tener el mismo
     * contenido dos veces en el DOM —con sus ids repetidos— y hace que cerrar
     * deje la página exactamente como estaba.
     */
    var modal   = document.getElementById('dModal');
    var mBody   = document.getElementById('dModalBody');
    var mActs   = document.getElementById('dModalActions');
    var prestado = null;   // el detalle que está ahora en el diálogo
    var volverA  = null;   // su tarjeta, para devolverlo
    var antesFoco = null;  // a dónde vuelve el foco al cerrar

    function abreDetalle(i) {
        var card = document.querySelector('.d-card[data-i="' + i + '"]');
        var det  = card ? card.querySelector('.d-detail') : null;
        if (!card || !det || !modal) { return; }

        cierraDetalle();                       // por si había otro abierto
        antesFoco = document.activeElement;

        volverA  = card;
        prestado = det;
        det.hidden = false;
        mBody.appendChild(det);

        // Las acciones se copian de la tarjeta: son las mismas, pero aquí hay
        // sitio para que lleven su etiqueta.
        mActs.innerHTML = '';
        var acciones = card.querySelector('.d-actions');
        if (acciones) {
            Array.prototype.forEach.call(acciones.children, function (b) {
                var copia = b.cloneNode(true);
                // El «ver en el mapa» del popup tiene que cerrarlo primero.
                if (copia.dataset && copia.dataset.goto !== undefined) {
                    copia.dataset.fromModal = '1';
                }
                mActs.appendChild(copia);
            });
        }

        modal.hidden = false;
        // Se bloquea el desplazamiento del fondo: si no, en el teléfono se
        // arrastra la página de debajo mientras se lee el detalle.
        document.body.style.overflow = 'hidden';
        var cerrar = modal.querySelector('.d-modal-close');
        if (cerrar) { cerrar.focus(); }
    }

    function cierraDetalle() {
        if (!modal || modal.hidden) { return; }
        modal.hidden = true;
        document.body.style.overflow = '';

        if (prestado && volverA) {
            prestado.hidden = true;
            volverA.appendChild(prestado);   // vuelve a su tarjeta
        }
        prestado = null;
        volverA  = null;
        mActs.innerHTML = '';

        if (antesFoco && antesFoco.focus) { antesFoco.focus(); }
        antesFoco = null;
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest) { return; }

        var abre = e.target.closest('[data-open]');
        if (abre) {
            abreDetalle(parseInt(abre.dataset.open, 10));
            return;
        }
        if (e.target.closest('[data-close-modal]')) { cierraDetalle(); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { cierraDetalle(); }
    });

    // ------------------------------------------- listado ⟷ mapa (móvil)
    /*
     * El botón flotante alterna una clase en el `<body>`; qué se enseña y qué
     * se esconde lo decide el CSS, y solo por debajo de 1100 px. Aquí no se
     * mira el ancho: si se hiciera, habría dos definiciones de «móvil» —una en
     * la hoja y otra en el guion— y un día dejarían de coincidir.
     */
    var fab = document.getElementById('dToggle');

    /**
     * Enseña el mapa (en móvil) y lo encuadra.
     *
     * `foco` es el índice de una empresa concreta, o `null` para encuadrar
     * sobre todas las que el buscador haya dejado a la vista.
     *
     * Las dos cosas van JUNTAS aquí a propósito. Antes el botón de la ficha
     * hacía tres llamadas seguidas —centrar, cambiar de panel (que reencuadraba
     * sobre todas) y volver a centrar—, con dos animaciones de Leaflet
     * compitiendo sobre un contenedor recién medido. El resultado era que el
     * botón «a veces no hacía nada»: el encuadre general se comía el centrado.
     * Ahora se decide **un solo** destino antes de mover el mapa.
     */
    function muestraMapa(foco) {
        if (!document.body.classList.contains('is-map')) {
            document.body.classList.add('is-map');
            if (fab) { fab.setAttribute('aria-pressed', 'true'); }
        }
        if (!mapa) { return; }

        /*
         * **Leaflet mide su contenedor al crearlo.** Si el mapa nació escondido
         * —que es lo que pasa en el teléfono, donde se arranca en el listado—
         * cree que mide 0 y al enseñarlo aparece gris, con los teselados a
         * medio poner. `invalidateSize()` le hace volver a medir; se llama
         * siempre, que es barato, en vez de llevar la cuenta de si ya se hizo.
         */
        mapa.invalidateSize();

        if (foco !== null && marcadores[foco]) {
            destaca(foco, true);
            return;
        }

        // Sin foco: se encuadra sobre lo que esté a la vista. Enseñar el mapa
        // centrado en marcadores que el buscador acaba de filtrar sería enseñar
        // algo distinto de lo que dice la lista.
        var vivos = [];
        cards.forEach(function (card) {
            var m = marcadores[card.dataset.i];
            if (!card.hidden && m) { vivos.push(m.getLatLng()); }
        });
        if (vivos.length > 1) {
            mapa.fitBounds(vivos, { padding: [40, 40] });
        } else if (vivos.length === 1) {
            mapa.setView(vivos[0], Math.max(mapa.getZoom(), 15));
        }
    }

    function muestraLista() {
        document.body.classList.remove('is-map');
        if (fab) { fab.setAttribute('aria-pressed', 'false'); }
        // Se sube: se venía de mirar el mapa, no de donde se estaba leyendo.
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    if (fab) {
        fab.addEventListener('click', function () {
            if (document.body.classList.contains('is-map')) {
                muestraLista();
            } else {
                muestraMapa(null);
            }
        });
    }

    /**
     * «Ver en el mapa» de una ficha — **un solo** manejador para todos.
     *
     * Antes había dos escuchando el mismo `[data-goto]` y se pisaban. Aquí se
     * decide una vez qué hacer según dónde se esté: en el teléfono hay que ir
     * al mapa además de moverlo (si no, el botón parece que no hace nada); en
     * escritorio el mapa ya está al lado y solo hay que centrarlo.
     */
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-goto]') : null;
        if (!b) { return; }

        var i = parseInt(b.dataset.goto, 10);

        // Desde el popup: cerrarlo antes, o el mapa se movería detrás de él.
        if (b.dataset.fromModal) { cierraDetalle(); }

        // ¿Estamos en la vista de un panel cada vez? Lo dice el propio botón
        // flotante: si el CSS lo tiene a la vista, es que hay que cambiar de
        // panel. Preguntárselo al CSS evita tener dos definiciones de «móvil»
        // —una en la hoja y otra aquí— que un día dejarían de coincidir.
        var enMovil = fab && getComputedStyle(fab).display !== 'none';

        if (enMovil) {
            muestraMapa(i);
        } else {
            destaca(i, true);
        }
    });
}());

</script>
</body>
</html>
