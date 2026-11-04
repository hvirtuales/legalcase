<?php
/**
 * Lienzo del directorio público — **autosuficiente** (contrato §7.3).
 *
 * Toda la hoja va en línea, los iconos son SVG en línea y no se carga `tailwind.css`,
 * ni `icons.css`, ni una sola URL externa. Si la carpeta del componente se suelta en
 * otra instalación, la vitrina sigue viéndose igual.
 *
 * **Lenguaje visual de leadslawyers.com**: fondo crema cálido con un resplandor dorado,
 * barra superior translúcida y fija, titulares en Cormorant Garamond y texto en Manrope,
 * botones de píldora, tarjetas de 22 px con un filete dorado y una banda navy con
 * tarjetas de vidrio. Navy `#0d2b57`/`#071a34` en superficies y titulares; el dorado
 * `#d0a24a` solo en acentos y en la insignia de verificado.
 *
 * **Las tipografías viajan con el componente** (`Assets/fonts`, licencia SIL OFL 1.1)
 * y las sirve `Asset::file` con lista blanca y caché larga: ni Google Fonts ni la hoja
 * `fonts.css` del núcleo. `font-display: swap` y una pila de respaldo con métricas
 * parecidas, para que el texto no espere a la descarga.
 *
 * Modo oscuro por `prefers-color-scheme` y no por un interruptor: quien llega desde
 * un buscador no viene a configurar nada. La referencia no tiene modo oscuro; aquí la
 * crema pasa a un navy casi negro y el dorado se aclara un punto para no vibrar.
 *
 * SEO: `noindex` por defecto y **las páginas que quieren salir en Google lo piden**.
 *
 * @var string      $pageTitle
 * @var string|null $description
 * @var string|null $canonical
 * @var string|null $robots
 */
$description = $description ?? '';
$canonical   = $canonical   ?? '';
$robots      = $robots      ?? 'noindex, nofollow';
$icon        = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($pageTitle) ?></title>
<?php /* Metadatos con `esc()` y no con `esc(..., 'attr')`: el escapador de atributos
         codifica todo lo no alfanumérico y deja la canónica ilegible al auditarla. */ ?>
<meta name="robots" content="<?= esc($robots) ?>">
<?php if ($description !== ''): ?>
<meta name="description" content="<?= esc($description) ?>">
<?php endif; ?>
<?php
/*
 * Una URL por idioma: el español es la de siempre y el inglés lleva `?lang=en`
 * (`PublicLocale`). La canónica apunta a la del idioma servido y los `hreflang` le
 * dicen al buscador que son la misma página traducida, no contenido duplicado.
 */
$pl = \Components\LeadFlow\Libraries\Directory\PublicLocale::class;
$lc = $pl::current();
?>
<?php if ($canonical !== ''): ?>
<link rel="canonical" href="<?= esc($pl::canonical($canonical)) ?>">
<link rel="alternate" hreflang="es" href="<?= esc($pl::canonical($canonical, 'es')) ?>">
<link rel="alternate" hreflang="en" href="<?= esc($pl::canonical($canonical, 'en')) ?>">
<link rel="alternate" hreflang="x-default" href="<?= esc($canonical) ?>">
<meta property="og:url" content="<?= esc($pl::canonical($canonical)) ?>">
<?php endif; ?>
<meta property="og:locale" content="<?= $lc === 'en' ? 'en_US' : 'es_CO' ?>">
<meta property="og:locale:alternate" content="<?= $lc === 'en' ? 'es_CO' : 'en_US' ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= esc(lang('Lfl.dirBrand')) ?>">
<meta property="og:title" content="<?= esc($pageTitle) ?>">
<?php if ($description !== ''): ?>
<meta property="og:description" content="<?= esc($description) ?>">
<?php endif; ?>
<link rel="preload" href="<?= base_url('lfl-recursos/cormorant-garamond-500-700.woff2') ?>" as="font" type="font/woff2" crossorigin>
<style>
@font-face{font-family:"Cormorant Garamond";font-style:normal;font-weight:500 700;font-display:swap;
  src:url("<?= base_url('lfl-recursos/cormorant-garamond-500-700.woff2') ?>") format("woff2")}
@font-face{font-family:"Manrope";font-style:normal;font-weight:400 800;font-display:swap;
  src:url("<?= base_url('lfl-recursos/manrope-400-800.woff2') ?>") format("woff2")}

:root{
  --ll-navy:#0d2b57; --ll-navy-deep:#071a34; --ll-navy-soft:#163f78; --ll-navy-bright:#1f4f93;
  --ll-gold:#d0a24a; --ll-gold-2:#e5b95f; --ll-gold-soft:#f1ddaf; --ll-gold-tint:rgba(208,162,74,.16);
  --ll-cream:#f7f3ea; --ll-bg-a:#fcfbf8; --ll-bg-b:#f1efe8;
  --ll-card:rgba(255,255,255,.92); --ll-card-solid:#ffffff; --ll-field-bg:#fbfaf6;
  --ll-ink:#11233f; --ll-head:#071a34; --ll-muted:rgba(17,35,63,.72); --ll-faint:rgba(17,35,63,.55);
  --ll-line:rgba(13,43,87,.09); --ll-field:rgba(13,43,87,.16); --ll-tint:rgba(13,43,87,.07);
  --ll-topbar:rgba(247,243,234,.82); --ll-on-dark:#ffffff;
  --ll-shadow:0 24px 60px rgba(10,26,50,.14); --ll-shadow-sm:0 14px 32px rgba(10,26,50,.08);
  --ll-r-xl:32px; --ll-r-lg:22px; --ll-r-md:16px;
  --ll-serif:"Cormorant Garamond",Georgia,"Times New Roman",serif;
  --ll-sans:"Manrope","Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif;
}
@media (prefers-color-scheme: dark){
  :root{
    --ll-navy:#9fc0ef; --ll-head:#f3ecdc; --ll-gold:#dcb25e; --ll-gold-2:#ecc679; --ll-gold-soft:#f1ddaf; --ll-gold-tint:rgba(220,178,94,.16);
    --ll-bg-a:#0a1424; --ll-bg-b:#08101d;
    --ll-card:rgba(19,31,52,.92); --ll-card-solid:#131f34; --ll-field-bg:#0f1a2c;
    --ll-ink:#e6ebf3; --ll-muted:rgba(230,235,243,.72); --ll-faint:rgba(230,235,243,.5);
    --ll-line:rgba(159,192,239,.13); --ll-field:rgba(159,192,239,.22); --ll-tint:rgba(159,192,239,.1);
    --ll-topbar:rgba(10,20,36,.82);
    --ll-shadow:0 24px 60px rgba(0,0,0,.45); --ll-shadow-sm:0 14px 32px rgba(0,0,0,.35);
  }
}
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%;scroll-behavior:smooth;scroll-padding-top:96px}
@media (prefers-reduced-motion: reduce){html{scroll-behavior:auto}}
/* Un SVG con viewBox y sin medida se estira al ancho del contenedor. */
svg{width:1em;height:1em;flex:0 0 auto}
body{margin:0;color:var(--ll-ink);font:16px/1.6 var(--ll-sans);
  background:radial-gradient(circle at top left,var(--ll-gold-tint),transparent 32%),linear-gradient(180deg,var(--ll-bg-a) 0%,var(--ll-bg-b) 100%);
  background-color:var(--ll-bg-b)}
img{max-width:100%;display:block}
a{color:var(--ll-navy);text-decoration:none}
a:hover{text-decoration:underline}
[hidden]{display:none!important}
h1,h2{font-family:var(--ll-serif);font-weight:600;color:var(--ll-head);letter-spacing:-.005em;line-height:1.05}
/* Titulares equilibrados: si necesitan dos líneas, las palabras se reparten parejas en vez
   de dejar una sola colgada abajo («… para su / caso»). Los navegadores que no lo
   soportan simplemente cortan como siempre. */
h1,h2,h3{text-wrap:balance}
h3{font-family:var(--ll-sans);font-weight:700;line-height:1.3;color:var(--ll-head)}

.ll-wrap{width:min(1180px,calc(100% - 32px));margin:0 auto}

/* Barra superior: translúcida, fija y desenfocada, como la de la referencia. */
.ll-head{position:sticky;top:0;z-index:30;background:var(--ll-topbar);border-bottom:1px solid var(--ll-line);
  -webkit-backdrop-filter:blur(18px);backdrop-filter:blur(18px)}
.ll-head .ll-wrap{display:flex;align-items:center;justify-content:space-between;gap:10px 16px;flex-wrap:wrap;padding:12px 0}
.ll-logo{display:flex;align-items:center;gap:12px;color:var(--ll-ink)}
.ll-logo:hover{text-decoration:none}
/* El logo a pelo (PNG transparente). Alto fijo y ancho por proporción (300×236). */
.ll-logo-mark{display:block;flex:0 0 auto}
.ll-logo-mark img{display:block;width:auto;height:52px}
@media (max-width:860px){.ll-logo-mark img{height:44px}}
.ll-logo-copy{display:flex;flex-direction:column;line-height:1.25}
.ll-logo-copy strong{font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--ll-navy)}
.ll-logo-copy span{font-size:14px;color:var(--ll-muted)}
.ll-nav{display:flex;align-items:center;flex-wrap:wrap;gap:2px;justify-content:flex-end}
.ll-nav a{padding:8px 12px;border-radius:999px;color:var(--ll-muted);font-size:14px;font-weight:500}
.ll-nav a:hover,.ll-nav a:focus-visible{background:var(--ll-tint);text-decoration:none;outline:none}
.ll-nav a.is-here{color:var(--ll-head);font-weight:700;background:var(--ll-tint)}
.ll-nav .ll-btn{padding:10px 17px;margin-left:4px;color:#fff}
.ll-nav .ll-btn-ghost{color:var(--ll-navy)}
/* Selector de idioma: una pastilla con las dos opciones; la activa en navy. */
.ll-lang{display:inline-flex;align-items:center;gap:2px;margin:0 4px;padding:3px;border:1px solid var(--ll-line);border-radius:999px;background:var(--ll-card)}
.ll-nav .ll-lang a{padding:5px 10px;border-radius:999px;font-size:12.5px;font-weight:800;letter-spacing:.06em;color:var(--ll-muted)}
.ll-nav .ll-lang a.is-on{background:linear-gradient(135deg,#0d2b57,#163f78);color:#fff}
.ll-nav .ll-lang a:hover{text-decoration:none}

/* El lema cede antes que el menú: logo + lema + seis entradas no caben en 1180 px. */
@media (max-width:1240px){.ll-logo-copy span{display:none}}

/* Menú hamburguesa (≤ 860 px). Sin JavaScript no hay botón y el menú queda desplegado
   como siempre: la clase `ll-js` la pone el propio script, así que nada se esconde
   si el script no corre. */
.ll-burger{display:none;place-items:center;width:46px;height:46px;border:1px solid var(--ll-line);border-radius:14px;
  background:var(--ll-card);color:var(--ll-head);cursor:pointer;box-shadow:var(--ll-shadow-sm)}
.ll-burger svg{width:22px;height:22px}
.ll-burger .ll-burger-x{display:none}
.ll-head.is-open .ll-burger .ll-burger-x{display:block}
.ll-head.is-open .ll-burger .ll-burger-bars{display:none}
.ll-burger:focus-visible{outline:2px solid var(--ll-gold);outline-offset:2px}
@media (max-width:860px){
  .ll-head .ll-wrap{justify-content:center}
  .ll-nav{justify-content:center}
  .ll-logo-copy span{display:none}
  .ll-js .ll-head .ll-wrap{justify-content:space-between;flex-wrap:wrap}
  .ll-js .ll-burger{display:inline-grid}
  .ll-js .ll-nav{display:none;flex-basis:100%;flex-direction:column;align-items:stretch;gap:4px;padding:6px 0 14px}
  .ll-js .ll-head.is-open .ll-nav{display:flex}
  .ll-js .ll-nav a{padding:13px 16px;border-radius:var(--ll-r-md);font-size:16px;color:var(--ll-ink)}
  .ll-js .ll-nav .ll-btn{margin:6px 0 0;justify-content:center}
  .ll-js .ll-nav .ll-btn:not(.ll-btn-ghost){color:#fff}
  .ll-js .ll-nav .ll-lang{align-self:center;margin:8px 0 2px}
  .ll-js .ll-nav .ll-lang a{padding:8px 18px;font-size:14px}
}

.ll-main{padding:34px 0 40px}
/* En la portada el hero ya separa; las secciones traen su propio aire. */
.ll-hero + .ll-main{padding-top:0}
.ll-card{background:var(--ll-card);border:1px solid var(--ll-line);border-radius:var(--ll-r-lg);box-shadow:var(--ll-shadow-sm)}
.ll-pad{padding:22px}
.ll-sub{color:var(--ll-muted);margin:.25rem 0 1.25rem}
.ll-small{font-size:13.5px}
.ll-muted{color:var(--ll-muted)}
.ll-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* Botones de píldora: navy la acción principal, dorado el acento, tinte la secundaria. */
.ll-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 22px;border:0;border-radius:999px;
  background:linear-gradient(135deg,#0d2b57 0%,#163f78 100%);color:#fff;font:inherit;font-weight:700;cursor:pointer;
  box-shadow:0 14px 30px rgba(13,43,87,.24);transition:transform .16s ease,box-shadow .16s ease}
.ll-btn:hover,.ll-btn:focus-visible{text-decoration:none;transform:translateY(-1px);outline:none}
.ll-btn:focus-visible{box-shadow:0 0 0 3px var(--ll-gold-tint),0 14px 30px rgba(13,43,87,.24)}
.ll-btn-gold{background:linear-gradient(135deg,var(--ll-gold) 0%,var(--ll-gold-2) 100%);color:#071a34;box-shadow:0 14px 30px rgba(208,162,74,.28)}
.ll-btn-ghost{background:var(--ll-tint);color:var(--ll-navy);box-shadow:none}
.ll-btn-quiet{background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.28);box-shadow:none}
.ll-btn-sm{padding:9px 16px;font-size:14px}
.ll-btn-block{width:100%}
@media (prefers-color-scheme: dark){.ll-btn{box-shadow:none}.ll-btn-ghost{color:#dbe7f8}}
@media (prefers-reduced-motion: reduce){.ll-btn{transition:none}.ll-btn:hover{transform:none}}

.ll-kicker{display:inline-flex;align-items:center;gap:8px;padding:6px 13px;border-radius:999px;background:var(--ll-gold-tint);
  color:var(--ll-navy);font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}

/* Buscador del hero y del listado. */
/* Fila flexible y no rejilla fija: la portada trae tres campos y el buscador cuatro
   (suma la jurisdicción); con columnas fijas el botón se salía. */
.ll-search{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.ll-search > .ll-field{flex:1 1 170px}
.ll-search > .ll-field:first-child{flex:2 1 240px}
.ll-search > .ll-btn{flex:0 0 auto}
@media (max-width:759px){.ll-search > *{flex:1 1 100%!important}}
.ll-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.ll-label{font-size:13.5px;font-weight:700;color:var(--ll-navy)}
.ll-input,.ll-select,.ll-textarea{width:100%;padding:13px 15px;border:1px solid var(--ll-field);border-radius:var(--ll-r-md);
  background:var(--ll-field-bg);color:var(--ll-ink);font:inherit}
.ll-textarea{min-height:120px;resize:vertical}
.ll-input:focus,.ll-select:focus,.ll-textarea:focus{outline:2px solid var(--ll-gold-tint);border-color:var(--ll-gold)}
.ll-req{color:#b42318}
@media (prefers-color-scheme: dark){.ll-req{color:#f4a39a}}

.ll-sec{margin:0 0 40px}
.ll-sec-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:16px}
.ll-sec-head > a{white-space:nowrap;flex:0 0 auto}
.ll-sec-head h2,.ll-sec-head h3{margin:0;font-family:var(--ll-serif);font-weight:600;font-size:clamp(26px,3vw,32px);color:var(--ll-head)}

.ll-grid{display:grid;gap:16px;grid-template-columns:1fr}
@media (min-width:620px){.ll-grid{grid-template-columns:repeat(2,1fr)}}
@media (min-width:920px){.ll-grid{grid-template-columns:repeat(3,1fr)}}

/* Rejilla de áreas: son puertas de entrada, no tarjetas de contenido. */
.ll-areas{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}
.ll-area{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:15px 18px;background:var(--ll-card);
  border:1px solid var(--ll-line);border-radius:var(--ll-r-md);color:var(--ll-ink)}
.ll-area:hover{text-decoration:none;border-color:var(--ll-gold)}
.ll-area-n{font-weight:700}
.ll-area-c{font-size:13px;color:var(--ll-muted)}

/* Tarjeta de perfil. */
.ll-pro{display:flex;flex-direction:column;gap:12px;padding:20px}
.ll-pro-top{display:flex;gap:12px;align-items:flex-start}
.ll-avatar{width:56px;height:56px;border-radius:50%;flex:0 0 auto;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,#0d2b57,#163f78);color:#fff;font-weight:700;font-size:19px;overflow:hidden}
.ll-avatar img{width:100%;height:100%;object-fit:cover}
.ll-pro-name{margin:0;font-family:var(--ll-serif);font-size:22px;font-weight:600}
.ll-tags{display:flex;gap:6px;flex-wrap:wrap}
.ll-tag{padding:4px 10px;border-radius:999px;background:var(--ll-tint);font-size:12px;color:var(--ll-ink)}
.ll-verified{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;
  background:var(--ll-gold-tint);color:var(--ll-navy);font-size:12px;font-weight:700}
.ll-verified svg{color:var(--ll-gold)}
.ll-featured{position:absolute;top:12px;right:12px;padding:4px 10px;border-radius:999px;
  background:linear-gradient(135deg,var(--ll-gold),var(--ll-gold-2));color:#071a34;font-size:11px;font-weight:800}
.ll-pro-foot{margin-top:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center;border-top:1px solid var(--ll-line);padding-top:12px}

.ll-split{display:grid;gap:18px;grid-template-columns:1fr}
@media (min-width:900px){.ll-split{grid-template-columns:2fr 1fr;align-items:start}}

.ll-band{background:var(--ll-card);border:1px solid var(--ll-line);border-radius:var(--ll-r-lg);padding:20px 22px;box-shadow:var(--ll-shadow-sm);
  display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:space-between;position:relative;overflow:hidden}
.ll-band::after{content:"";position:absolute;inset:auto 22px 0 22px;height:4px;border-radius:999px;background:linear-gradient(90deg,var(--ll-gold),transparent)}
.ll-list{margin:0;padding-left:18px}
.ll-list li{margin:.35rem 0}
.ll-alert{padding:13px 16px;border-radius:var(--ll-r-md);border:1px solid var(--ll-line);background:var(--ll-card);margin-bottom:16px}
.ll-alert-bad{border-color:#f0c2bd;background:#fdf3f2;color:#8a2018}
@media (prefers-color-scheme: dark){.ll-alert-bad{border-color:#5c211b;background:#2a1412;color:#f4b4ad}}
.ll-empty{padding:28px;text-align:center}

/* La trampa del formulario va fuera de la pantalla, no oculta: algunos navegadores
   no autocompletan lo que está en `display:none` y entonces no atrapa nada. */
.ll-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}

.ll-foot{border-top:1px solid var(--ll-line);padding:28px 0 34px;margin-top:20px;color:var(--ll-muted)}
.ll-foot .ll-wrap{display:flex;gap:18px;flex-wrap:wrap;justify-content:space-between;align-items:flex-start}
.ll-foot strong{font-family:var(--ll-serif);font-size:22px;font-weight:600;color:var(--ll-head)}
.ll-foot-links{display:flex;gap:6px 18px;flex-wrap:wrap;font-size:14px}
.ll-foot-links a{color:var(--ll-muted)}
.ll-legal{max-width:70ch;font-size:12.5px;color:var(--ll-faint);margin:8px 0 0}
.ll-pag{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}

/* ───────────────────────── Micrositio de la firma ─────────────────────────
   Dos columnas: a la izquierda lo que se LEE —quién es, qué hace, quién la
   forma—, a la derecha lo que se USA —contacto, QR, consulta—. La derecha
   acompaña (`sticky`) mientras se recorren los abogados. En móvil la derecha
   cae ENTERA debajo: subir el contacto con `order` parte en dos la lectura. */
.ll-biz{display:grid;gap:18px;grid-template-columns:1fr}
@media (min-width:900px){
  .ll-biz{grid-template-columns:minmax(0,3fr) minmax(0,1fr);align-items:start}
  .ll-biz-aside{position:sticky;top:96px}
}
.ll-biz-col{display:flex;flex-direction:column;gap:18px;min-width:0}
.ll-biz-aside{display:flex;flex-direction:column;gap:18px;min-width:0}
.ll-blk{padding:20px}
.ll-blk > h2{margin:0;font-size:24px;display:flex;align-items:center;gap:8px}
.ll-blk-rule{height:1px;background:var(--ll-line);margin:12px 0 0}
.ll-biz-cover{aspect-ratio:16/6;background:linear-gradient(125deg,#071a34,#0d2b57 55%,#1f4f93);overflow:hidden;border-radius:var(--ll-r-lg) var(--ll-r-lg) 0 0}
.ll-biz-cover img{width:100%;height:100%;object-fit:cover}
.ll-biz-stripe{height:4px}
.ll-biz-id{display:flex;align-items:center;gap:14px;padding:16px 20px}
.ll-biz-logo{position:relative;z-index:1;display:grid;place-items:center;width:64px;height:64px;flex:0 0 auto;border-radius:16px;
  border:1px solid var(--ll-line);background:#fff;overflow:hidden;font-weight:700;font-size:20px;color:#0d2b57}
.ll-biz-logo img{width:100%;height:100%;object-fit:contain}
.ll-svc{list-style:none;margin:0;padding:0}
.ll-svc li{padding:12px 0;border-top:1px solid var(--ll-line)}
.ll-svc li:first-child{border-top:0}
.ll-svc-n{font-weight:700;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ll-kv{margin:0;display:grid;gap:10px}
.ll-kv dt{font-size:12.5px;color:var(--ll-muted)}
.ll-kv dd{margin:0;font-weight:700;word-break:break-word}

/* Módulo de calificación. */
.ll-rate{display:grid;gap:16px;grid-template-columns:1fr;align-items:center;padding:14px 0;border-top:1px solid var(--ll-line)}
@media (min-width:560px){.ll-rate{grid-template-columns:auto 1fr;gap:24px}}
.ll-rate-score{text-align:center}
.ll-rate-big{margin:0;font-family:var(--ll-serif);font-size:46px;font-weight:700;line-height:1}
.ll-rate-score .ll-row{justify-content:center}
.ll-rate-bars{display:grid;gap:5px;min-width:0}
.ll-rate-row{display:grid;grid-template-columns:14px 1fr 28px;align-items:center;gap:8px}
.ll-rate-n{font-size:12px;color:var(--ll-muted);text-align:right}
.ll-rate-c{font-size:12px;color:var(--ll-muted)}
.ll-rate-bar{height:7px;border-radius:999px;background:var(--ll-tint);overflow:hidden}
.ll-rate-fill{display:block;height:100%;background:linear-gradient(90deg,var(--ll-gold),var(--ll-gold-2))}

.ll-qr{text-align:center}
.ll-qr img{width:150px;height:150px;margin:0 auto;border-radius:12px;background:#fff;padding:6px}

/* ───────────────────────── Portada ─────────────────────────
   Estructura de leadslawyers.com/landing: hero con tarjeta visual, secciones con
   titular grande en Cormorant, tarjetas con filete dorado, banda navy con tarjetas
   de vidrio y un panel de llamado final con la acción en dorado. */
.ll-hero{padding:56px 0 34px}
.ll-hero-grid{display:grid;gap:28px;grid-template-columns:1fr;align-items:stretch}
@media (min-width:980px){.ll-hero-grid{grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr)}}
.ll-hero h1{margin:18px 0 18px;font-size:clamp(44px,6vw,82px);line-height:.98;max-width:15ch}
.ll-lede{margin:0;max-width:58ch;font-size:17.5px;line-height:1.75;color:var(--ll-muted)}
.ll-actions{display:flex;flex-wrap:wrap;gap:12px;margin:28px 0 0}
.ll-hero-note{margin:16px 0 0;font-size:14.5px;color:var(--ll-muted)}
.ll-hero-note a{font-weight:700;text-decoration:underline;text-underline-offset:3px}
.ll-stats{display:grid;gap:14px;grid-template-columns:1fr;margin-top:30px}
@media (min-width:640px){.ll-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
.ll-stat{padding:16px 18px;border-radius:var(--ll-r-md);background:var(--ll-card);border:1px solid var(--ll-line);box-shadow:var(--ll-shadow-sm)}
.ll-stat strong{display:block;font-family:var(--ll-serif);font-size:25px;font-weight:600;line-height:1.1;color:var(--ll-head);margin-bottom:4px}
.ll-stat span{font-size:14px;color:var(--ll-muted)}

/* Tarjeta visual: la foto del componente a sangre, la marca arriba y el panel de
   estándares empujado al fondo con `margin-top:auto` (columna flexible, sin posiciones
   absolutas para el texto: así no se pisan con ninguna altura). El degradado navy de
   fondo es lo que se ve mientras la foto carga. */
.ll-visual{position:relative;isolation:isolate;overflow:hidden;min-height:560px;display:flex;flex-direction:column;align-items:flex-start;
  padding:18px;border-radius:var(--ll-r-xl);box-shadow:var(--ll-shadow);
  background:linear-gradient(150deg,#071a34 0%,#0d2b57 55%,#1f4f93 100%)}
.ll-visual-img{position:absolute;inset:0;z-index:-2;width:100%;height:100%;object-fit:cover;object-position:50% 22%}
/* Velo solo abajo: arriba la foto respira; donde va el panel, ya casi navy. */
.ll-visual::after{content:"";position:absolute;inset:0;z-index:-1;
  background:linear-gradient(180deg,rgba(7,26,52,.10) 0%,rgba(7,26,52,.05) 35%,rgba(7,26,52,.55) 70%,rgba(7,26,52,.9) 100%)}
.ll-visual-brand{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;
  background:rgba(7,26,52,.78);color:#f1ddaf;font-size:12.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;
  -webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)}
.ll-visual-brand svg{width:18px;height:18px}
.ll-visual-panel{margin-top:auto;align-self:stretch;padding:20px 22px;border-radius:20px;color:#fff;
  background:linear-gradient(135deg,rgba(7,26,52,.94),rgba(13,43,87,.88));box-shadow:0 18px 36px rgba(7,26,52,.34)}
.ll-visual-panel strong{display:block;margin-bottom:10px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#f1ddaf}
.ll-visual-panel ul{list-style:none;margin:0;padding:0;display:grid;gap:9px}
.ll-visual-panel li{display:flex;gap:10px;align-items:flex-start;font-size:14.5px;line-height:1.5;color:rgba(255,255,255,.9)}
.ll-visual-panel li svg{width:17px;height:17px;margin-top:2px;color:#d0a24a}
@media (max-width:979px){.ll-visual{min-height:520px}}

/* ── Fotos de fondo ──
   `.ll-photo` aísla el apilado; la foto (`.ll-photo-bg`) va a z-index -2 y el velo
   (`::before`) a -1, ambos por encima del fondo propio del bloque y por debajo del texto.
   El velo nunca baja de .86 donde hay texto blanco: con eso el contraste queda por encima
   de 4,5:1 aun sobre la zona más clara de la imagen. */
.ll-photo{position:relative;isolation:isolate;overflow:hidden}
.ll-photo-bg{position:absolute;inset:0;z-index:-2;width:100%;height:100%;object-fit:cover}
.ll-photo::before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;
  background:linear-gradient(125deg,rgba(7,26,52,.95) 0%,rgba(13,43,87,.9) 55%,rgba(19,56,108,.86) 100%)}

/* Foto en cabecera de tarjeta (pilares): a sangre, sin velo; el texto va debajo. */
.ll-feature.ll-feature-photo{padding-top:0}
/* `height:auto` es imprescindible: el `height="900"` del HTML (que reserva el hueco y evita
   saltos al cargar) manda sobre `aspect-ratio` si no se anula, y la foto salía altísima. */
.ll-feature-media{display:block;width:calc(100% + 44px);max-width:none;height:auto;margin:0 -22px 4px;aspect-ratio:16/10;object-fit:cover}

/* Texto y foto lado a lado (verificación). */
.ll-media-split{display:grid;gap:28px;grid-template-columns:1fr;align-items:center;margin-bottom:26px}
@media (min-width:980px){.ll-media-split{grid-template-columns:1.05fr .95fr}}
.ll-media-split .ll-intro{margin-bottom:0}
.ll-media{position:relative;margin:0;overflow:hidden;aspect-ratio:4/3;border-radius:var(--ll-r-xl);box-shadow:var(--ll-shadow)}
.ll-media img{width:100%;height:100%;object-fit:cover;object-position:50% 30%}
.ll-media::after{content:"";position:absolute;inset:auto 28px 0 28px;height:4px;border-radius:999px;background:linear-gradient(90deg,var(--ll-gold),transparent)}

/* Velos DIRECCIONALES: densos donde va el texto, ligeros donde no, para que la foto se vea.
   Cada uno respeta el mínimo de .86 bajo el texto blanco. */
/* Portal: el texto a la izquierda; a la derecha las tarjetas de vidrio llevan su propio
   fondo navy (.6), que sumado al velo (.68) deja su texto blanco por encima de 4,5:1. */
.ll-photo-left::before{background:linear-gradient(90deg,rgba(7,26,52,.95) 0%,rgba(7,26,52,.9) 42%,rgba(13,43,87,.68) 70%,rgba(13,43,87,.6) 100%)}
.ll-photo-left .ll-glass{background:rgba(7,26,52,.6);border-color:rgba(255,255,255,.16);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px)}
@media (max-width:979px){.ll-photo-left::before{background:linear-gradient(180deg,rgba(7,26,52,.94),rgba(13,43,87,.88))}}
/* Directorio: el titular arriba a la izquierda; el buscador va en su tarjeta blanca. */
.ll-photo-top::before{background:linear-gradient(160deg,rgba(7,26,52,.94) 0%,rgba(7,26,52,.88) 40%,rgba(13,43,87,.62) 100%)}
/* Llamado final: el texto abajo; arriba la foto respira. */
.ll-photo-bottom::before{background:linear-gradient(180deg,rgba(7,26,52,.25) 0%,rgba(7,26,52,.5) 30%,rgba(7,26,52,.88) 52%,rgba(7,26,52,.95) 100%)}

/* Banda del directorio: titular blanco sobre la foto y el buscador en su tarjeta. */
.ll-photo-band{padding:38px 36px;margin-bottom:30px;border-radius:var(--ll-r-xl);background:#071a34;box-shadow:var(--ll-shadow)}
/* En la banda el titular usa todo el ancho: el tope de 860 px de `.ll-intro` lo cortaba antes de tiempo. */
.ll-photo-band .ll-intro{max-width:none}
.ll-photo-band .ll-intro p{max-width:64ch}
.ll-photo-band .ll-intro h2{color:#fff}
.ll-photo-band .ll-intro p{color:rgba(255,255,255,.88)}
@media (max-width:620px){.ll-photo-band{padding:26px 16px}}

/* Llamado final: la columna de texto sobre foto. */
.ll-cta-photo{display:flex;flex-direction:column;justify-content:flex-end;min-height:420px;padding:30px;border-radius:24px;background:#071a34;color:#fff}
.ll-cta .ll-cta-photo h2{color:#fff}
.ll-cta .ll-cta-photo p{color:rgba(255,255,255,.88)}
.ll-cta-photo .ll-kicker{align-self:flex-start;background:rgba(208,162,74,.22);color:#f1ddaf}
@media (max-width:620px){.ll-cta-photo{min-height:300px;padding:24px 20px}}

.ll-section{padding:34px 0 26px}
.ll-intro{display:grid;gap:10px;margin-bottom:26px;max-width:860px}
.ll-intro h2{margin:0;font-size:clamp(34px,4.2vw,56px)}
.ll-intro p{margin:0;max-width:64ch;line-height:1.75;color:var(--ll-muted)}

.ll-cards{display:grid;gap:16px;grid-template-columns:1fr}
@media (min-width:760px){.ll-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (min-width:980px){.ll-cards{grid-template-columns:repeat(3,minmax(0,1fr))}.ll-cards-4{grid-template-columns:repeat(4,minmax(0,1fr))}}
.ll-feature{position:relative;overflow:hidden;padding:24px 22px 28px;border-radius:var(--ll-r-lg);background:var(--ll-card);
  border:1px solid var(--ll-line);box-shadow:var(--ll-shadow-sm)}
.ll-feature::after{content:"";position:absolute;inset:auto 22px 0 22px;height:4px;border-radius:999px;background:linear-gradient(90deg,var(--ll-gold),transparent)}
.ll-feature h3{margin:14px 0 8px;font-size:17px}
.ll-feature p{margin:0;font-size:15px;line-height:1.7;color:var(--ll-muted)}
.ll-ic{display:inline-grid;place-items:center;width:44px;height:44px;border-radius:14px;background:var(--ll-gold-tint);color:var(--ll-navy)}
.ll-ic svg{width:22px;height:22px}
.ll-num{display:block;font-family:var(--ll-serif);font-size:46px;font-weight:600;line-height:1;color:var(--ll-gold)}

/* Banda navy con tarjetas de vidrio. */
.ll-dark{padding:36px;border-radius:var(--ll-r-xl);color:#fff;box-shadow:var(--ll-shadow);
  background:linear-gradient(125deg,#071a34,#0d2b57 55%,#1f4f93 100%)}
.ll-dark-grid{display:grid;gap:26px;grid-template-columns:1fr;align-items:start}
@media (min-width:980px){.ll-dark-grid{grid-template-columns:1.05fr .95fr}}
.ll-dark h2{margin:12px 0 14px;font-size:clamp(32px,4vw,52px);color:#fff}
.ll-dark p{margin:0 0 14px;line-height:1.75;color:rgba(255,255,255,.86)}
.ll-dark .ll-kicker{background:rgba(208,162,74,.2);color:#f1ddaf}
.ll-dark .ll-small{color:rgba(255,255,255,.7)}
.ll-glass-grid{display:grid;gap:14px;grid-template-columns:1fr}
@media (min-width:560px){.ll-glass-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
.ll-glass{padding:20px;border-radius:var(--ll-r-lg);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14)}
.ll-glass strong{display:block;margin-bottom:8px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#f1ddaf}
.ll-glass p{margin:0;font-size:15px;line-height:1.65;color:rgba(255,255,255,.86)}
@media (max-width:620px){.ll-dark{padding:24px 18px}}

.ll-note{display:flex;gap:8px;align-items:center;margin:18px 0 0;font-size:14px;color:var(--ll-muted)}
.ll-note svg{color:var(--ll-gold)}

/* Panel del llamado final: texto a la izquierda y la tarjeta de acción a la derecha. */
.ll-cta{display:grid;gap:22px;grid-template-columns:1fr;padding:26px;border-radius:var(--ll-r-xl);border:1px solid var(--ll-line);box-shadow:var(--ll-shadow);
  background:linear-gradient(180deg,var(--ll-card),var(--ll-card))}
@media (min-width:980px){.ll-cta{grid-template-columns:1.02fr .98fr;padding:34px}}
.ll-cta h2{margin:14px 0 12px;font-size:clamp(32px,3.6vw,46px)}
.ll-cta p{margin:0;line-height:1.75;color:var(--ll-muted)}
.ll-action{display:flex;flex-direction:column;gap:12px;justify-content:center;padding:22px;border-radius:24px;background:var(--ll-card-solid);border:1px solid var(--ll-line)}
.ll-trust{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-radius:var(--ll-r-md);background:var(--ll-tint);border:1px solid var(--ll-line)}
.ll-trust svg{width:22px;height:22px;margin-top:2px;color:var(--ll-gold)}
.ll-trust strong{display:block;font-size:15px;color:var(--ll-head)}
.ll-trust span{font-size:14px;color:var(--ll-muted)}
.ll-action .ll-btn{padding:16px 22px;font-size:16.5px}
.ll-fine{margin:0;text-align:center;font-size:13px;color:var(--ll-faint)}
</style>
<?php /* Antes del primer pintado: así el menú móvil nace plegado y no parpadea abierto. */ ?>
<script>document.documentElement.classList.add('ll-js');</script>
</head>
<body>
<?php
/*
 * El portal de clientes es de LegalCase, otro componente: solo se enlaza si su
 * controlador existe. El Autoload solo registra los componentes ACTIVOS, así que
 * desactivar LegalCase quita el enlace sin tocar esta vista (§0.4: sin dependencia).
 */
$hasPortal = class_exists('Components\LegalCase\Controllers\ClientPortal');
?>
<header class="ll-head">
  <div class="ll-wrap">
    <?php
    /*
     * El logo de LegalCase (Assets/img), limpio y sin fondo: ni baldosa ni sombra. La «L»
     * es navy y en modo oscuro desaparecería sobre la barra, así que `<picture>` sirve la
     * variante con el navy pasado a crema (el dorado no cambia).
     *
     * Ya no usa el logo de Configuración del sistema: era un JPG sobre fondo blanco, que
     * es justo la caja que había que esconder con la baldosa.
     */
    ?>
    <a class="ll-logo" href="<?= base_url('/') ?>">
      <picture class="ll-logo-mark">
        <source srcset="<?= base_url('lfl-recursos/logo-dark.png') ?>" media="(prefers-color-scheme: dark)">
        <img src="<?= base_url('lfl-recursos/logo.png') ?>" alt="" width="300" height="236">
      </picture>
      <span class="ll-logo-copy">
        <strong><?= esc(lang('Lfl.dirBrand')) ?></strong>
        <span><?= esc(lang('Lfl.dirTagline')) ?></span>
      </span>
    </a>
    <button class="ll-burger" type="button" aria-expanded="false" aria-controls="ll-nav"
      aria-label="<?= esc(lang('Lfl.dirMenuOpen'), 'attr') ?>"
      data-open="<?= esc(lang('Lfl.dirMenuOpen'), 'attr') ?>" data-close="<?= esc(lang('Lfl.dirMenuClose'), 'attr') ?>">
      <svg class="ll-burger-bars" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      <svg class="ll-burger-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
    <nav class="ll-nav" id="ll-nav" aria-label="<?= esc(lang('Lfl.dirNavLabel'), 'attr') ?>">
      <?php
      // El enlace de la sección en la que está el visitante queda marcado (`aria-current`).
      $here = trim((string) service('request')->getUri()->getPath(), '/');
      $on   = static fn (string $p): string => ($here === $p || str_starts_with($here, $p . '/')) ? ' aria-current="page" class="is-here"' : '';
      ?>
      <a href="<?= base_url('firmas') ?>"<?= $on('firmas') ?>><?= esc(lang('Lfl.dirNavFirms')) ?></a>
      <a href="<?= base_url('buscar') ?>"<?= $on('buscar') ?>><?= esc(lang('Lfl.dirNavLawyers')) ?></a>
      <?php if ($hasPortal): ?>
        <a href="<?= base_url('legalcase-portal') ?>"><?= esc(lang('Lfl.dirNavClients')) ?></a>
      <?php endif; ?>
      <a class="ll-btn" href="<?= base_url('registro-legal') ?>"><?= esc(lang('Lfl.suCta')) ?></a>
      <a class="ll-btn ll-btn-ghost" href="<?= base_url('login') ?>"><?= esc(lang('Lfl.dirNavSignIn')) ?></a>
      <?php // Selector de idioma, al final de la fila: enlaces a la URL de esta misma página en cada idioma. ?>
      <span class="ll-lang" role="group" aria-label="<?= esc(lang('Lfl.dirLangLabel'), 'attr') ?>">
        <?php foreach (['es' => 'ES', 'en' => 'EN'] as $code => $txt): ?>
          <a href="<?= esc($pl::url($code), 'attr') ?>" hreflang="<?= esc($code, 'attr') ?>" lang="<?= esc($code, 'attr') ?>"
             <?= $lc === $code ? 'aria-current="true" class="is-on"' : '' ?>
             title="<?= esc(lang('Lfl.dirLang_' . $code), 'attr') ?>"><?= esc($txt) ?></a>
        <?php endforeach; ?>
      </span>
    </nav>
  </div>
</header>

<?= $this->renderSection('hero') ?>

<main class="ll-main">
  <div class="ll-wrap">
    <?= $this->renderSection('content') ?>
  </div>
</main>

<footer class="ll-foot">
  <div class="ll-wrap">
    <div>
      <strong><?= esc(lang('Lfl.dirBrand')) ?></strong>
      <p class="ll-legal"><?= esc(lang('Lfl.dirDisclaimer')) ?></p>
    </div>
    <nav class="ll-foot-links" aria-label="<?= esc(lang('Lfl.dirBrand'), 'attr') ?>">
      <a href="<?= base_url('/') ?>#verificacion"><?= esc(lang('Lfl.dirNavForLawyers')) ?></a>
      <a href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirNavFind')) ?></a>
      <a href="<?= base_url('firmas') ?>"><?= esc(lang('Lfl.dirNavFirms')) ?></a>
      <a href="<?= base_url('registro-legal') ?>"><?= esc(lang('Lfl.suCta')) ?></a>
      <a href="<?= base_url('login') ?>"><?= esc(lang('Lfl.dirNavLogin')) ?></a>
    </nav>
  </div>
</footer>
<script>
/* Menú hamburguesa: abre y cierra, y se cierra solo al elegir un enlace, con Escape o al
   volver a escritorio (si no, quedaría «abierto» por debajo y reaparecería al achicar). */
(function () {
  var head = document.querySelector('.ll-head'), btn = head && head.querySelector('.ll-burger');
  if (!btn) { return; }
  function set(open) {
    head.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.setAttribute('aria-label', btn.getAttribute(open ? 'data-close' : 'data-open'));
  }
  btn.addEventListener('click', function () { set(!head.classList.contains('is-open')); });
  head.querySelectorAll('.ll-nav a').forEach(function (a) { a.addEventListener('click', function () { set(false); }); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && head.classList.contains('is-open')) { set(false); btn.focus(); }
  });
  window.matchMedia('(min-width: 861px)').addEventListener('change', function (m) { if (m.matches) { set(false); } });
})();
</script>
</body>
</html>
