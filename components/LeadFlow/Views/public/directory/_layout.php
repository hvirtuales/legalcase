<?php
/**
 * Lienzo del directorio público — **autosuficiente** (contrato §7.3).
 *
 * Toda la hoja va en línea, los iconos son SVG en línea y la tipografía es la del
 * sistema: esta página no carga `tailwind.css`, ni `icons.css`, ni una sola URL
 * externa. Si la carpeta del componente se suelta en otra instalación, la vitrina
 * sigue viéndose igual.
 *
 * **La paleta es la de la marca**: navy `#0b1f44` en superficies y encabezados, y el
 * dorado `#bf8f23` reservado para acciones y para la insignia de verificado — si el
 * dorado pinta también títulos y bordes deja de señalar nada. En oscuro los dos se
 * aclaran: el navy sobre negro pierde el contraste que lo hace legible y el dorado
 * puro vibra.
 *
 * Modo oscuro por `prefers-color-scheme` y no por un interruptor: quien llega desde
 * un buscador no viene a configurar nada.
 *
 * SEO: `noindex` por defecto y **las páginas que quieren salir en Google lo piden**.
 * Es el mismo criterio que el resto de la plataforma; aquí casi todas lo piden, pero
 * el formulario de consulta y el acuse no.
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
<?php if ($canonical !== ''): ?>
<link rel="canonical" href="<?= esc($canonical) ?>">
<meta property="og:url" content="<?= esc($canonical) ?>">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= esc(lang('Lfl.dirBrand')) ?>">
<meta property="og:title" content="<?= esc($pageTitle) ?>">
<?php if ($description !== ''): ?>
<meta property="og:description" content="<?= esc($description) ?>">
<?php endif; ?>
<style>
:root{
  --ll-navy:#0b1f44; --ll-navy-soft:#16305f; --ll-gold:#bf8f23; --ll-gold-soft:#f6efdd;
  --ll-bg:#f7f8fa; --ll-card:#ffffff; --ll-ink:#11151c; --ll-muted:#5b6472;
  --ll-line:#e3e6ec; --ll-field:#c7ccd6; --ll-on-dark:#ffffff;
  --ll-radius:14px; --ll-shadow:0 1px 2px rgba(11,31,68,.06),0 10px 28px rgba(11,31,68,.07);
}
@media (prefers-color-scheme: dark){
  :root{
    --ll-navy:#12305f; --ll-navy-soft:#1b3f79; --ll-gold:#d8a93a; --ll-gold-soft:#2a2210;
    --ll-bg:#0a0e16; --ll-card:#141a24; --ll-ink:#e8eaee; --ll-muted:#98a2b3;
    --ll-line:#242c39; --ll-field:#333d4d;
    --ll-shadow:0 1px 2px rgba(0,0,0,.5),0 10px 28px rgba(0,0,0,.35);
  }
}
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
/* Un SVG con viewBox y sin medida se estira al ancho del contenedor. */
svg{width:1em;height:1em;flex:0 0 auto}
body{margin:0;background:var(--ll-bg);color:var(--ll-ink);
  font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
img{max-width:100%;display:block}
a{color:var(--ll-navy);text-decoration:none}
@media (prefers-color-scheme: dark){a{color:#9dc0ff}}
a:hover{text-decoration:underline}
[hidden]{display:none!important}
h1,h2,h3{line-height:1.25;letter-spacing:-.01em}

.ll-wrap{max-width:1080px;margin:0 auto;padding:0 16px}
.ll-head{background:var(--ll-navy);color:var(--ll-on-dark)}
.ll-head a{color:var(--ll-on-dark)}
.ll-head .ll-wrap{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 16px}
.ll-logo{display:flex;align-items:center;gap:9px;font-weight:700}
.ll-logo svg{width:22px;height:22px;color:var(--ll-gold)}
/* El logo configurado es un JPG sobre blanco: sobre el navy de la cabecera hace
   falta la pastilla, o se ve un recorte blanco con bordes duros. */
.ll-logo-img{width:34px;height:34px;border-radius:9px;background:#fff;padding:3px;
  object-fit:contain;flex:0 0 auto}
.ll-nav{margin-left:auto;display:flex;gap:16px;flex-wrap:wrap;font-size:14px}

.ll-main{padding:26px 0 40px}
.ll-card{background:var(--ll-card);border:1px solid var(--ll-line);border-radius:var(--ll-radius);box-shadow:var(--ll-shadow)}
.ll-pad{padding:18px}
.ll-sub{color:var(--ll-muted);margin:.25rem 0 1.25rem}
.ll-small{font-size:13px}
.ll-muted{color:var(--ll-muted)}
.ll-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* Botones: el dorado es la acción principal, el navy la secundaria. */
.ll-btn{display:inline-flex;align-items:center;gap:7px;padding:11px 18px;border:0;border-radius:999px;
  background:var(--ll-gold);color:#18140a;font:inherit;font-weight:600;cursor:pointer}
.ll-btn:hover{text-decoration:none;filter:brightness(.95)}
.ll-btn-quiet{background:transparent;color:var(--ll-on-dark);border:1px solid rgba(255,255,255,.55)}
.ll-btn-ghost{background:transparent;color:var(--ll-ink);border:1px solid var(--ll-line)}
.ll-btn-sm{padding:8px 14px;font-size:14px}
.ll-btn-block{width:100%;justify-content:center}

/* Hero a sangre: una banda de portada tiene que llegar a los dos bordes. */
.ll-hero{background:linear-gradient(160deg,var(--ll-navy),var(--ll-navy-soft));color:var(--ll-on-dark)}
.ll-hero .ll-wrap{padding:46px 16px 40px}
/* Héroe con portada: la imagen al fondo y el degradado de la marca por delante.
   El velo no es decoración —es lo que mantiene legible el titular blanco sobre una
   foto que no controlamos—, y por eso es opaco de arriba y se abre abajo, donde está
   el buscador sobre tarjeta. `position:relative` en el héroe y `z-index` en el
   contenido, o la imagen taparía el formulario. */
.ll-hero-img{position:relative;isolation:isolate}
.ll-hero-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2}
.ll-hero-img::before{content:"";position:absolute;inset:0;z-index:-1;
  background:linear-gradient(160deg,rgba(11,31,68,.90),rgba(22,48,95,.78))}
@media (prefers-color-scheme: dark){
  .ll-hero-img::before{background:linear-gradient(160deg,rgba(6,16,36,.93),rgba(12,28,56,.85))}
}
.ll-hero h1{margin:0 0 10px;font-size:clamp(26px,4.6vw,42px);max-width:22ch}
.ll-hero p{margin:0 0 18px;max-width:62ch;color:rgba(255,255,255,.88)}
.ll-kicker{display:inline-block;margin-bottom:14px;padding:4px 12px;border-radius:999px;
  background:rgba(191,143,35,.18);color:var(--ll-gold);font-size:13px;font-weight:600}
.ll-hero-cta{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}

/* Buscador del hero y del listado. */
.ll-search{display:grid;gap:10px;grid-template-columns:1fr;align-items:end}
@media (min-width:760px){.ll-search{grid-template-columns:2fr 1.2fr 1.2fr auto}}
.ll-field{display:flex;flex-direction:column;gap:5px;min-width:0}
.ll-label{font-size:13px;font-weight:600;color:var(--ll-muted)}
.ll-hero .ll-label{color:rgba(255,255,255,.85)}
.ll-input,.ll-select,.ll-textarea{width:100%;padding:11px 12px;border:1px solid var(--ll-field);
  border-radius:10px;background:var(--ll-card);color:var(--ll-ink);font:inherit}
.ll-textarea{min-height:110px;resize:vertical}
.ll-input:focus,.ll-select:focus,.ll-textarea:focus{outline:2px solid var(--ll-gold);outline-offset:1px}
.ll-req{color:#b42318}

.ll-sec{margin:0 0 34px}
.ll-sec-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:14px}
.ll-sec-head h2{margin:0;font-size:22px}

.ll-grid{display:grid;gap:14px;grid-template-columns:1fr}
@media (min-width:620px){.ll-grid{grid-template-columns:repeat(2,1fr)}}
@media (min-width:920px){.ll-grid{grid-template-columns:repeat(3,1fr)}}

/* Rejilla de áreas: son puertas de entrada, no tarjetas de contenido. */
.ll-areas{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(210px,1fr))}
.ll-area{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px;
  background:var(--ll-card);border:1px solid var(--ll-line);border-radius:12px;color:var(--ll-ink)}
.ll-area:hover{text-decoration:none;border-color:var(--ll-gold)}
.ll-area-n{font-weight:600}
.ll-area-c{font-size:13px;color:var(--ll-muted)}

/* Tarjeta de perfil. */
.ll-pro{display:flex;flex-direction:column;gap:12px;padding:16px}
.ll-pro-top{display:flex;gap:12px;align-items:flex-start}
.ll-avatar{width:56px;height:56px;border-radius:50%;flex:0 0 auto;display:flex;align-items:center;
  justify-content:center;background:var(--ll-navy);color:var(--ll-on-dark);font-weight:700;font-size:19px;overflow:hidden}
.ll-avatar img{width:100%;height:100%;object-fit:cover}
.ll-pro-name{margin:0;font-size:17px}
.ll-tags{display:flex;gap:6px;flex-wrap:wrap}
.ll-tag{padding:3px 9px;border-radius:999px;background:var(--ll-bg);border:1px solid var(--ll-line);font-size:12px}
.ll-verified{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;
  background:var(--ll-gold-soft);color:var(--ll-gold);font-size:12px;font-weight:600}
.ll-featured{position:absolute;top:12px;right:12px;padding:3px 9px;border-radius:999px;
  background:var(--ll-gold);color:#18140a;font-size:11px;font-weight:700}
.ll-pro-foot{margin-top:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center;
  border-top:1px solid var(--ll-line);padding-top:12px}

.ll-split{display:grid;gap:18px;grid-template-columns:1fr}
@media (min-width:900px){.ll-split{grid-template-columns:2fr 1fr;align-items:start}}

.ll-band{background:var(--ll-card);border:1px solid var(--ll-line);border-left:4px solid var(--ll-gold);
  border-radius:12px;padding:18px;display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.ll-list{margin:0;padding-left:18px}
.ll-list li{margin:.3rem 0}
.ll-alert{padding:12px 14px;border-radius:10px;border:1px solid var(--ll-line);background:var(--ll-card);margin-bottom:16px}
.ll-alert-bad{border-color:#f0c2bd;background:#fdf3f2;color:#8a2018}
@media (prefers-color-scheme: dark){.ll-alert-bad{border-color:#5c211b;background:#2a1412;color:#f4b4ad}}
.ll-empty{padding:26px;text-align:center}

/* La trampa del formulario va fuera de la pantalla, no oculta: algunos navegadores
   no autocompletan lo que está en `display:none` y entonces no atrapa nada. */
.ll-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}

.ll-foot{border-top:1px solid var(--ll-line);background:var(--ll-card);padding:24px 0;margin-top:20px}
.ll-foot .ll-wrap{display:flex;gap:16px;flex-wrap:wrap;justify-content:space-between}
.ll-legal{max-width:70ch;font-size:12.5px;color:var(--ll-muted);margin:10px 0 0}
.ll-pag{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}

/* ───────────────────────── Micrositio de la firma ─────────────────────────
   Dos columnas: a la izquierda lo que se LEE —quién es, qué hace, quién la
   forma—, a la derecha lo que se USA —contacto, QR, consulta—. La derecha
   acompaña (`sticky`) mientras se recorren los abogados, que es justo cuando
   dan ganas de escribir.

   En móvil es una sola columna y la derecha cae ENTERA debajo: primero quién es
   la firma y a quién tiene, después cómo escribirle. Se probó subir el contacto
   con `order` y se descartó: parte en dos la lectura y deja los abogados —que
   son el motivo de la visita— detrás de un formulario. */
.ll-biz{display:grid;gap:18px;grid-template-columns:1fr}
@media (min-width:900px){
  .ll-biz{grid-template-columns:minmax(0,3fr) minmax(0,1fr);align-items:start}
  .ll-biz-aside{position:sticky;top:18px}
}
.ll-biz-col{display:flex;flex-direction:column;gap:18px;min-width:0}
.ll-biz-aside{display:flex;flex-direction:column;gap:18px;min-width:0}
/* Cada bloque se sostiene solo: el que no tiene nada que enseñar no se pinta y
   no deja hueco. */
.ll-blk{padding:18px}
.ll-blk > h2{margin:0;font-size:17px;display:flex;align-items:center;gap:8px}
.ll-blk-rule{height:1px;background:var(--ll-line);margin:12px 0 0}

/* Identidad: la portada se repite aquí en pequeño, dentro de la tarjeta, para
   que la ficha siga teniendo cara cuando se comparte un pantallazo. */
.ll-biz-cover{aspect-ratio:16/6;background:var(--ll-navy);overflow:hidden}
.ll-biz-cover img{width:100%;height:100%;object-fit:cover}
.ll-biz-stripe{height:4px}
.ll-biz-id{display:flex;align-items:center;gap:14px;padding:16px 18px}
.ll-biz-logo{position:relative;z-index:1;display:grid;place-items:center;width:64px;height:64px;
  flex:0 0 auto;border-radius:16px;border:1px solid var(--ll-line);background:var(--ll-card);
  overflow:hidden;font-weight:700;font-size:20px;color:var(--ll-navy)}
.ll-biz-logo img{width:100%;height:100%;object-fit:contain}

/* Servicios: una lista, no una rejilla de tarjetas. Son textos de largo
   desigual y en rejilla quedan cajas medio vacías al lado de cajas llenas. */
.ll-svc{list-style:none;margin:0;padding:0}
.ll-svc li{padding:12px 0;border-top:1px solid var(--ll-line)}
.ll-svc li:first-child{border-top:0}
.ll-svc-n{font-weight:600;display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* Datos de contacto: etiqueta arriba y dato debajo, para que se lean en una
   columna estrecha sin partirse. */
.ll-kv{margin:0;display:grid;gap:10px}
.ll-kv dt{font-size:12.5px;color:var(--ll-muted)}
.ll-kv dd{margin:0;font-weight:600;word-break:break-word}

/* Módulo de calificación: la nota grande y el desglose por estrella.
   En una columna estrecha —la ficha de un abogado dentro de su tarjeta— las barras
   caen debajo de la nota en vez de encogerse hasta no significar nada. */
.ll-rate{display:grid;gap:16px;grid-template-columns:1fr;align-items:center;
  padding:14px 0;border-top:1px solid var(--ll-line)}
@media (min-width:560px){.ll-rate{grid-template-columns:auto 1fr;gap:24px}}
.ll-rate-score{text-align:center}
.ll-rate-big{margin:0;font-size:40px;font-weight:800;line-height:1;letter-spacing:-.02em}
.ll-rate-score .ll-row{justify-content:center}
.ll-rate-bars{display:grid;gap:5px;min-width:0}
.ll-rate-row{display:grid;grid-template-columns:14px 1fr 28px;align-items:center;gap:8px}
.ll-rate-n{font-size:12px;color:var(--ll-muted);text-align:right}
.ll-rate-c{font-size:12px;color:var(--ll-muted)}
.ll-rate-bar{height:7px;border-radius:999px;background:var(--ll-bg);overflow:hidden;
  border:1px solid var(--ll-line)}
.ll-rate-fill{display:block;height:100%;background:var(--ll-gold)}

.ll-qr{text-align:center}
.ll-qr img{width:150px;height:150px;margin:0 auto;border-radius:10px;background:#fff;padding:6px}
</style>
</head>
<body>
<header class="ll-head">
  <div class="ll-wrap">
    <?php
    /*
     * El logo de la plataforma, el que está en Configuración del sistema.
     *
     * Va en una pastilla BLANCA porque el logo configurado es un JPG sobre fondo
     * blanco y la cabecera es navy: puesto a pelo se vería una caja blanca recortada.
     * Es el mismo recurso que usa la ficha de la firma para el suyo.
     *
     * Sin logo configurado se queda el icono de siempre: la cabecera de un sitio
     * público no puede depender de que alguien haya subido un archivo.
     */
    $brandLogo = \Components\LeadFlow\Models\PlatformModel::brandLogo();
    ?>
    <a class="ll-logo" href="<?= base_url('/') ?>">
      <?php if ($brandLogo !== null): ?>
        <img class="ll-logo-img" src="<?= esc($brandLogo, 'attr') ?>" alt="" width="34" height="34">
      <?php else: ?>
        <?= $icon('scale') ?>
      <?php endif; ?>
      <span><?= esc(lang('Lfl.dirBrand')) ?></span>
    </a>
    <nav class="ll-nav">
      <a href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirNavFind')) ?></a>
      <a href="<?= base_url('/') ?>#areas"><?= esc(lang('Lfl.dirNavAreas')) ?></a>
      <a href="<?= base_url('firmas') ?>"><?= esc(lang('Lfl.dirNavFirms')) ?></a>
      <a href="<?= base_url('/') ?>#abogados"><?= esc(lang('Lfl.dirNavForLawyers')) ?></a>
      <a href="<?= base_url('registro-legal') ?>"><?= esc(lang('Lfl.suCta')) ?></a>
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
  </div>
</footer>
</body>
</html>
