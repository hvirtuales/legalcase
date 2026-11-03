<?php
/**
 * Hoja de estilos del contenido renderizado (clases `pg-*`).
 *
 * **Un solo archivo para las dos superficies**: la página pública lo incrusta en
 * su `<style>` (autosuficiente, §7.3 — sin `tailwind.css` ni CDN) y el editor lo
 * incrusta también dentro de su lienzo. Así lo que se ve mientras se arma la
 * página es lo que se publica, sin dos hojas que mantener sincronizadas a mano.
 *
 * No lleva la etiqueta `<style>`: la pone quien lo incluye.
 *
 * @var array $settings Ajustes de la página: accent, width.
 */
$accent = '';
if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) ($settings['accent'] ?? ''))) {
    // Crudo a propósito: `esc($hex,'css')` produce `\23 …`, que no es un color
    // válido (contrato §7.3).
    $accent = (string) $settings['accent'];
}
/*
 * Los DOS anchos de la página, como el `contentSize` y el `wideSize` del
 * `theme.json` de Gutenberg: el del contenido (la medida de lectura) y el
 * amplio (para lo que debe respirar más, como una galería o una fila de
 * tarjetas). Cada sección elige entre los dos —o se va a todo lo ancho—, así
 * que basta cambiarlos aquí para recomponer la página entera.
 *
 * Compatibilidad: las páginas anteriores guardaban un único `width` con tres
 * valores fijos. Si no hay medidas nuevas, se derivan de aquel preajuste en vez
 * de saltar a un ancho que nadie eligió.
 */
$legacy  = ['narrow' => 720, 'boxed' => 1100, 'wide' => 1340];
$base    = $legacy[(string) ($settings['width'] ?? 'boxed')] ?? $legacy['boxed'];
$content = (int) ($settings['contentWidth'] ?? 0) ?: $base;
$wide    = (int) ($settings['wideWidth'] ?? 0) ?: (int) round($base * 1.25);

// Topes: por debajo no cabe una columna y por encima no hay pantalla. Se acotan
// aquí ADEMÁS de al guardar, porque estos valores llegan a un `max-width`.
$content = max(360, min(1600, $content));
$wide    = max($content, min(1920, $wide));
?>
/* ---------------------------------------------------------------------------
   Contenido de la página. Sin framework y sin CDN.

   TIPOGRAFÍA: Inter, la misma que usa toda la plataforma, para que una página
   publicada no desentone con el panel desde el que se armó. Se apunta a la copia
   **ya auto-hospedada** del núcleo (`public/fonts/inter/`) en vez de duplicar los
   ficheros dentro del componente.

   Es una excepción medida al §7.3 del contrato («tipografía del sistema»), y se
   sostiene porque **degrada sola**: la página no enlaza ninguna hoja de estilos
   externa —la prueba de fuego del contrato sigue pasando— y si el componente se
   lleva a otra plataforma, el @font-face simplemente no resuelve y entra la pila
   del sistema que va detrás. La página se ve bien igual, solo que con otra
   letra. Para hacerla 100 % autónoma bastaría copiar los cinco .woff2 a
   assets/fonts/ y servirlos por el controlador Asset.

   (Ojo con lo que se escribe en estos comentarios: acaban dentro del <style> de
   la página, así que una etiqueta HTML literal aquí aparece en el HTML servido.)
   --------------------------------------------------------------------------- */
<?php foreach ([300, 400, 500, 600, 700] as $weight): ?>
@font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: <?= $weight ?>;
    font-display: swap;
    src: url('<?= base_url('fonts/inter/inter-' . $weight . '.woff2') ?>') format('woff2');
}
<?php endforeach; ?>

.pg-root {
    --pg-accent: <?= $accent !== '' ? $accent : '#2563eb' ?>;
    --pg-accent-ink: #ffffff;
    --pg-ink: #16181d;
    --pg-muted: #5f6470;
    --pg-bg: #ffffff;
    --pg-soft: #f5f6f8;
    --pg-line: #e3e5ea;
    /* Borde de los controles de formulario. Va aparte de `--pg-line` a
       propósito: el tono que funciona para un divisor o el borde de una tarjeta
       es demasiado tenue para un campo, que tiene que leerse como una caja donde
       se puede escribir aunque esté sobre el fondo gris claro de su recuadro. */
    --pg-field-line: #b9c0cc;
    /* Los dos anchos de la página (ver arriba). `--pg-max` se conserva como
       alias del ancho de contenido: lo usan el hero y el pie del layout. */
    --pg-content: <?= $content ?>px;
    --pg-wide: <?= $wide ?>px;
    --pg-max: var(--pg-content);
    --pg-radius: 10px;

    color: var(--pg-ink);
    background: var(--pg-bg);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

@media (prefers-color-scheme: dark) {
    .pg-root:not([data-pg-theme="light"]) {
        --pg-ink: #e9eaee;
        --pg-muted: #a0a5b1;
        --pg-bg: #14161a;
        --pg-soft: #1c1f25;
        --pg-line: #2b2f37;
        --pg-field-line: #4b5361;
    }
}

.pg-root *, .pg-root *::before, .pg-root *::after { box-sizing: border-box; }
.pg-root img { max-width: 100%; height: auto; display: block; }
.pg-root a { color: var(--pg-accent); }

/* ------------------------------------------------------------- estructura */
.pg-section { width: 100%; }
/* Fija al desplazar (lo que hacía la antigua cabecera de sitio). En el editor
   NO se aplica —lo decide el renderizador—: anclada a la ventana se montaría
   sobre la paleta. */
.pg-sec-sticky { position: sticky; top: 0; z-index: 30; }
.pg-wrap { max-width: var(--pg-content); margin: 0 auto; padding-left: 20px; padding-right: 20px; }

/* Los tres anchos que puede elegir una sección, más el estrecho de lectura.
   Es el `align` de Gutenberg: ninguno (contenido), `wide` y `full`. */
.pg-w-boxed  > .pg-wrap { max-width: var(--pg-content); }
.pg-w-wide   > .pg-wrap { max-width: var(--pg-wide); }
.pg-w-full   > .pg-wrap { max-width: none; }
.pg-w-narrow > .pg-wrap { max-width: min(44rem, var(--pg-content)); }

.pg-pad-none { padding-top: 0; padding-bottom: 0; }
.pg-pad-sm   { padding-top: 16px; padding-bottom: 16px; }
.pg-pad-md   { padding-top: 32px; padding-bottom: 32px; }
.pg-pad-lg   { padding-top: 56px; padding-bottom: 56px; }
.pg-pad-xl   { padding-top: 88px; padding-bottom: 88px; }

.pg-cols { display: grid; grid-template-columns: 1fr; }
.pg-gap-none { gap: 0; }
.pg-gap-sm   { gap: 12px; }
.pg-gap-md   { gap: 24px; }
.pg-gap-lg   { gap: 40px; }
.pg-gap-xl   { gap: 64px; }

.pg-v-start   { align-items: start; }
.pg-v-center  { align-items: center; }
.pg-v-stretch { align-items: stretch; }

.pg-col { min-width: 0; }
/* Alineación VERTICAL del contenido de la columna. Solo entra si se pide
   algo distinto de «arriba», que es el reparto natural: convertir siempre la
   columna en un contenedor flexible cambiaría cómo se colapsan los márgenes
   de lo que ya está publicado. */
.pg-col[class*="pg-cv-"] { display: flex; flex-direction: column; height: 100%; }
.pg-cv-center  { justify-content: center; }
.pg-cv-bottom  { justify-content: flex-end; }
.pg-cv-between { justify-content: space-between; }
/* Con fondo propio la columna necesita aire por dentro: sin él el texto queda
   pegado al borde del color. */
.pg-col-bg { padding: 22px; }

/* Las columnas se reparten a partir de tableta: en el teléfono, por defecto,
   van una debajo de otra —que es lo único legible—, salvo que la sección diga
   lo contrario con «no apilar» (el `isStackedOnMobile` de Gutenberg). */
@media (min-width: 768px) {
    .pg-cols-2 { grid-template-columns: repeat(2, 1fr); }
    .pg-cols-3 { grid-template-columns: repeat(3, 1fr); }
    .pg-cols-4 { grid-template-columns: repeat(4, 1fr); }
}
@media (min-width: 480px) and (max-width: 767px) {
    .pg-cols-4, .pg-gallery.pg-cols-3, .pg-gallery.pg-cols-4 { grid-template-columns: repeat(2, 1fr); }
}

/* Sección que NO se apila: mantiene el reparto también en el teléfono. Para
   filas de iconos o cifras, donde apilar es peor que encoger. */
.pg-cols.pg-nostack.pg-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.pg-cols.pg-nostack.pg-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.pg-cols.pg-nostack.pg-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

/* Reparto a medida (25 % + 75 % y compañía): **fila flexible**, no rejilla.
   Con la rejilla de 12 que había antes, las 11 separaciones se sumaban a las
   doce pistas: en un ancho apretado —el lienzo del editor con los dos paneles
   abiertos— los huecos se comían el sitio, las pistas se encogían a casi nada y
   el contenido se salía de su celda amontonándose a la izquierda.

   `flex: 0 1 var(--pg-cw)` es justo la diferencia: la columna pide su
   porcentaje y **cede lo que haga falta** para que quepa la separación, en vez
   de desbordarse. El porcentaje lo emite el renderizador como variable —ya
   validado— para que sea esta hoja la que decida CUÁNDO se aplica: en el
   teléfono cada columna ocupa la fila entera, como el resto. */
.pg-cols-free { display: flex; flex-wrap: wrap; }
.pg-cols-free > .pg-col { flex: 1 1 100%; }
@media (min-width: 768px) {
    .pg-cols-free > .pg-col.pg-cw { flex: 0 1 var(--pg-cw); }
}
/* Sin apilar, el reparto rige también en pantalla estrecha. */
.pg-cols.pg-nostack.pg-cols-free > .pg-col.pg-cw { flex: 0 1 var(--pg-cw); }
/* Sección con imagen de fondo y velo (el `core/cover` de Gutenberg). */
.pg-sec-img { position: relative; background-size: cover; background-position: center; }
.pg-sec-img > .pg-wrap { position: relative; z-index: 1; }
.pg-sec-scrim { position: absolute; inset: 0; }
.pg-sec-img.pg-ov-dark  .pg-sec-scrim { background: rgba(12, 14, 18, .58); }
.pg-sec-img.pg-ov-light .pg-sec-scrim { background: rgba(255, 255, 255, .68); }
.pg-sec-img.pg-ov-dark  { color: #fff; }
.pg-sec-img.pg-ov-light { color: #14161a; }

/* ------------------------------------------------------------ alineaciones */
.pg-a-left   { text-align: left; }
.pg-a-center { text-align: center; }
.pg-a-right  { text-align: right; }

/* --------------------------------------------------------------- contenido */
.pg-h { margin: 0 0 .5em; line-height: 1.2; font-weight: 700; letter-spacing: -.01em; }
.pg-h1 { font-size: clamp(1.9rem, 4vw, 2.6rem); }
.pg-h2 { font-size: clamp(1.5rem, 3vw, 2rem); }
.pg-h3 { font-size: 1.3rem; }
.pg-h4 { font-size: 1.1rem; }

.pg-text { margin: 0 0 1em; }
.pg-text > *:first-child { margin-top: 0; }
.pg-text > *:last-child { margin-bottom: 0; }
.pg-text p { margin: 0 0 .9em; }
.pg-text h2, .pg-text h3, .pg-text h4, .pg-text h5 { margin: 1.4em 0 .5em; line-height: 1.25; }
.pg-text ul, .pg-text ol { margin: 0 0 .9em; padding-left: 1.3em; }
.pg-text li { margin: .25em 0; }
.pg-text blockquote { margin: 1em 0; padding-left: 1em; border-left: 3px solid var(--pg-line); color: var(--pg-muted); }
.pg-text img { margin: 1em 0; border-radius: var(--pg-radius); }
.pg-text table { width: 100%; border-collapse: collapse; margin: 1em 0; }
.pg-text th, .pg-text td { border: 1px solid var(--pg-line); padding: 8px 10px; text-align: left; }
.pg-text pre { background: var(--pg-soft); padding: 12px; border-radius: var(--pg-radius); overflow-x: auto; }
.pg-text code { background: var(--pg-soft); padding: .1em .35em; border-radius: 4px; }
.pg-t-sm { font-size: .93rem; }
.pg-t-md { font-size: 1rem; }
.pg-t-lg { font-size: 1.13rem; }

/* Capitular del primer párrafo (el `dropCap` de core/paragraph). Se aplica solo
   al primero: en el segundo ya no es un recurso, es un error. */
.pg-dropcap > p:first-of-type::first-letter {
    float: left;
    margin: .08em .12em 0 0;
    font-size: 3.2em;
    line-height: .82;
    font-weight: 700;
    color: var(--pg-accent);
}

/* Las viñetas se declaran EXPLÍCITAS. Esta misma hoja se incrusta dentro del
   editor, que carga Tailwind, y su reset (`preflight`) hace `ul { list-style:
   none }`: confiar en el valor por defecto del navegador dejaba la lista sin
   viñetas justo mientras se maquetaba. */
.pg-list { margin: 0 0 1em; padding-left: 1.3em; }
.pg-list li { margin: .35em 0; }
.pg-list a { color: var(--pg-accent); text-decoration: none; }
.pg-list a:hover { text-decoration: underline; }

.pg-list-bullet { list-style: disc outside; }
.pg-list-number { list-style: decimal outside; }

/* Las de marcador propio no usan `list-style`: lo pintan con `::before`, que es
   lo único que permite un icono y un color a medida. */
.pg-list-check, .pg-list-arrow, .pg-list-star, .pg-list-dash, .pg-list-none {
    list-style: none;
    padding-left: 0;
}
.pg-list-check li, .pg-list-arrow li, .pg-list-star li, .pg-list-dash li {
    position: relative;
    padding-left: 1.7em;
}
.pg-list-none li { padding-left: 0; }

/* Marca de verificación dibujada con dos bordes girados: sin imagen ni fuente
   de iconos, que en la página pública no habría de dónde sacar. */
.pg-list-check li::before {
    content: ""; position: absolute; left: .15em; top: .45em;
    width: .55em; height: .95em; border: solid var(--pg-accent);
    border-width: 0 2px 2px 0; transform: rotate(45deg);
}
.pg-list-arrow li::before {
    content: ""; position: absolute; left: .2em; top: .5em;
    width: .45em; height: .45em; border: solid var(--pg-accent);
    border-width: 2px 2px 0 0; transform: rotate(45deg);
}
.pg-list-star li::before {
    content: "★"; position: absolute; left: 0; top: 0;
    color: var(--pg-accent); font-size: .9em; line-height: 1.7;
}
.pg-list-dash li::before {
    content: ""; position: absolute; left: .1em; top: .85em;
    width: .8em; height: 2px; border-radius: 2px; background: var(--pg-accent);
}

.pg-quote { margin: 1.5em 0; padding: 0 0 0 1.1em; border-left: 3px solid var(--pg-accent); }
.pg-quote.pg-a-center { border-left: 0; padding: 0; }
.pg-quote p { margin: 0 0 .5em; font-size: 1.15rem; font-style: italic; }
.pg-cite { display: block; font-size: .9rem; font-style: normal; color: var(--pg-muted); }
.pg-cite-role::before { content: " · "; }

/* ---------------------------------------------------------------- imágenes */
.pg-fig { margin: 0 0 1.2em; }
/* Ojo: aquí había `border-radius: inherit`, que heredaba del `<figure>` —sin
   radio— y, con más especificidad que `.pg-r-*` (una clase + un elemento contra
   una clase), pisaba el radio elegido en el inspector. El resultado: la opción
   «esquinas» de la imagen no hacía nada. El radio lo pone su clase y punto. */
.pg-fig img { display: inline-block; }
.pg-fig figcaption, .pg-gitem figcaption, .pg-video figcaption {
    margin-top: .5em; font-size: .85rem; color: var(--pg-muted);
}
.pg-img-small  img { max-width: 200px; }
.pg-img-medium img { max-width: 420px; }
.pg-img-large  img { max-width: 720px; }
.pg-img-full   img { width: 100%; }
.pg-fig.pg-a-center img { margin-left: auto; margin-right: auto; }
.pg-fig.pg-a-right img { margin-left: auto; }

.pg-r-none { border-radius: 0; }
.pg-r-sm   { border-radius: var(--pg-radius); }
.pg-r-lg   { border-radius: 20px; }
.pg-r-full { border-radius: 999px; }

.pg-gallery { display: grid; grid-template-columns: 1fr; gap: 14px; margin: 0 0 1.2em; }
.pg-gitem { margin: 0; }
.pg-gitem img { width: 100%; }

/* Recorte a proporción fija (el `aspectRatio` de core/image y el `imageCrop` de
   core/gallery). Antes el 4:3 estaba clavado en el CSS de la galería y no se
   podía tocar; sin recorte, dos fotos de altura distinta descuadran la fila. */
.pg-ar { object-fit: cover; width: 100%; }
.pg-ar-16-9 { aspect-ratio: 16 / 9; }
.pg-ar-4-3  { aspect-ratio: 4 / 3; }
.pg-ar-1-1  { aspect-ratio: 1 / 1; }
.pg-ar-3-4  { aspect-ratio: 3 / 4; }
.pg-ar-9-16 { aspect-ratio: 9 / 16; }

/* ------------------------------------------------------------------ vídeo */
.pg-video { margin: 0 0 1.2em; }
.pg-video iframe { width: 100%; border: 0; display: block; border-radius: var(--pg-radius); }
.pg-ratio-16-9 iframe { aspect-ratio: 16 / 9; }
.pg-ratio-4-3  iframe { aspect-ratio: 4 / 3; }
.pg-ratio-1-1  iframe { aspect-ratio: 1 / 1; }

/* ---------------------------------------------------------------- botones */
.pg-btnwrap { margin: 0 0 1.2em; }

/* El botón se gobierna con TRES variables, no con reglas por estilo. Así el
   color que se elija en el inspector llega por `style="--pg-btn-bg:…"` y sirve
   igual para el fondo, para el borde y —lo que antes no pasaba— para el estado
   con el puntero encima: una regla `:hover` no puede leer un `style` en línea,
   pero sí una variable declarada en él.
   `--pg-btn-hover` la usan los CUATRO estilos: relleno en el sólido y en el de
   borde, velo en el fantasma y color del texto en el de enlace. */
.pg-btn {
    --pg-btn-bg: var(--pg-accent);
    --pg-btn-fg: var(--pg-accent-ink);
    --pg-btn-hover: color-mix(in srgb, var(--pg-btn-bg) 80%, #000);

    display: inline-block;
    text-decoration: none;
    font-weight: 600;
    border: 1px solid transparent;
    border-radius: var(--pg-radius);
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
}
.pg-btn:active { transform: translateY(1px); }
.pg-btn[disabled] { opacity: .55; cursor: default; }

.pg-btn-sm { padding: 7px 14px; font-size: .875rem; }
.pg-btn-md { padding: 10px 20px; font-size: 1rem; }
.pg-btn-lg { padding: 14px 28px; font-size: 1.06rem; }

/* Radio propio del botón. Va DESPUÉS de `.pg-btn` (misma especificidad, decide
   el orden) y con nombre propio: las clases `.pg-r-*` de las imágenes están
   antes en la hoja y `.pg-btn` las pisaba. */
.pg-btn-r-none { border-radius: 0; }
.pg-btn-r-sm   { border-radius: var(--pg-radius); }
.pg-btn-r-lg   { border-radius: 20px; }
.pg-btn-r-full { border-radius: 999px; }

.pg-btn-primary { background: var(--pg-btn-bg); color: var(--pg-btn-fg); border-color: var(--pg-btn-bg); }
.pg-btn-primary:hover { background: var(--pg-btn-hover); border-color: var(--pg-btn-hover); }

.pg-btn-outline { background: transparent; color: var(--pg-btn-bg); border-color: var(--pg-btn-bg); }
/* Al pasar por encima se rellena: es el cambio que se espera de un botón con
   borde, y deja claro que es pulsable. */
.pg-btn-outline:hover { background: var(--pg-btn-hover); color: var(--pg-btn-fg); border-color: var(--pg-btn-hover); }

.pg-btn-ghost { background: transparent; color: var(--pg-btn-bg); }
.pg-btn-ghost:hover { background: color-mix(in srgb, var(--pg-btn-hover) 22%, transparent); }

/* Estilo «enlace»: sin fondo ni borde, para cuando la fila de botones es un
   menú de navegación. Ahí el color elegido es el del TEXTO, no el del fondo
   (lo decide `Renderer::buttons()`, que emite `--pg-btn-fg` en vez de `-bg`). */
.pg-btn-link {
    background: transparent;
    border-color: transparent;
    color: var(--pg-btn-fg, var(--pg-ink));
    font-weight: 500;
}
/* En «enlace» no hay fondo que teñir: el color al pasar por encima es el del
   TEXTO. Es el estilo con el que se montan los menús, y era el único de los
   cuatro que ignoraba por completo el color elegido. */
.pg-btn-link:hover { background: var(--pg-soft); color: var(--pg-btn-hover); }

/* Botón a todo el ancho de su columna. En el teléfono es casi obligatorio: un
   botón pequeño y centrado se falla con el pulgar. */
.pg-btn-block { display: block; }
.pg-btn-block > .pg-btn { display: block; width: 100%; text-align: center; }

/* ------------------------------------------------------------------ hero */
.pg-hero {
    position: relative; display: flex; align-items: center;
    background-size: cover; background-position: center; background-color: var(--pg-soft);
    color: var(--pg-ink);
}
.pg-hero-sm { min-height: 240px; }
.pg-hero-md { min-height: 380px; }
.pg-hero-lg { min-height: 520px; }
.pg-hero::before { content: ""; position: absolute; inset: 0; }
.pg-ov-none::before  { background: transparent; }
.pg-ov-dark::before  { background: rgba(0, 0, 0, .5); }
.pg-ov-light::before { background: rgba(255, 255, 255, .6); }
/* Con velo hay que fijar la tinta: la del tema no contrasta con el velo. */
.pg-ov-dark  { color: #fff; }
.pg-ov-light { color: #14161a; }
.pg-hero-in {
    position: relative; width: 100%; max-width: var(--pg-max);
    margin: 0 auto; padding: 56px 20px;
}
.pg-hero-title { margin: 0 0 .35em; font-size: clamp(2rem, 5vw, 3.1rem); line-height: 1.1; font-weight: 800; letter-spacing: -.02em; }
.pg-hero-sub { margin: 0 0 1.4em; font-size: clamp(1rem, 2vw, 1.25rem); opacity: .92; max-width: 46rem; }
.pg-hero.pg-a-center .pg-hero-sub { margin-left: auto; margin-right: auto; }
.pg-hero.pg-a-right .pg-hero-sub { margin-left: auto; }

/* --------------------------------------------------------------- tarjetas */
.pg-cards { display: grid; grid-template-columns: 1fr; gap: 20px; margin: 0 0 1.2em; }
.pg-card {
    border: 1px solid var(--pg-line); border-radius: var(--pg-radius);
    overflow: hidden; background: var(--pg-bg); display: flex; flex-direction: column;
}
.pg-card-img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; }
.pg-card-body { padding: 18px; }
.pg-card-title { margin: 0 0 .4em; font-size: 1.12rem; font-weight: 700; }
.pg-card-text { margin: 0 0 .8em; color: var(--pg-muted); }
.pg-card-link { font-weight: 600; text-decoration: none; }
.pg-card-link:hover { text-decoration: underline; }

/* ----------------------------------------------------------- formulario */
.pg-form {
    border: 1px solid var(--pg-line); border-radius: var(--pg-radius);
    padding: 22px; background: var(--pg-soft); margin: 0 0 1.2em;
}
.pg-form-title { margin: 0 0 .3em; font-size: 1.25rem; font-weight: 700; }
.pg-form-intro { margin: 0 0 1.1em; color: var(--pg-muted); }
.pg-form-body { display: grid; gap: 14px; }
.pg-field { display: grid; gap: 5px; }
.pg-field label { font-size: .88rem; font-weight: 600; }
.pg-req { color: #d33; }
.pg-field input, .pg-field textarea, .pg-field select {
    width: 100%;
    /* `font: inherit` a secas también reinicia la interlínea a `normal`, que en
       un campo alto queda apretada; por eso va explícita después. */
    font: inherit;
    line-height: 1.4;
    color: var(--pg-ink);
    background: var(--pg-bg);
    /* Sin `appearance: none` cada navegador dibuja su propio marco encima del
       nuestro: en iOS el campo sale con sombra interior y esquinas propias, y el
       borde se pierde. */
    -webkit-appearance: none;
    appearance: none;
    border: 1px solid var(--pg-field-line);
    border-radius: 8px;
    padding: 11px 13px;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.pg-field input::placeholder, .pg-field textarea::placeholder { color: var(--pg-muted); opacity: 1; }
.pg-field input:hover, .pg-field textarea:hover, .pg-field select:hover {
    border-color: var(--pg-ink);
}
.pg-field input:focus, .pg-field textarea:focus, .pg-field select:focus {
    outline: none;
    border-color: var(--pg-accent);
    /* El halo hace de segundo borde: con `outline` + `border-color: transparent`
       el campo perdía su caja justo al enfocarlo, que es cuando más falta hace. */
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--pg-accent) 25%, transparent);
}
.pg-field textarea { resize: vertical; min-height: 110px; }
/* `appearance: none` se lleva también la flecha del desplegable: se repone con
   un SVG en línea (nada que descargar) para que siga pareciendo una lista. */
.pg-field select {
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 38px;
}
.pg-form button { justify-self: start; }
.pg-form-success { margin: 0; padding: 14px 16px; border-radius: 8px; background: rgba(22, 163, 74, .12); color: #15803d; font-weight: 600; }
.pg-form-error { margin: 0 0 1em; padding: 12px 14px; border-radius: 8px; background: rgba(220, 38, 38, .12); color: #b91c1c; font-weight: 600; }
.pg-form-empty { margin: 0; color: var(--pg-muted); font-style: italic; }

/* ===========================================================================
   BLOQUES DE EMPRESA — cabecera, QR, redes y botón flotante de WhatsApp.
   Registro sobrio: mucho aire, una sola tinta, filete fino de acento y nada
   de sombras marcadas. La imagen la pone el banner; la interfaz se aparta.
   =========================================================================== */

/* ------------------------------------------- contacto de la empresa ------ */
.pg-contact { display: flex; flex-direction: column; gap: 10px; margin: 0 0 1.2em; }
.pg-contact.pg-a-center { align-items: center; text-align: center; }
.pg-contact.pg-a-right  { align-items: flex-end; text-align: right; }
.pg-contact-item {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    color: var(--pg-muted);
    text-decoration: none;
    line-height: 1.5;
}
a.pg-contact-item:hover { color: var(--pg-accent); }
.pg-contact-item svg { width: 16px; height: 16px; flex: none; margin-top: 3px; opacity: .8; }

/* --- MENÚ PLEGABLE (el bloque «Botones» con «plegar en el móvil») -----------
   La cabecera y el pie dejaron de ser bloques propios: ahora son secciones
   armadas con elementos, y el menú es un bloque de botones. Lo único que no se
   podía reproducir con CSS corriente —plegarlo en el teléfono— vive aquí.

   **Sin JavaScript.** La página pública no carga ni una línea de guion (§7.3),
   así que el panel se abre con una casilla oculta y `:checked`. Es la única
   técnica que da un desplegable sin script y además funciona con el teclado: la
   etiqueta es enfocable y la barra espaciadora la activa. */
.pg-btnrow-wrap { display: contents; }
.pg-nav-check { position: absolute; opacity: 0; pointer-events: none; }
.pg-nav-burger {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 42px;
    height: 42px;
    margin-left: auto;
    padding: 9px;
    border-radius: 8px;
    cursor: pointer;
}
.pg-nav-burger:hover { background: var(--pg-soft); }
.pg-nav-burger span {
    display: block;
    height: 2px;
    border-radius: 2px;
    background: var(--pg-ink);
    transition: transform .2s ease, opacity .2s ease;
}

@media (max-width: 860px) {
    .pg-nav-burger { display: flex; }
    .pg-btnrow-collapse {
        /* Cerrado: alto cero y sin dejar hueco. `display:none` impediría animar
           y sacaría el menú del orden de tabulación de golpe. */
        flex-basis: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 2px;
        max-height: 0;
        overflow: hidden;
        transition: max-height .25s ease;
    }
    .pg-nav-check:checked ~ .pg-btnrow-collapse { max-height: 70vh; overflow-y: auto; padding: 8px 0 12px; }
    .pg-nav-check:checked ~ .pg-nav-burger span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .pg-nav-check:checked ~ .pg-nav-burger span:nth-child(2) { opacity: 0; }
    .pg-nav-check:checked ~ .pg-nav-burger span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
    .pg-btnrow-collapse .pg-btn { justify-content: flex-start; }
}
@media (prefers-reduced-motion: reduce) {
    .pg-btnrow-collapse, .pg-nav-burger span { transition: none; }
}

/* ----------------------------------------------- varios botones en fila --- */
.pg-btnrow { display: flex; flex-wrap: wrap; align-items: center; margin: 0 0 1.2em; }
.pg-btnrow.pg-a-left   { justify-content: flex-start; }
.pg-btnrow.pg-a-center { justify-content: center; }
.pg-btnrow.pg-a-right  { justify-content: flex-end; }
/* En el teléfono, dos botones lado a lado quedan tan estrechos que no se
   aciertan: por defecto pasan a ocupar la fila entera, uno debajo de otro. */
@media (max-width: 560px) {
    /* En columna es una lista de navegación. La alineación pasa a ser el eje
   transversal, así que `justify-content` ya no vale: manda `align-items`. */
.pg-btnrow-column { flex-direction: column; align-items: flex-start; }
.pg-btnrow-column.pg-a-center { align-items: center; }
.pg-btnrow-column.pg-a-right  { align-items: flex-end; }

.pg-btnrow-stack { flex-direction: column; align-items: stretch; }
    .pg-btnrow-stack > .pg-btn { text-align: center; }
}

/* ------------------------------------------------------------------- QR */
.pg-qrwrap { margin: 0 0 1.2em; }
.pg-qrwrap.pg-a-center { text-align: center; }
.pg-qrwrap.pg-a-right { text-align: right; }

.pg-qr {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    margin: 0;
    text-align: center;
}
.pg-qr-card {
    padding: 22px 24px;
    background: var(--pg-bg);
    border: 1px solid var(--pg-line);
    border-radius: 16px;
    box-shadow: 0 2px 14px rgba(10, 14, 22, .06);
}
.pg-qr img {
    display: block;
    height: auto;
    /* El QR se lee mejor sobre blanco puro aunque la página esté en oscuro. */
    background: #fff;
    padding: 8px;
    border-radius: 8px;
}
.pg-qr-sm img { width: 130px; }
.pg-qr-md img { width: 180px; }
.pg-qr-lg img { width: 240px; }

.pg-qr-title {
    margin: 0;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--pg-muted);
}
.pg-qr-cap { margin: 0; font-size: .88rem; color: var(--pg-muted); max-width: 18rem; }

/* ------------------------------------------------------------- redes ---- */
.pg-social { margin: 0 0 1.2em; }
.pg-soc-title {
    margin: 0 0 14px;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--pg-muted);
}
.pg-soc-row { display: flex; flex-wrap: wrap; gap: 12px; }
.pg-social.pg-a-center .pg-soc-row { justify-content: center; }
.pg-social.pg-a-right .pg-soc-row { justify-content: flex-end; }

.pg-soc-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    text-decoration: none;
    transition: transform .15s ease, background-color .15s ease, color .15s ease, border-color .15s ease;
}
.pg-soc-link:hover { transform: translateY(-2px); }
.pg-soc-sm .pg-soc-link { width: 38px; height: 38px; }
.pg-soc-md .pg-soc-link { width: 46px; height: 46px; }
.pg-soc-lg .pg-soc-link { width: 56px; height: 56px; }
.pg-soc-link svg { width: 45%; height: 45%; display: block; }

.pg-soc-solid .pg-soc-link { background: var(--pg-accent); color: #fff; }
.pg-soc-solid .pg-soc-link:hover { background: color-mix(in srgb, var(--pg-accent) 80%, #000); }
.pg-soc-outline .pg-soc-link { border: 1px solid var(--pg-line); color: var(--pg-ink); }
.pg-soc-outline .pg-soc-link:hover { border-color: var(--pg-accent); color: var(--pg-accent); }
.pg-soc-plain .pg-soc-link { color: var(--pg-muted); }
.pg-soc-plain .pg-soc-link:hover { color: var(--pg-accent); }

/* Con el nombre al lado (el `showLabels` de core/social-links) el círculo no
   sirve: pasa a píldora, con el icono a tamaño fijo y el texto detrás. */
.pg-soc-link.pg-soc-labeled {
    width: auto;
    gap: 9px;
    padding: 0 18px 0 14px;
    border-radius: 999px;
    font-weight: 600;
    font-size: .9rem;
}
.pg-soc-sm .pg-soc-labeled { height: 38px; }
.pg-soc-md .pg-soc-labeled { height: 46px; }
.pg-soc-lg .pg-soc-labeled { height: 56px; }
.pg-soc-labeled svg { width: 1.15em; height: 1.15em; flex: none; }
.pg-soc-name { white-space: nowrap; }

/* ------------------------------------------- WhatsApp flotante ---------- */
.pg-wa {
    position: fixed;
    /* Encima del contenido pero por debajo de cualquier capa modal. */
    z-index: 40;
    bottom: calc(20px + env(safe-area-inset-bottom, 0px));
    display: inline-flex;
    align-items: center;
    gap: 10px;
    height: 58px;
    padding: 0 19px;
    border-radius: 999px;
    /* Verde de marca de WhatsApp: aquí no manda el acento de la página, porque
       el botón se reconoce por el color tanto como por el icono. Las dos
       variables las puede reescribir el bloque con sus propios colores. */
    background: var(--pg-wa-bg, #25d366);
    color: var(--pg-wa-fg, #fff);
    text-decoration: none;
    font-weight: 600;
    font-size: .95rem;
    box-shadow: 0 8px 26px rgba(37, 211, 102, .42);
    transition: transform .18s ease, box-shadow .18s ease;
}
.pg-wa:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(37, 211, 102, .5); }
.pg-wa-right { right: calc(20px + env(safe-area-inset-right, 0px)); }
.pg-wa-left  { left: calc(20px + env(safe-area-inset-left, 0px)); }
/* Sin etiqueta: círculo, que es el flotante de toda la vida. La medida sale
   de las proporciones habituales —icono de 40 px con 8 px de aire alrededor—
   y la sombra pasa a gris neutro: la verde sobre fondo blanco desdibuja el
   borde del círculo, mientras que en la píldora, que es mucho mayor, funciona
   como un halo de marca. */
.pg-wa-round {
    width: 56px;
    height: 56px;
    padding: 0;
    gap: 0;
    justify-content: center;
    border-radius: 50%;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .35);
}
.pg-wa-round:hover { box-shadow: 0 6px 18px rgba(0, 0, 0, .4); }
.pg-wa-round .pg-wa-icon svg { width: 40px; height: 40px; }

.pg-wa-icon { display: inline-flex; }
.pg-wa-icon svg { width: 28px; height: 28px; display: block; }
.pg-wa-label { white-space: nowrap; }

/* En pantallas estrechas se queda solo el icono: la etiqueta se comía media
   pantalla justo encima del contenido. */
@media (max-width: 520px) {
    .pg-wa { height: 54px; padding: 0 13px; }
    .pg-wa-label { display: none; }
    /* Sin etiqueta que esconder, el círculo conserva su medida. */
    .pg-wa-round { width: 56px; height: 56px; padding: 0; }
}

/* Dentro del editor NO puede ir fijo: se anclaría a la ventana y taparía la
   paleta o el inspector. Ahí se pinta como un botón normal. */
.pg-wa-static {
    position: static;
    box-shadow: none;
    margin: 4px 0 1.2em;
}
.pg-wa-static:hover { transform: none; }

@media (prefers-reduced-motion: reduce) {
    .pg-wa, .pg-soc-link, .pg-chead-item { transition: none; }
    .pg-wa:hover, .pg-soc-link:hover { transform: none; }
}

/* Aviso del editor cuando el bloque necesita datos de la empresa que faltan.
   En la página publicada estos bloques no pintan nada. */
.pg-cempty {
    margin: 0 0 1.2em;
    padding: 18px;
    border: 1px dashed var(--pg-field-line);
    border-radius: var(--pg-radius);
    color: var(--pg-muted);
    font-size: .88rem;
    text-align: center;
}

/* ------------------------------------------------------------- separadores */
.pg-sp-none { height: 0; }
.pg-sp-sm   { height: 16px; }
.pg-sp-md   { height: 36px; }
.pg-sp-lg   { height: 64px; }
.pg-sp-xl   { height: 96px; }

.pg-hr { border: 0; border-top: 1px solid var(--pg-line); margin: 28px 0; }
.pg-hr-dashed { border-top-style: dashed; }
.pg-hr-dotted { border-top-style: dotted; }

.pg-raw { margin: 0 0 1.2em; }
.pg-raw iframe, .pg-raw img { max-width: 100%; }

/* ===========================================================================
   ESPACIADO POR BLOQUE (el `spacing` support de Gutenberg)
   Van AL FINAL a propósito: tienen la misma especificidad que las reglas de
   cada bloque (una clase), así que es el orden lo que decide, y lo que el
   usuario fija en el inspector tiene que ganar a lo que trae el bloque.
   Escala cerrada, la misma del bloque «Espacio» y del relleno de la sección:
   con píxeles libres una página se descuadra en dos tardes.
   =========================================================================== */
.pg-mt-none { margin-top: 0; }
.pg-mt-sm   { margin-top: 12px; }
.pg-mt-md   { margin-top: 28px; }
.pg-mt-lg   { margin-top: 52px; }
.pg-mt-xl   { margin-top: 84px; }

.pg-mb-none { margin-bottom: 0; }
.pg-mb-sm   { margin-bottom: 12px; }
.pg-mb-md   { margin-bottom: 28px; }
.pg-mb-lg   { margin-bottom: 52px; }
.pg-mb-xl   { margin-bottom: 84px; }

.pg-p-none { padding: 0; }
.pg-p-sm   { padding: 12px; }
.pg-p-md   { padding: 22px; }
.pg-p-lg   { padding: 36px; }
.pg-p-xl   { padding: 56px; }

/* En una sección el relleno propio es vertical (la banda respira arriba y
   abajo) y los lados los pone `.pg-wrap`; si además se le da relleno de bloque,
   se respeta sin descuadrar el centrado del contenido. */
.pg-section.pg-p-sm > .pg-wrap,
.pg-section.pg-p-md > .pg-wrap,
.pg-section.pg-p-lg > .pg-wrap,
.pg-section.pg-p-xl > .pg-wrap { padding-left: 0; padding-right: 0; }
