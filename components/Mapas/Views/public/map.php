<?php

/**
 * El mapa que ve el visitante.
 *
 * **Autosuficiente** (contrato §7.3): no carga `tailwind.css`, `icons.css` ni
 * `fonts.css` del núcleo, no enlaza ninguna hoja externa y no llama a ningún
 * CDN. Toda la hoja va en línea, los iconos son SVG en línea y la tipografía es
 * la del sistema. Quien abre esto está en el pasillo de un centro comercial con
 * el móvil: la página tiene que pintarse entera con lo que trae.
 *
 * Los estilos del mapa en sí salen de `_map_styles.php`, el MISMO archivo que
 * incrusta el editor: el pin se ve igual al colocarlo y al publicarlo.
 *
 * @var array $map
 * @var array $payload
 */
$empresa = trim((string) ($company['name'] ?? ''));

/*
 * El logo del MAPA manda sobre el de la empresa: un mismo grupo publica varios
 * mapas —dos centros comerciales, una feria— y cada uno tiene su identidad. Sin
 * logo propio se cae al de la empresa, que es lo que hacía antes: quien no toque
 * nada no nota el cambio.
 */
$logo = trim((string) ($map['logo'] ?? '')) ?: trim((string) ($company['logo'] ?? ''));
if ($logo !== '' && !preg_match('#^https?://#i', $logo)) {
    $logo = base_url(ltrim($logo, '/'));
}

// La cabecera se puede quitar: en un tótem solo le roba alto al plano.
$conCabecera = !isset($map['show_header']) || (int) $map['show_header'] === 1;
?>
<!doctype html>
<html lang="<?= esc(lang('Map.htmlLang'), 'attr') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
<title><?= esc($map['name']) ?><?= $empresa !== '' ? ' · ' . esc($empresa) : '' ?></title>
<meta name="description" content="<?= esc(mb_substr((string) ($map['description'] ?? ''), 0, 160), 'attr') ?>">
<meta name="robots" content="index, follow">
<style>
:root{
  --mv-bg:#eef1f5; --mv-card:#ffffff; --mv-ink:#111827; --mv-soft:#6b7280; --mv-line:#e5e7eb;
  /* Ancho del directorio en escritorio. Una sola medida: la usan el panel,
     el hueco de la leyenda —que si no queda debajo— y el guion, que se la
     pasa al visor para que la ficha no se abra tapada. */
  --pm-aside:19rem;
  /* El directorio va SOBRE el mapa, así que se deja ver un poco: sin
     transparencia parecía una pared pegada al plano. El desenfoque es lo
     que mantiene el texto legible con el mapa moviéndose detrás; donde no
     exista, queda el color con alfa, que ya funciona por sí solo. */
  --pm-glass:rgba(255,255,255,.82);
}
@media (prefers-color-scheme: dark){
  :root{
    --mv-bg:#101216; --mv-card:#191c22; --mv-ink:#e8eaee; --mv-soft:#9aa2ae; --mv-line:#282c34;
    --pm-glass:rgba(25,28,34,.82);
  }
}
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0; padding:0}
body{
  background:var(--mv-bg); color:var(--mv-ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  font-size:16px; line-height:1.5;
  display:flex; flex-direction:column;
  /* `100dvh` y no `100vh`: en el móvil la barra del navegador se recoge al
     desplazar y con `vh` queda un hueco muerto abajo. */
  height:100dvh;
}
/* La barra reúne QUÉ se está mirando (el nombre) y CÓMO se filtra (las
   categorías). Los niveles se fueron de aquí a un selector flotante sobre el
   plano: cambiar de piso es una acción sobre el mapa, no sobre la página. */
.pm-bar{
  display:flex; align-items:center; gap:.75rem; flex:none;
  padding:.6rem .9rem; background:var(--mv-card); border-bottom:1px solid var(--mv-line);
}
.pm-brand{display:flex; align-items:center; gap:.55rem; min-width:0; flex:none}
.pm-brand img{max-height:28px; max-width:110px; height:auto; display:block}
.pm-brand strong{font-size:1rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis}

/* Las categorías dicen qué significa cada color —sin ellas los pines son
   puntos de colores sin explicación— y de paso filtran. Estaban en una franja
   propia bajo el mapa; aquí arriba se leen junto al nombre y le devuelven al
   plano el alto que ocupaban. En pantalla estrecha se desplazan de lado en vez
   de partirse en dos filas, que empujarían el mapa hacia abajo. */
.pm-legend{
  display:flex; gap:.4rem; flex:none; margin-left:auto;
  overflow-x:auto; scrollbar-width:none; padding:.15rem 0;
}
.pm-legend::-webkit-scrollbar{display:none}

.pm-map{flex:1; min-height:0; position:relative}
.pm-legend button{
  display:inline-flex; align-items:center; gap:.4rem; flex:none;
  padding:.3rem .7rem; font:inherit; font-size:.78rem; font-weight:600;
  background:var(--mv-bg); color:var(--mv-soft);
  border:1px solid var(--mv-line); border-radius:999px; cursor:pointer; white-space:nowrap;
}
.pm-legend button.is-on{color:var(--mv-ink); border-color:currentColor}
.pm-legend i{width:.65rem; height:.65rem; border-radius:50%; flex:none}

/* Los niveles, flotando sobre la esquina superior derecha del plano.
   Un `<select>` y no la fila de botones de antes: con seis o siete pisos la
   fila se comía la barra, y elegir uno de una lista es lo que el navegador ya
   sabe hacer bien —también con el teclado y en el móvil, donde abre su propia
   rueda—. Se esconde con un solo nivel: un desplegable con una única opción no
   es una elección, es ruido. */
.pm-levelbox{
  position:absolute; top:.75rem; right:.75rem; z-index:15;
  background:var(--pm-glass); backdrop-filter:blur(10px) saturate(1.1);
  -webkit-backdrop-filter:blur(10px) saturate(1.1);
  border:1px solid var(--mv-line); border-radius:999px;
  box-shadow:0 2px 10px rgba(0,0,0,.14);
}
.pm-levelbox[hidden]{display:none}
.pm-levelbox select{
  -webkit-appearance:none; appearance:none;
  padding:.45rem 2rem .45rem .9rem; max-width:12rem;
  font:inherit; font-size:.82rem; font-weight:600; color:var(--mv-ink);
  background:none; border:0; border-radius:999px; cursor:pointer;
  /* La flecha, repuesta a mano: al quitar la apariencia nativa se va con ella. */
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right .6rem center; background-size:.9rem;
}
.pm-levelbox select:focus-visible{outline:2px solid var(--mv-ink); outline-offset:2px}
/* ------------------------------------------------------- el directorio */
/* MÓVIL PRIMERO: en un pasillo se mira el mapa con una mano, así que el
   directorio es una hoja que sube desde abajo y se cierra arrastrando o con el
   botón. Una columna fija a la izquierda dejaría el mapa en una franja
   inservible. A partir de 900 px sí cabe al lado, y entonces se queda fija. */
.pm-menu{
  position:fixed; left:0; right:0; bottom:0; z-index:30;
  display:flex; flex-direction:column;
  max-height:min(78dvh,34rem);
  background:var(--pm-glass);
  backdrop-filter:blur(16px) saturate(1.15); -webkit-backdrop-filter:blur(16px) saturate(1.15);
  border-top:1px solid var(--mv-line);
  border-radius:1rem 1rem 0 0;
  box-shadow:0 -8px 32px rgba(0,0,0,.22);
  transform:translateY(calc(100% - 3.25rem));
  transition:transform .28s cubic-bezier(.22,1,.36,1);
}
.pm-menu.is-open{transform:translateY(0)}
@media (prefers-reduced-motion:reduce){.pm-menu{transition:none}}

/* El tirador: es lo único visible con la hoja cerrada, así que tiene que
   decir qué hay dentro y cuánto. */
.pm-menu-grab{
  display:flex; align-items:center; gap:.6rem; flex:none;
  padding:.85rem 1rem; background:none; border:0; cursor:pointer;
  font:inherit; font-size:.9rem; font-weight:700; color:var(--mv-ink); text-align:left;
}
.pm-menu-grab::before{
  content:""; position:absolute; top:.4rem; left:50%; transform:translateX(-50%);
  width:2.25rem; height:.25rem; border-radius:999px; background:var(--mv-line);
}
.pm-menu-grab span{margin-left:auto; font-weight:400; font-size:.8rem; color:var(--mv-soft)}
.pm-menu-body{flex:1; min-height:0; overflow-y:auto; padding:0 .75rem 1rem; -webkit-overflow-scrolling:touch}

.pm-search{
  width:100%; margin-bottom:.6rem; padding:.6rem .8rem;
  -webkit-appearance:none; appearance:none;
  font:inherit; font-size:.95rem; color:var(--mv-ink); background:var(--mv-bg);
  border:1px solid var(--mv-line); border-radius:.6rem;
}
.pm-search:focus-visible{outline:none; border-color:var(--mv-ink)}

/* Un grupo por categoría, plegable. `<details>` y no JavaScript: se pliega
   solo, funciona sin guion y el navegador ya sabe hacerlo accesible. */
.pm-group{border-bottom:1px solid var(--mv-line)}
.pm-group:last-child{border-bottom:0}
.pm-group>summary{
  display:flex; align-items:center; gap:.55rem; cursor:pointer; list-style:none;
  padding:.7rem .25rem; font-size:.9rem; font-weight:600;
}
.pm-group>summary::-webkit-details-marker{display:none}
.pm-group>summary i{width:.7rem; height:.7rem; border-radius:50%; flex:none}
.pm-group>summary b{margin-left:auto; font-weight:400; font-size:.78rem; color:var(--mv-soft)}
.pm-group>summary::after{
  content:"›"; margin-left:.35rem; color:var(--mv-soft); font-size:1.1rem; line-height:1;
  transform:rotate(90deg); transition:transform .18s;
}
.pm-group[open]>summary::after{transform:rotate(-90deg)}

/* La fila del directorio: **solo el nombre**, sin miniatura ni descripción. Un
   directorio es una lista para encontrar algo, y con cien locales las fotos lo
   convierten en un desplazamiento interminable; la foto y la descripción están
   donde importan —en la ficha—, a un toque de aquí.
   Y con una línea entre filas, que es lo que deja recorrerlas con la vista sin
   contar renglones. Va como `border-top` en todas menos la primera —y no
   `border-bottom`— para que la última no cierre el grupo con una raya suelta
   justo encima de la del siguiente. */
.pm-item{
  display:flex; align-items:center; gap:.6rem; width:100%;
  padding:.6rem .5rem;
  background:none; border:0; border-top:1px solid var(--mv-line); cursor:pointer;
  font:inherit; text-align:left; color:var(--mv-ink);
}
.pm-group > summary + .pm-item,
.pm-group > summary + .pm-row{border-top:0}

/* La fila con sub-elementos: el nombre lleva al sitio y el chevrón abre lo que
   ese sitio OFRECE. Son dos acciones distintas, así que son dos botones —si el
   nombre desplegara, no habría forma de ir al local—. La línea de separación
   pasa a la fila: dentro, la `.pm-item` ya no la pinta. */
.pm-row{display:flex; align-items:stretch; border-top:1px solid var(--mv-line)}
.pm-row > .pm-item{border-top:0}
.pm-sub-toggle{
  flex:0 0 auto; width:2.4rem; padding:0;
  background:none; border:0; cursor:pointer;
  color:var(--mv-soft); font:inherit; font-size:.9rem; line-height:1;
  transition:transform .15s ease;
}
.pm-sub-toggle:hover,.pm-sub-toggle:focus-visible{color:var(--mv-ink); outline:none}
.pm-sub-toggle.is-open{transform:rotate(180deg)}
.pm-subs{
  display:flex; flex-direction:column; gap:.25rem;
  padding:.15rem .5rem .5rem 1.6rem;
}
/* `[hidden]` es una regla del NAVEGADOR y pierde contra cualquier clase que
   declare `display`: sin esto, el desplegable nacería abierto. */
.pm-subs[hidden]{display:none}
.pm-sub{
  padding:.4rem .7rem; text-align:left;
  font:inherit; font-size:.8125rem; font-weight:600; line-height:1.3;
  color:var(--pin,#2563eb); background:transparent; cursor:pointer;
  border:1.5px solid var(--pin,#2563eb); border-radius:999px;
}
.pm-sub:hover,.pm-sub:focus-visible{background:var(--mv-bg); outline:none}
/* Encendido: es un interruptor, y hay que ver cuál está puesto. */
.pm-sub.is-on{color:#fff; background:var(--pin,#2563eb)}
.pm-item:hover,.pm-item:focus-visible{background:var(--mv-bg); outline:none}
.pm-item.is-on{background:var(--mv-bg); box-shadow:inset 2px 0 0 var(--pin,#2563eb)}
.pm-item-txt{min-width:0; flex:1}
.pm-item-txt strong{
  display:block; font-size:.875rem; font-weight:600; line-height:1.35;
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
/* El piso al que pertenece el sitio. Solo se pinta con más de un nivel: con
   uno solo sería la misma etiqueta repetida en todas las filas. */
.pm-item-lv{
  flex:none; align-self:center; padding:.1rem .4rem; border-radius:999px;
  background:var(--mv-bg); color:var(--mv-soft);
  font-size:.65rem; font-weight:600; white-space:nowrap;
}

.pm-none{padding:1.5rem .5rem; text-align:center; color:var(--mv-soft); font-size:.85rem}

/* El botón que pliega el directorio. Solo en escritorio: en el móvil ya se
   pliega solo, es una hoja, y su tirador hace este mismo trabajo. */
.pm-toggle{
  display:none; flex:none; align-items:center; justify-content:center;
  width:2rem; height:2rem; padding:0; cursor:pointer;
  background:var(--mv-bg); color:var(--mv-soft);
  border:1px solid var(--mv-line); border-radius:.5rem;
}
.pm-toggle:hover{color:var(--mv-ink)}
.pm-toggle svg{width:1.05rem; height:1.05rem; display:block}
/* La barra del icono marca DÓNDE está el panel; al plegarlo se refleja, que es
   la única pista de que volverá por la izquierda. */
body.pm-tight .pm-toggle svg{transform:scaleX(-1)}

/* A partir de 900 px hay sitio para tenerlo siempre a la vista. */
@media (min-width:900px){
  /* UNA sola columna, y el directorio SUPERPUESTO sobre el mapa.
     Con el directorio como columna propia, abrirlo y cerrarlo cambiaba el
     ancho del plano: el mapa se recomponía debajo de las manos y el encuadre
     saltaba justo cuando se estaba mirando algo. Superpuesto, el plano mide
     siempre lo mismo y el panel solo tapa o destapa.
     La superposición la hace la propia rejilla: el directorio y el mapa
     ocupan LA MISMA celda (fila 2), no hace falta posicionarlo a mano ni
     acertar con el alto de la cabecera. */
  body{
    display:grid; grid-template-columns:minmax(0,1fr); grid-template-rows:auto minmax(0,1fr);
  }
  .pm-bar{grid-column:1; grid-row:1}
  .pm-side{grid-column:1; grid-row:2; display:flex; flex-direction:column; min-height:0; min-width:0}
  .pm-menu{
    grid-column:1; grid-row:2; justify-self:start; z-index:25;
    position:relative; transform:none; width:var(--pm-aside); max-width:100%;
    max-height:none; min-height:0;
    border-top:0; border-right:1px solid var(--mv-line); border-radius:0;
    /* Sombra hacia fuera: es lo que dice que está ENCIMA del plano y no al lado. */
    box-shadow:0 0 28px rgba(0,0,0,.18);
  }
  .pm-menu-grab{display:none}
  .pm-menu-body{padding-top:.75rem}
  .pm-toggle{display:inline-flex}


  /* Plegado: el panel desaparece y el mapa se queda como estaba, intacto.
     `display:none` y no un ancho de 0: una caja de 0 px sigue midiendo su
     contenido y el navegador la deja asomar. */
  body.pm-tight .pm-menu{display:none}
}

/* Sin cabecera, el botón de plegar flota sobre el plano: vivía en la barra,
   y sin él no habría forma de volver a abrir el directorio. */
.pm-toggle-float{
  /* También en el móvil: sin cabecera no hay otro sitio desde donde abrir
     el directorio, y el tirador de la hoja solo existe ahí abajo. */
  display:inline-flex;
  position:fixed; top:.75rem; left:.75rem; z-index:31;
  background:var(--pm-glass); backdrop-filter:blur(10px);
  -webkit-backdrop-filter:blur(10px);
  box-shadow:0 2px 10px rgba(0,0,0,.14);
}
@media (min-width:900px){
  .pm-toggle-float{display:inline-flex}
}
@media (max-width:899px){
  .pm-side{display:flex; flex-direction:column; flex:1; min-height:0}
  /* Hueco para el tirador de la hoja, que si no tapa el último control de
     zoom. Va con `.pm-map` delante a propósito: `_map_styles.php` se
     incrusta DESPUÉS de esta hoja y trae su propio `.mv-tools{bottom:.75rem}`,
     así que a igual especificidad ganaba el suyo y la regla no hacía nada. */
  .pm-map .mv-tools{bottom:4.25rem}
  /* La barra se aprieta: el nombre puede encogerse, las categorías se
     desplazan de lado y el conjunto no debe partirse en dos filas. */
  .pm-brand{min-width:0; flex:0 1 auto}
  .pm-legend{margin-left:.25rem; min-width:0}
}

.pm-empty{
  position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
  color:var(--mv-soft); font-size:.9rem; text-align:center; padding:2rem;
}
</style>
<?= view('Components\Mapas\Views\_map_styles') ?>
</head>
<body>

<?php if ($conCabecera): ?>
<header class="pm-bar">
    <button type="button" class="pm-toggle" data-pm-toggle aria-controls="pmMenu" aria-expanded="true"
            title="<?= esc(lang('Map.menuClose'), 'attr') ?>" aria-label="<?= esc(lang('Map.menuClose'), 'attr') ?>">
        <?php /* Panel lateral: el marco y la columna. SVG en línea (§7.3). */ ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
            <path d="M9 3v18"></path>
        </svg>
    </button>
    <div class="pm-brand">
        <?php if ($logo !== ''): ?><img src="<?= esc($logo, 'attr') ?>" alt="<?= esc($empresa, 'attr') ?>"><?php endif; ?>
        <strong><?= esc($map['name']) ?></strong>
    </div>
    <nav class="pm-legend" data-pm-legend aria-label="<?= esc(lang('Map.categories'), 'attr') ?>"></nav>
</header>
<?php else: ?>
<?php /* Sin cabecera, las categorías se van con ella; el directorio las
         sigue agrupando, que es donde de verdad se usan. El botón de
         plegar vivía ahí arriba, así que aquí flota sobre el plano. */ ?>
<button type="button" class="pm-toggle pm-toggle-float" data-pm-toggle aria-controls="pmMenu" aria-expanded="true"
        title="<?= esc(lang('Map.menuClose'), 'attr') ?>" aria-label="<?= esc(lang('Map.menuClose'), 'attr') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
        <path d="M9 3v18"></path>
    </svg>
</button>
<?php endif; ?>

<div class="pm-side">
    <main class="pm-map">
        <div data-pm-stage style="width:100%;height:100%"></div>

        <?php /* Los niveles, flotando sobre el plano. Oculto de salida: el
                 guion lo enseña solo si hay más de uno que elegir. */ ?>
        <div class="pm-levelbox" data-pm-levelbox hidden>
            <select data-pm-levels aria-label="<?= esc(lang('Map.levels'), 'attr') ?>"></select>
        </div>

        <div class="mv-tools">
            <button type="button" data-pm-in aria-label="<?= esc(lang('Map.zoomIn'), 'attr') ?>">+</button>
            <button type="button" data-pm-out aria-label="<?= esc(lang('Map.zoomOut'), 'attr') ?>">−</button>
            <button type="button" data-pm-fit aria-label="<?= esc(lang('Map.fit'), 'attr') ?>">⤢</button>
        </div>
    </main>

</div>

<!--
  El directorio: las categorías con sus sitios dentro, plegables.
  En móvil es una hoja que sube desde abajo —se mira el mapa con una mano—; a
  partir de 900 px se queda fija a la izquierda. Los grupos son `<details>` y no
  JavaScript: se pliegan solos, funcionan sin guion y el navegador ya sabe
  hacerlos accesibles.
-->
<aside class="pm-menu" id="pmMenu" data-pm-menu>
    <button type="button" class="pm-menu-grab" data-pm-grab aria-expanded="false">
        <?= lang('Map.menu') ?>
        <span data-pm-count></span>
    </button>
    <div class="pm-menu-body">
        <input type="search" class="pm-search" data-pm-search
               placeholder="<?= esc(lang('Map.search'), 'attr') ?>"
               aria-label="<?= esc(lang('Map.search'), 'attr') ?>">
        <div data-pm-groups></div>
    </div>
</aside>

<script type="application/json" id="mapData"><?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="mapI18n"><?= json_encode([
    'more'  => lang('Map.more'),
    'close' => lang('Map.close'),
    'empty' => lang('Map.publicEmpty'),
    'none'  => lang('Map.noResults'),
    'nPts'  => lang('Map.nPoints'),
    'onePt' => lang('Map.onePoint'),
    'noCat' => lang('Map.noCategory'),
    'open'  => lang('Map.menuOpen'),
    'shut'  => lang('Map.menuClose'),
    // El desplegable de los sub-elementos.
    'subsOpen' => lang('Map.subsOpen'),
], JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= base_url('mapa/recurso/viewer.js') ?>?v=<?= @filemtime(__DIR__ . '/../../assets/js/viewer.js') ?>"></script>
<script>
(function () {
    'use strict';
    function json(id, f) { var e = document.getElementById(id); try { return e ? JSON.parse(e.textContent) : f; } catch (x) { return f; } }
    var datos = json('mapData', { levels: [], categories: [] });
    var T = json('mapI18n', {});
    var crear = window.MapViewer.crear;
    var host = document.querySelector('[data-pm-stage]');

    if (!datos.levels.length) {
        host.appendChild(crear('div', { class: 'pm-empty', text: T.empty || '' }));
        return;
    }

    var ficha = null;
    function cerrar() { visor.detachCard(); ficha = null; }

    var visor = new window.MapViewer(host, {
        data: datos,
        // Los textos de la ficha, que ahora arma el visor.
        i18n: T,
        onCanvasClick: cerrar,
        // Cuánto del lienzo tapa el directorio por la izquierda. Se mide del
        // DOM y no de una constante: así vale igual plegado, desplegado y en
        // móvil, donde el panel es una hoja de abajo y no tapa nada.
        inset: function () {
            var m = document.querySelector('[data-pm-menu]');
            if (!m || getComputedStyle(m).display === 'none') { return 0; }
            var r = m.getBoundingClientRect(), s = visor.stage.getBoundingClientRect();
            // Solo cuenta si es la COLUMNA de escritorio —alto completo y sin
            // ocupar todo el ancho—. En el móvil es una hoja pegada abajo que
            // va de lado a lado, y tomarla por un margen izquierdo empujaría
            // todas las fichas fuera de la pantalla.
            var columna = r.height >= s.height - 2 && r.width < s.width;
            return columna ? Math.max(0, Math.min(r.right - s.left, s.width)) : 0;
        },
        onPick: function (p, btn, pos) {
            cerrar();
            // La arma el VISOR, que es donde vive una sola vez: esta pantalla y
            // el editor la tenían copiada y se separaban a cada cambio.
            var card = visor.buildCard(p, { onClose: cerrar });
            // La coloca el VISOR, anclada al punto: así sigue al pin cuando se
            // mueve o se amplía el mapa. Antes se escribía aquí un `left`/`top`
            // fijos y la ficha se quedaba señalando a otro local.
            // Anclada DONDE SE PULSÓ cuando llega ese dato —una forma grande
            // tiene el centroide lejos, a veces fuera de la pantalla—; en un pin
            // y al llegar desde el directorio no hay clic, y manda su propio sitio.
            ficha = visor.attachCard(card, pos || p);
        }
    });

    /* --------------------------------------------- los niveles (flotante) */

    var nav  = document.querySelector('[data-pm-levels]');
    var caja = document.querySelector('[data-pm-levelbox]');

    function pintarNiveles() {
        nav.innerHTML = '';
        datos.levels.forEach(function (lv, i) {
            nav.appendChild(crear('option', { value: String(i), text: lv.name }));
        });
        nav.value = String(visor.levelIndex);
        // Con un solo nivel no hay nada que elegir: un desplegable de una
        // opción solo ocupa sitio sobre el plano.
        caja.hidden = datos.levels.length < 2;
    }

    nav.addEventListener('change', function () {
        cerrar();
        elegido = null;
        visor.setLevel(parseInt(nav.value, 10) || 0);
        pintarNiveles();
        pintarMenu();
    });
    pintarNiveles();

    var leg = document.querySelector('[data-pm-legend]');
    var activa = null;
    function pintarLeyenda() {
        // La leyenda vive DENTRO de la cabecera, y la cabecera se puede
        // quitar: sin esta guarda, apagarla dejaba el mapa entero muerto con
        // un «Cannot set properties of null». Las categorías siguen
        // agrupando el directorio, que es donde de verdad se usan.
        if (!leg) { return; }
        leg.innerHTML = '';
        if (!datos.categories.length) { leg.hidden = true; return; }
        datos.categories.forEach(function (c) {
            leg.appendChild(crear('button', {
                type: 'button', class: activa === c.id ? 'is-on' : '', style: 'color:' + c.color,
                on: {
                    click: function () {
                        // Volver a pulsar la misma quita el filtro: es lo que
                        // espera cualquiera de un botón que ya está encendido.
                        activa = activa === c.id ? null : c.id;
                        visor.setFilter(activa);
                        cerrar();
                        pintarLeyenda();
                        pintarMenu();
                    }
                }
            }, [crear('i', { style: 'background:' + c.color }), crear('span', { text: c.name })]));
        });
    }
    pintarLeyenda();

    /* ------------------------------------------------------ el directorio */

    var menu   = document.querySelector('[data-pm-menu]');
    var grupos = document.querySelector('[data-pm-groups]');
    var busca  = document.querySelector('[data-pm-search]');
    var elegido = null;

    /** Sin acentos y en minúsculas: buscar «cafe» tiene que encontrar «Café». */
    function plano(t) {
        return (t || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    /** Los puntos del nivel actual que pasan el filtro de búsqueda. */
    /**
     * Lo que se lista en el directorio: **todo el mapa, no solo el piso a la
     * vista**.
     *
     * Quien busca una tienda no sabe en qué planta está —es justo lo que viene a
     * averiguar—, así que acotar la lista al nivel actual convertía el buscador
     * en algo que solo encuentra lo que ya se estaba mirando. Cada sitio lleva
     * el nivel al que pertenece, y al pulsarlo el mapa cambia de piso solo.
     */
    /** Los sub-elementos de un elemento, en cualquier piso. */
    function subDe(id) {
        var out = [];
        datos.levels.forEach(function (lv, i) {
            (lv.points || []).forEach(function (p) {
                if (p.parent === id) { out.push({ p: p, nivel: i, nivelNombre: lv.name }); }
            });
        });
        return out;
    }

    /**
     * Lo que se lista: los elementos SUELTOS, no los sub-elementos.
     *
     * Un sub-elemento no es un sitio, es algo que un sitio ofrece: sacarlo al
     * mismo nivel dejaría el directorio lleno de «Cómo llegar» sin decir a
     * dónde. Cuelgan de su padre, en el desplegable.
     *
     * Pero **la búsqueda sí los mira**: quien escribe el nombre de una ruta
     * tiene que encontrarla, así que si un hijo casa, aparece su padre —que es
     * por donde se llega a él—.
     */
    function visibles() {
        var q = plano(busca.value.trim());
        var casa = function (p) {
            return !q || plano(p.title).indexOf(q) >= 0 || plano(p.desc).indexOf(q) >= 0;
        };

        // Qué padres tienen algún hijo que case: hay que enseñarlos aunque el
        // padre no case por sí mismo.
        var porHijo = {};
        datos.levels.forEach(function (lv) {
            (lv.points || []).forEach(function (p) {
                if (p.parent && casa(p)) { porHijo[p.parent] = true; }
            });
        });

        var out = [];
        datos.levels.forEach(function (lv, i) {
            (lv.points || []).forEach(function (p) {
                if (p.parent) { return; }
                if (!casa(p) && !porHijo[p.id]) { return; }
                // Se anota el nivel en una copia y no en el punto: el objeto es
                // el mismo que consume el visor, y ensuciarlo con datos de
                // presentación acabaría viajando a sitios donde no pinta nada.
                out.push({ p: p, nivel: i, nivelNombre: lv.name });
            });
        });
        return out;
    }

    /**
     * El directorio: un grupo por categoría con sus sitios dentro.
     *
     * Los sitios **sin categoría** van en su propio grupo al final, no se
     * esconden: alguien tiene que poder encontrarlos igual.
     */
    function pintarMenu() {
        var pts = visibles();

        /*
         * Qué estaba abierto, para devolverlo como estaba.
         *
         * La lista se rehace entera cada vez —al buscar, al cambiar de piso, al
         * encender una ruta—, y un `<details>` recién creado nace cerrado. Sin
         * esto, tocar un «cómo llegar» plegaba el grupo que se acababa de abrir
         * y había que volver a buscar dónde se estaba.
         */
        var abiertos = {};
        Array.prototype.forEach.call(grupos.querySelectorAll('.pm-group'), function (g) {
            if (g.open) { abiertos['g' + g.dataset.cat] = true; }
        });
        Array.prototype.forEach.call(grupos.querySelectorAll('.pm-subs'), function (s) {
            if (!s.hidden) { abiertos['s' + s.dataset.pid] = true; }
        });

        grupos.innerHTML = '';
        document.querySelector('[data-pm-count]').textContent =
            pts.length === 1 ? T.onePt : (T.nPts || '{0}').replace('{0}', pts.length);

        if (!pts.length) {
            grupos.appendChild(crear('p', { class: 'pm-none', text: T.none }));
            return;
        }

        var cats = (datos.categories || []).slice();
        cats.push({ id: null, name: T.noCat, color: '#2563eb' });

        var variosNiveles = datos.levels.length > 1;

        cats.forEach(function (c) {
            var suyos = pts.filter(function (x) { return (x.p.category || null) === c.id; });
            if (!suyos.length) { return; }

            var det = crear('details', { class: 'pm-group', 'data-cat': String(c.id) });
            // Con búsqueda activa se abren todos: si no, habría que ir
            // desplegando grupo por grupo para ver qué ha encontrado.
            if (busca.value.trim() || cats.length <= 2 || abiertos['g' + c.id]) { det.open = true; }

            det.appendChild(crear('summary', {}, [
                crear('i', { style: 'background:' + c.color }),
                crear('span', { text: c.name }),
                crear('b', { text: String(suyos.length) })
            ]));

            suyos.forEach(function (x) {
                var p = x.p;
                var hijos = subDe(p.id);

                var fila = crear('button', {
                    type: 'button',
                    class: 'pm-item' + (elegido === p.id ? ' is-on' : ''),
                    style: '--pin:' + c.color,
                    on: { click: function () { irA(p, x.nivel); } }
                }, [
                    // Solo el nombre: ni miniatura ni descripción. Un directorio
                    // es una lista para encontrar algo, y con cien locales las
                    // fotos lo convierten en un desplazamiento interminable. La
                    // foto y la descripción están donde importan —en la ficha—,
                    // a un toque de aquí.
                    crear('span', { class: 'pm-item-txt' }, [
                        crear('strong', { text: p.title })
                    ]),
                    // El piso, solo cuando hay más de uno: con un único nivel
                    // sería la misma etiqueta repetida en todas las filas. Es lo
                    // que distingue dos locales que se llaman igual en plantas
                    // distintas, y lo que hace entender por qué el mapa cambia de
                    // piso al pulsar.
                    variosNiveles ? crear('span', { class: 'pm-item-lv', text: x.nivelNombre }) : null
                ]);

                if (!hijos.length) { det.appendChild(fila); return; }

                /*
                 * Con sub-elementos, la fila se convierte en un desplegable.
                 *
                 * El nombre sigue llevando al sitio y el chevrón abre lo que ese
                 * sitio OFRECE —su «cómo llegar»—. Son dos acciones distintas, y
                 * por eso son dos botones: si el nombre desplegara, no habría
                 * forma de ir al local; y si desplegar fuera lo único, habría que
                 * abrir para poder llegar.
                 */
                var visto = !!abiertos['s' + p.id];
                var subs = crear('div', { class: 'pm-subs', 'data-pid': String(p.id), hidden: !visto });
                var chev = crear('button', {
                    type: 'button', class: 'pm-sub-toggle' + (visto ? ' is-open' : ''),
                    'aria-expanded': visto ? 'true' : 'false',
                    'aria-label': T.subsOpen || 'Ver más opciones',
                    title: T.subsOpen || 'Ver más opciones',
                    text: '\u25BE'
                });
                chev.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    var abierto = subs.hidden;
                    subs.hidden = !abierto;
                    chev.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                    chev.classList.toggle('is-open', abierto);
                });

                hijos.forEach(function (h) {
                    subs.appendChild(crear('button', {
                        type: 'button',
                        class: 'pm-sub' + (visor.activeChild === h.p.id ? ' is-on' : ''),
                        style: '--pin:' + c.color,
                        text: h.p.title,
                        on: {
                            click: function () {
                                // El mismo interruptor que en la ficha: si ya
                                // está puesta, se apaga y se cierra. Si solo
                                // encendiera, no habría forma de quitar la ruta
                                // del plano desde aquí.
                                if (visor.activeChild === h.p.id) {
                                    visor.setActiveChild(h.p.id);
                                    cerrar();
                                    pintarMenu();
                                    return;
                                }
                                irA(p, x.nivel, h.p);
                            }
                        }
                    }));
                });

                det.appendChild(crear('div', { class: 'pm-row' }, [fila, chev]));
                det.appendChild(subs);
            });

            grupos.appendChild(det);
        });
    }

    /**
     * Llevar el mapa hasta un sitio: se amplía y se centra, y se le abre la
     * ficha. Es lo que convierte una lista de locales en una forma de llegar
     * a ellos.
     */
    function irA(p, nivel, hijo) {
        elegido = p.id;
        cerrar();
        // En móvil la hoja tapa el mapa: se recoge para que se vea adónde ha
        // ido. En pantalla ancha está al lado y no estorba, así que se queda.
        if (window.matchMedia('(max-width: 899px)').matches) { abrirMenu(false); }

        // Si el sitio está en otro piso, se cambia de piso **y el selector lo
        // dice**: el visitante tiene que entender por qué el plano ya no es el
        // que estaba mirando.
        var cambiaNivel = nivel !== undefined && nivel !== visor.levelIndex;
        if (cambiaNivel) {
            visor.setLevel(nivel);
            pintarNiveles();
        }

        pintarMenu();

        // Cambiar de nivel carga la imagen del plano nuevo y termina en `fit()`,
        // que reencuadra: enfocar antes de eso lo descolocaría. Se espera a que
        // el nivel esté puesto y luego se va al punto.
        var enfocar = function () {
            visor.focusPoint(p);
            // La ficha, cuando la animación ya ha dejado el pin en su sitio:
            // antes, se colocaría junto a la posición vieja.
            setTimeout(function () {
                /*
                 * Si se pidió un sub-elemento, se enciende ANTES de abrir la
                 * ficha, y la ficha se ancla AL HIJO.
                 *
                 * Encenderlo después dejaba la ficha colocada para el encuadre
                 * viejo; y anclarla al padre la mandaba fuera de la pantalla en
                 * cuanto los dos no cabían juntos —una ruta larga que empieza en
                 * la otra punta del piso—, así que se veía el recorrido y se
                 * perdía de vista de quién era. Anclada al hijo, la ficha —que
                 * sigue siendo la del local— viaja con lo que se pidió ver.
                 */
                var sitio = null;
                if (hijo) {
                    visor.setActiveChild(hijo.id, p);
                    pintarMenu();
                    sitio = { x: hijo.x, y: hijo.y };
                }

                /*
                 * Si se pidió un sub-elemento, la ficha que se abre es LA SUYA.
                 *
                 * Desde el menú se ha elegido la ruta por su nombre, así que lo
                 * que hay que enseñar es la ruta: su título, su descripción, su
                 * foto y su enlace. Antes se abría la del padre, y todo lo que
                 * se hubiera escrito en el sub-elemento quedaba sin salida.
                 * Desde la FICHA DEL PADRE el botón sigue siendo un interruptor
                 * —enciende y apaga la ruta sin cambiar de ficha—, que es lo que
                 * permite comparar dos rutas del mismo local.
                 */
                var quien = hijo || p;
                var pin = visor.pins.filter(function (x) { return x.point.id === quien.id; })[0];
                var forma = (visor.formas || []).filter(function (x) { return x.point.id === quien.id; })[0];
                var el = (pin && pin.el) || (forma && forma.el);
                if (el || sitio) { visor.opts.onPick(quien, el, sitio); }
                // Y un destello sobre lo que se buscaba: sin él el viaje
                // termina y no queda claro cuál de los diez de esa esquina era.
                visor.pulse(hijo ? hijo.id : p.id);
            }, 360);
        };

        if (cambiaNivel) { setTimeout(enfocar, 260); } else { enfocar(); }
    }

    function abrirMenu(abrir) {
        menu.classList.toggle('is-open', abrir);
        var g = document.querySelector('[data-pm-grab]');
        g.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        g.setAttribute('aria-label', abrir ? T.shut : T.open);
    }

    /* --------------------------------------- plegar el directorio (escritorio) */

    /**
     * En una pantalla ancha el directorio se queda fijo a la izquierda, y eso
     * está bien para buscar un local; pero para MIRAR el plano —que es a lo que
     * se viene— le quita 19 rem. Se pliega, y la preferencia se recuerda: quien
     * lo cierra suele quererlo cerrado también la próxima vez.
     *
     * Es solo de escritorio: en el móvil el directorio ya es una hoja que se
     * recoge sola, y su tirador hace justo esto.
     */
    var botonPlegar = document.querySelector('[data-pm-toggle]');
    var LLAVE = 'mapDirectorio';

    function plegar(cerrado) {
        document.body.classList.toggle('pm-tight', cerrado);
        botonPlegar.setAttribute('aria-expanded', cerrado ? 'false' : 'true');
        botonPlegar.setAttribute('aria-label', cerrado ? T.open : T.shut);
        botonPlegar.setAttribute('title', cerrado ? T.open : T.shut);
        try { localStorage.setItem(LLAVE, cerrado ? '1' : '0'); } catch (e) {}
    }

    // En modo privado `localStorage` lanza al leer; sin preferencia, abierto.
    var guardado = '0';
    try { guardado = localStorage.getItem(LLAVE) || '0'; } catch (e) {}
    if (guardado === '1') { plegar(true); }

    botonPlegar.addEventListener('click', function () {
        plegar(!document.body.classList.contains('pm-tight'));
    });

    document.querySelector('[data-pm-grab]').addEventListener('click', function () {
        abrirMenu(!menu.classList.contains('is-open'));
    });
    busca.addEventListener('input', pintarMenu);
    pintarMenu();

    document.querySelector('[data-pm-in]').addEventListener('click', function () { visor.zoomBy(1.35); });
    document.querySelector('[data-pm-out]').addEventListener('click', function () { visor.zoomBy(1 / 1.35); });
    document.querySelector('[data-pm-fit]').addEventListener('click', function () { visor.fit(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cerrar(); } });
})();
</script>
</body>
</html>
