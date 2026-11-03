<?php
/**
 * Tarjeta de presentación digital del abogado o de la firma.
 *
 * **Lienzo propio, no el del directorio.** Esto no es una página que se recorre: es una
 * tarjeta que ocupa la pantalla y se gira. Toda la hoja va en línea y los SVG también
 * (§7.3), así que no carga `tailwind.css` ni `icons.css` ni una URL externa.
 *
 * El diseño es el que ya está probado dos veces en el ecosistema —la tarjeta de
 * Servicios y la de Salud— y se hereda con sus lecciones, no se reinventa:
 *
 *  · **Formato CR80 vertical** (54 × 86 por `aspect-ratio`, ancho `min(100%, 22.5rem)`).
 *    Apaisada queda una franja ilegible en el teléfono, que es donde se mira una tarjeta.
 *  · **El envoltorio va aparte de la tarjeta**: la animación de «voltear en la mano»
 *    escala el envoltorio, y en el mismo elemento la escala y el `rotateY` se pelean.
 *  · **Las dos caras se cruzan las opacidades**, porque Safari deja ver el reverso solo
 *    con `backface-visibility`. Y se respeta `prefers-reduced-motion`.
 *  · **La barra de acciones vive FUERA de la cara que gira**: dentro quedaría del revés
 *    la mitad del tiempo.
 *
 * Lo que cambia respecto a la de Salud es el contenido, porque el dominio es otro: donde
 * allí va la especialidad médica aquí va el **área de práctica**, donde allí va la
 * valoración —que aquí todavía no existe— va la **credencial**: la licencia y la
 * insignia de verificado, que es lo que genera confianza en esta profesión (§8 del
 * insumo). Y el botón principal no reserva una hora: **solicita orientación**, que es lo
 * que crea el lead.
 *
 * @var array       $profile  perfil decorado por `PublicDirectory`
 * @var array       $company  fila de `companies` (logo, portada, marca, redes)
 * @var string|null $qr       data URI del QR de esta tarjeta
 * @var string      $url      URL canónica de la tarjeta
 * @var string      $pageTitle
 */
use Components\LeadFlow\Models\ProfileModel;

$p     = $profile;
$icon  = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$perfil = base_url('abogado/' . rawurlencode((string) $p['slug']));

/*
 * El color de marca de la empresa, si lo tiene. **Se valida con una regex y se emite
 * CRUDO**: `esc($hex,'css')` produce `\23 …` y `esc($hex,'attr')` produce `&#x23;…`, y
 * ninguno de los dos es un color CSS válido. Sin marca propia manda el navy de
 * Leads Lawyers.
 */
$brandHex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? ''))
    ? (string) $company['brand_color']
    : '#0b1f44';

/** Las imágenes van por `<img src>` y no dentro de un `url()`: una URL en un atributo
 *  dentro de una regla CSS pasa por dos decodificaciones. Con la etiqueta basta `esc()`. */
$img = static function (?string $path): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    return preg_match('~^https?://~i', $path)
        ? $path
        : (function_exists('media_url') ? media_url($path) : base_url(ltrim($path, '/')));
};

$foto    = $img($p['photo'] ?? null);
$portada = $img($company['banner'] ?? null);
$logo    = $img($company['logo'] ?? null);
$wa      = preg_replace('/\D+/', '', (string) ($p['whatsapp'] ?: $p['phone']));
$tel     = preg_replace('/\D+/', '', (string) ($p['phone'] ?: $p['whatsapp']));
$areas   = array_column($p['areas'], 'name');
$iniciales = ProfileModel::initials($p['display_name']);

$redes = array_filter([
    'instagram' => $company['instagram'] ?? '',
    'facebook'  => $company['facebook'] ?? '',
    'linkedin'  => $company['linkedin'] ?? '',
    'youtube'   => $company['youtube'] ?? '',
]);

// La segunda vista solo existe si hay algo que no cabe en el frente.
$detalle = count($areas) > 3 || $p['jurisdictions'] || $p['languages_list'] || $p['modes_list'] || $p['bio'];
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= esc($pageTitle) ?></title>
<?php // Se indexa: es una página de presentación pública, como el perfil. ?>
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= esc($url) ?>">
<meta property="og:type" content="profile">
<meta property="og:title" content="<?= esc($p['display_name']) ?>">
<meta property="og:description" content="<?= esc($p['headline'] ?: ($areas[0] ?? '')) ?>">
<meta property="og:url" content="<?= esc($url) ?>">
<?php if ($foto !== ''): ?>
<meta property="og:image" content="<?= esc($foto) ?>">
<?php endif; ?>
<style>
:root{
  --dc-brand:<?= $brandHex ?>;            /* validado arriba, emitido crudo */
  --dc-gold:#bf8f23; --dc-brand-ink:#ffffff;
  --dc-bg:#eef0f4; --dc-card:#ffffff; --dc-ink:#11151c; --dc-muted:#5b6472; --dc-line:#e3e6ec;
}
@media (prefers-color-scheme: dark){
  :root{--dc-bg:#080b11;--dc-card:#141a24;--dc-ink:#e8eaee;--dc-muted:#98a2b3;--dc-line:#242c39;--dc-gold:#d8a93a}
}
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
svg{width:1em;height:1em;flex:0 0 auto}
body{
  margin:0;min-height:100dvh;background:var(--dc-bg);color:var(--dc-ink);
  font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  /* Sitio para la barra fija de abajo más el área segura del teléfono. */
  padding:20px 16px calc(104px + env(safe-area-inset-bottom));
}
body.is-detail{justify-content:flex-start}
img{max-width:100%;display:block}
a{color:inherit;text-decoration:none}
[hidden]{display:none!important}

.dc-wrap{width:min(100%,22.5rem);perspective:1600px}
.dc{position:relative;width:100%;aspect-ratio:54/86;transform-style:preserve-3d;
  transition:transform .7s cubic-bezier(.2,.7,.2,1)}
.dc.is-back{transform:rotateY(180deg)}
.dc-face{position:absolute;inset:0;backface-visibility:hidden;-webkit-backface-visibility:hidden;
  background:var(--dc-card);border-radius:20px;overflow:hidden;display:flex;flex-direction:column;
  box-shadow:0 10px 30px rgba(11,31,68,.20),0 2px 6px rgba(11,31,68,.10);
  transition:opacity .35s linear}
.dc-back{transform:rotateY(180deg)}
.dc:not(.is-back) .dc-back{opacity:0}
.dc.is-back .dc-front{opacity:0}
@media (prefers-reduced-motion: reduce){.dc,.dc-face{transition:none}}

/* ── Frente ─────────────────────────────────────────────────────────────── */
.dc-cover{position:relative;height:150px;background:linear-gradient(135deg,var(--dc-brand) 0%,var(--dc-gold) 190%)}
.dc-cover img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
/* Velo: la portada la elige la empresa y puede ser clarísima; sobre ella asomaría el
   borde blanco de la foto redonda. El velo le da un suelo constante. */
.dc-cover img+span{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.05),rgba(0,0,0,.30))}
/* El margen negativo es la MITAD del diámetro: así la foto queda partida por el borde
   de la portada sea cual sea su tamaño. */
.dc-photo{position:relative;z-index:1;width:130px;height:130px;border-radius:50%;margin:-65px auto 0;
  border:4px solid var(--dc-card);background:var(--dc-card);overflow:hidden;
  display:grid;place-items:center;box-shadow:0 4px 12px rgba(11,31,68,.18)}
.dc-photo img{width:100%;height:100%;object-fit:cover}
.dc-ini{font-weight:800;font-size:42px;color:var(--dc-brand)}
.dc-body{padding:12px 18px 0;text-align:center;flex:1;min-height:0;overflow:auto}
/* Identidad arriba y acciones abajo: el sobrante se reparte con márgenes automáticos y
   NO centrando el bloque, porque centrar con `overflow` corta por arriba y lo cortado
   no se alcanza. Un margen automático se rinde a cero en cuanto falta sitio. */
.dc-front .dc-body{display:flex;flex-direction:column;padding-bottom:14px}
.dc-name{font-size:20px;font-weight:800;letter-spacing:-.02em;margin:0}
/* El área de práctica es la credencial: se lee como tal sin meterla en una caja. */
.dc-area{font-size:12px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;
  color:var(--dc-gold);margin:5px 0 0}
.dc-bar{width:44px;height:3px;border-radius:2px;background:var(--dc-brand);margin:8px auto}
.dc-role{font-size:13px;color:var(--dc-muted);margin:0}
/* Calificación: discreta y bajo el nombre. En una tarjeta de presentación la nota
   acompaña, no encabeza — quien la recibe viene a saber quién es la persona. */
.dc-rate{display:flex;align-items:center;gap:5px;margin:6px 0 0}
.dc-rate svg{width:13px;height:13px;color:var(--dc-gold)}
.dc-rate-v{font-size:12.5px;font-weight:700}
.dc-rate-c{font-size:11.5px;color:var(--dc-muted)}
.dc-org{font-size:12px;font-weight:700;color:var(--dc-brand);margin:6px 0 0}
.dc-tags{display:flex;flex-wrap:wrap;gap:5px;justify-content:center;margin:10px 0 0}
.dc-tag{font-size:11px;font-weight:600;border-radius:999px;padding:2px 9px;background:var(--dc-line);color:var(--dc-ink)}
/* **La credencial ocupa el lugar que en la tarjeta médica tiene la valoración**: justo
   encima de las acciones, centrada en el hueco libre con márgenes automáticos. Es lo que
   decide si alguien escribe. Sin licencia ni verificación no se pinta nada: un hueco es
   mejor que una insignia vacía. */
.dc-cred{display:flex;flex-direction:column;align-items:center;gap:4px;margin-block:auto}
.dc-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;
  background:rgba(191,143,35,.14);color:var(--dc-gold);font-size:11.5px;font-weight:700}
.dc-lic{font-size:11.5px;color:var(--dc-muted);font-weight:600}
.dc-front .dc-rows-bottom{margin-top:auto}

/* Contactos en círculos, con el rótulo FUERA del círculo: el marco ciñe solo el icono,
   que es lo que se pulsa, y debajo queda la palabra. Cinco de 60 px con 4 de separación
   caben en el ancho útil (322 px a 390). */
.dc-rows{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:4px;justify-content:center}
.dc-rows li{flex:0 0 auto}
.dc-rows li a,.dc-rows li button{border:0;background:none;padding:0;cursor:pointer;font:inherit;
  display:flex;flex-direction:column;align-items:center;gap:6px;width:60px;
  font-size:10px;font-weight:600;color:var(--dc-muted);line-height:1.25;text-align:center}
.dc-rows svg{width:44px;height:44px;padding:11px;border-radius:50%;
  background:var(--dc-brand);color:var(--dc-brand-ink);transition:background .15s linear,color .15s linear}
/* La acción principal —pedir orientación— lleva el dorado: es la que paga la tarjeta. */
.dc-rows li.is-main svg{background:var(--dc-gold);color:#18140a}
.dc-rows li a:hover svg,.dc-rows li button:hover svg{background:var(--dc-line);color:var(--dc-ink)}
.dc-rows li a:hover,.dc-rows li button:hover{color:var(--dc-ink)}
/* El rótulo no puede partir la fila: se corta con puntos suspensivos. */
.dc-rows li b{display:block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}
/* Pie a sangre con el color de marca, y el botón de girar DENTRO de esa banda. */
.dc-foot{margin-top:10px;background:var(--dc-brand);color:var(--dc-brand-ink);
  padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:11.5px}

/* ── Reverso ────────────────────────────────────────────────────────────── */
.dc-back .dc-body{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:22px 18px}
/* El logo es lo primero del reverso: ahí se mira de quién es la tarjeta antes de
   escanear. Ancho automático porque un logo apaisado forzado a cuadrado se deforma. */
.dc-logo{max-height:62px;max-width:80%;width:auto;object-fit:contain;margin-bottom:8px}
.dc-hr{width:56px;height:1px;background:var(--dc-line);margin:12px 0}
.dc-qr{width:148px;height:148px;border-radius:12px;background:#fff;padding:8px}
.dc-socials{display:flex;gap:9px;margin-top:14px}
.dc-socials a{display:grid;place-items:center;width:34px;height:34px;border-radius:999px;
  border:1px solid var(--dc-line);color:var(--dc-muted)}
.dc-socials a:hover{background:var(--dc-brand);border-color:var(--dc-brand);color:var(--dc-brand-ink)}
.dc-socials svg{width:16px;height:16px}
.dc-flip{border:0;background:rgba(255,255,255,.22);color:inherit;cursor:pointer;
  border-radius:999px;padding:5px 11px;font:inherit;font-size:11.5px;font-weight:700}
.dc-flip:hover{background:rgba(255,255,255,.34)}

/* ── Barra de acciones: FIJA y fuera de la cara que gira ────────────────── */
.dc-actions{position:fixed;left:0;right:0;bottom:0;z-index:5;
  background:var(--dc-card);border-top:1px solid var(--dc-line);
  padding:9px 8px calc(9px + env(safe-area-inset-bottom));
  display:flex;justify-content:center;gap:4px;flex-wrap:wrap}
/* **Sin marcos**: seis recuadros en una barra de teléfono se leen como seis cajas. */
.dc-act{border:0;background:none;cursor:pointer;font:inherit;text-decoration:none;
  display:flex;flex-direction:column;align-items:center;gap:4px;width:62px;padding:4px 2px;
  font-size:10px;font-weight:600;color:var(--dc-muted);line-height:1.2;text-align:center}
.dc-act svg{width:22px;height:22px}
.dc-act-main svg{width:38px;height:38px;padding:9px;border-radius:50%;
  background:var(--dc-gold);color:#18140a}
.dc-act-on{color:var(--dc-brand)}
@media (prefers-color-scheme: dark){.dc-act-on{color:var(--dc-gold)}}

/* ── Detalles: una VISTA de la tarjeta, no una ventana ──────────────────── */
/* Con seis áreas y cuatro jurisdicciones, un diálogo flotante deja una caja pequeña con
   su propio desplazamiento dentro de una pantalla que ya se desplaza. La vista ocupa el
   sitio de la tarjeta y la página entera hace de lista. */
.dc-panel{width:min(100%,32rem);margin:0 auto}
.dc-panel h2{font-size:17px;margin:0 0 4px}
.dc-block{background:var(--dc-card);border:1px solid var(--dc-line);border-radius:14px;padding:14px;margin-bottom:10px}
.dc-block h3{font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:var(--dc-muted);margin:0 0 8px}
.dc-block p{margin:0;font-size:14px}
.dc-hidden{position:absolute;left:-9999px}

dialog{border:0;padding:0;background:transparent}
dialog::backdrop{background:rgba(11,31,68,.55)}
/* El preflight de Tailwind no está aquí, pero `<dialog>` necesita `margin:auto` igual. */
.dc-modal{margin:auto;background:var(--dc-card);color:var(--dc-ink);border-radius:18px;padding:20px;
  text-align:center;max-width:min(92vw,20rem)}
.dc-modal img{margin:0 auto}
.dc-modal button{margin-top:14px;border:0;border-radius:999px;padding:9px 18px;cursor:pointer;
  background:var(--dc-brand);color:var(--dc-brand-ink);font:inherit;font-weight:700}
</style>
</head>
<body>

<div class="dc-wrap" id="wrap">
  <div class="dc" id="card">

    <?php // ───────────────────────────────────────────── FRENTE ?>
    <div class="dc-face dc-front">
      <div class="dc-cover">
        <?php if ($portada !== ''): ?>
          <img src="<?= esc($portada) ?>" alt=""><span></span>
        <?php endif; ?>
      </div>

      <span class="dc-photo">
        <?php if ($foto !== ''): ?>
          <img src="<?= esc($foto) ?>" alt="<?= esc($p['display_name']) ?>">
        <?php else: ?>
          <span class="dc-ini"><?= esc($iniciales) ?></span>
        <?php endif; ?>
      </span>

      <div class="dc-body">
        <h1 class="dc-name"><?= esc($p['display_name']) ?></h1>

        <?php // La primera área manda: es el titular de lo que hace esta persona. ?>
        <?php if ($areas): ?>
          <p class="dc-area"><?= esc($areas[0]) ?></p>
        <?php endif; ?>

        <?php
        /*
         * La calificación, si tiene opiniones publicadas. Va antes del filete, pegada
         * al nombre, porque es parte de quién es esta persona y no un dato más de la
         * ficha. Sin opiniones no se pinta: en una tarjeta de presentación, cinco
         * estrellas vacías son peor que ninguna estrella.
         */
        ?>
        <?php if (! empty($p['rating'])): ?>
          <?php $rv = (float) $p['rating']['avg']; ?>
          <p class="dc-rate">
            <span style="display:inline-flex;gap:1px">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg viewBox="0 0 24 24" fill="<?= round($rv) >= $i ? 'currentColor' : 'none' ?>"
                  stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m12 4 2.4 5 5.6.8-4 3.9 1 5.5-5-2.7-5 2.7 1-5.5-4-3.9 5.6-.8L12 4Z"/></svg>
              <?php endfor; ?>
            </span>
            <span class="dc-rate-v"><?= esc(number_format($rv, 1)) ?></span>
            <span class="dc-rate-c"><?= esc(lang('Lfl.revCount', [(int) $p['rating']['count']])) ?></span>
          </p>
        <?php endif; ?>

        <div class="dc-bar"></div>
        <?php if (! empty($p['headline'])): ?>
          <p class="dc-role"><?= esc($p['headline']) ?></p>
        <?php endif; ?>
        <?php if (! empty($company['name'])): ?>
          <p class="dc-org"><?= esc($company['name']) ?></p>
        <?php endif; ?>

        <?php if (count($areas) > 1): ?>
          <div class="dc-tags">
            <?php foreach (array_slice($areas, 1, 3) as $a): ?>
              <span class="dc-tag"><?= esc($a) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php $tieneCred = ! empty($p['is_verified']) || ! empty($p['bar_number']); ?>
        <?php if ($tieneCred): ?>
          <div class="dc-cred">
            <?php if (! empty($p['is_verified'])): ?>
              <span class="dc-badge"><?= $icon('shield') ?><?= esc(lang('Lfl.dirVerified')) ?></span>
            <?php endif; ?>
            <?php if (! empty($p['bar_number'])): ?>
              <span class="dc-lic"><?= esc(lang('Lfl.dcLicense', [$p['bar_number'] . ($p['bar_state'] ? ' · ' . $p['bar_state'] : '')])) ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <ul class="dc-rows<?= $tieneCred ? '' : ' dc-rows-bottom' ?>">
          <?php if ($wa !== ''): ?>
            <li><a target="_blank" rel="noopener" href="https://wa.me/<?= esc($wa) ?>"><?= $icon('whatsapp') ?><b>WhatsApp</b></a></li>
          <?php endif; ?>
          <?php // Llamar no es lo mismo que WhatsApp: hay quien no lo usa, y hay asuntos
                // que se resuelven marcando. ?>
          <?php if ($tel !== ''): ?>
            <li><a href="tel:<?= esc($tel) ?>"><?= $icon('phone') ?><b><?= esc(lang('Lfl.dcPhone')) ?></b></a></li>
          <?php endif; ?>
          <?php if (! empty($p['email'])): ?>
            <li><a href="mailto:<?= esc($p['email']) ?>" title="<?= esc($p['email'], 'attr') ?>"><?= $icon('mail') ?><b><?= esc(lang('Lfl.dcEmail')) ?></b></a></li>
          <?php endif; ?>
          <?php // La acción que crea el lead, en dorado: es la razón de ser de la tarjeta. ?>
          <li class="is-main"><a href="<?= esc($perfil . '/consulta') ?>"><?= $icon('spark') ?><b><?= esc(lang('Lfl.dcAsk')) ?></b></a></li>
          <li><a href="<?= esc($perfil) ?>"><?= $icon('users') ?><b><?= esc(lang('Lfl.dcProfile')) ?></b></a></li>
        </ul>
      </div>

      <div class="dc-foot">
        <span><?= esc(lang('Lfl.dirBrand')) ?></span>
        <button type="button" class="dc-flip" data-flip><?= esc(lang('Lfl.dcFlip')) ?></button>
      </div>
    </div>

    <?php // ───────────────────────────────────────────── REVERSO ?>
    <div class="dc-face dc-back">
      <div class="dc-body">
        <?php if ($logo !== ''): ?>
          <img class="dc-logo" src="<?= esc($logo) ?>" alt="<?= esc($company['name'] ?? '') ?>">
        <?php elseif (! empty($company['name'])): ?>
          <p class="dc-org" style="font-size:15px;margin:0"><?= esc($company['name']) ?></p>
        <?php endif; ?>

        <p class="dc-role" style="margin:4px 0 0"><?= esc($p['display_name']) ?></p>
        <div class="dc-hr"></div>

        <?php // El QR va DEBAJO del nombre: primero se reconoce de quién es la tarjeta y
              // después se escanea. ?>
        <?php if ($qr !== null): ?>
          <img class="dc-qr" src="<?= esc($qr) ?>" alt="">
          <p class="dc-role" style="margin:10px 0 0;font-size:11.5px"><?= esc(lang('Lfl.dcQrHint')) ?></p>
        <?php endif; ?>

        <?php if ($redes): ?>
          <div class="dc-socials">
            <?php foreach ($redes as $red => $u): ?>
              <a target="_blank" rel="noopener" href="<?= esc($u) ?>" aria-label="<?= esc(ucfirst($red)) ?>"><?= $icon($red) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="dc-foot">
        <span><?= esc($company['name'] ?? lang('Lfl.dirBrand')) ?></span>
        <button type="button" class="dc-flip" data-flip><?= esc(lang('Lfl.dcFlipBack')) ?></button>
      </div>
    </div>
  </div>
</div>

<?php
/*
 * La vista de detalles: lo que no cabe en el frente.
 *
 * Son **dos botones y no uno que cambia de nombre**: el de antes decía «Detalles» y
 * pasaba a decir «Tarjeta», y había que pulsarlo para descubrir que era el mismo. Así
 * cada vista tiene su botón, siempre en el mismo sitio, y el encendido dice en cuál estás.
 */
?>
<?php if ($detalle): ?>
  <section class="dc-panel" id="detailbox" hidden>
    <div class="dc-block">
      <h2><?= esc($p['display_name']) ?></h2>
      <?php if (! empty($p['headline'])): ?>
        <p class="dc-role"><?= esc($p['headline']) ?></p>
      <?php endif; ?>
    </div>

    <?php if (! empty($p['bio'])): ?>
      <div class="dc-block">
        <h3><?= esc(lang('Lfl.dirAbout')) ?></h3>
        <p style="white-space:pre-line"><?= esc($p['bio']) ?></p>
      </div>
    <?php endif; ?>

    <?php
    $bloques = [
        'dirAreasOf'       => $areas ? implode(' · ', $areas) : null,
        'dirJurisdictions' => $p['jurisdictions'] ? implode(' · ', $p['jurisdictions']) : null,
        'dirLanguages'     => $p['languages_list'] ? implode(' · ', $p['languages_list']) : null,
        'dirModes'         => $p['modes_list'] ? implode(' · ', array_map(static fn ($m) => lang('Lfl.dirMode_' . $m), $p['modes_list'])) : null,
        'dirExperience'    => ! empty($p['experience_years']) ? lang('Lfl.dirYears', [(int) $p['experience_years']]) : null,
        'dirLicense'       => ! empty($p['bar_number']) ? $p['bar_number'] . ($p['bar_state'] ? ' · ' . $p['bar_state'] : '') : null,
    ];
    ?>
    <?php foreach ($bloques as $clave => $valor): ?>
      <?php if ($valor !== null && $valor !== ''): ?>
        <div class="dc-block">
          <h3><?= esc(lang('Lfl.' . $clave)) ?></h3>
          <p><?= esc($valor) ?></p>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="dc-block" style="text-align:center">
      <a class="dc-flip" style="background:var(--dc-gold);color:#18140a;display:inline-block"
        href="<?= esc($perfil . '/consulta') ?>"><?= esc(lang('Lfl.dirAskCta')) ?></a>
    </div>
  </section>
<?php endif; ?>

<nav class="dc-actions">
  <?php if ($detalle): ?>
    <button type="button" class="dc-act dc-act-on" data-card aria-pressed="true">
      <?= $icon('card') ?><span><?= esc(lang('Lfl.dcCard')) ?></span>
    </button>
    <button type="button" class="dc-act" data-detail aria-pressed="false">
      <?= $icon('list') ?><span><?= esc(lang('Lfl.dcDetails')) ?></span>
    </button>
  <?php endif; ?>
  <?php // El QR es la acción principal: así se pasa una tarjeta de un teléfono a otro
        // sin dictar nada, con el móvil ya en la mano. ?>
  <?php if ($qr !== null): ?>
    <button type="button" class="dc-act dc-act-main" data-qr>
      <?= $icon('qr') ?><span><?= esc(lang('Lfl.dcQr')) ?></span>
    </button>
  <?php endif; ?>
  <a class="dc-act" href="<?= esc($url) ?>.vcf">
    <?= $icon('user') ?><span><?= esc(lang('Lfl.dcSave')) ?></span>
  </a>
  <button type="button" class="dc-act" data-share>
    <?= $icon('share') ?><span data-share-label><?= esc(lang('Lfl.dcShare')) ?></span>
  </button>
</nav>

<?php if ($qr !== null): ?>
  <dialog id="qrbox">
    <div class="dc-modal">
      <img class="dc-qr" src="<?= esc($qr) ?>" alt="" style="width:200px;height:200px">
      <p class="dc-lic" style="margin:10px 0 0"><?= esc(lang('Lfl.dcQrHint')) ?></p>
      <button type="button" data-close><?= esc(lang('Lfl.close')) ?></button>
    </div>
  </dialog>
<?php endif; ?>

<script>
(function () {
  'use strict';

  var card = document.getElementById('card');
  var wrap = document.getElementById('wrap');

  function gira() {
    card.classList.toggle('is-back');
  }

  document.querySelectorAll('[data-flip]').forEach(function (b) {
    b.addEventListener('click', function (e) { e.stopPropagation(); gira(); });
  });

  // Tocar la tarjeta también gira, salvo sobre un enlace o un botón: si no, pulsar
  // «WhatsApp» giraría la tarjeta además de abrirlo.
  card.addEventListener('click', function (e) {
    if (e.target.closest('a,button')) { return; }
    gira();
  });

  // Las vistas se alternan con `hidden` y no con una clase que ponga `display:none`: el
  // atributo lo entienden igual el navegador y un lector de pantalla.
  var box     = document.getElementById('detailbox');
  var verDet  = document.querySelector('[data-detail]');
  var verCard = document.querySelector('[data-card]');

  if (box && verDet && verCard) {
    var muestra = function (detalles) {
      box.hidden  = ! detalles;
      wrap.hidden = detalles;
      document.body.classList.toggle('is-detail', detalles);
      verDet.classList.toggle('dc-act-on', detalles);
      verCard.classList.toggle('dc-act-on', ! detalles);
      verDet.setAttribute('aria-pressed', detalles ? 'true' : 'false');
      verCard.setAttribute('aria-pressed', detalles ? 'false' : 'true');
      window.scrollTo(0, 0);
    };
    verDet.addEventListener('click', function () { muestra(true); });
    verCard.addEventListener('click', function () { muestra(false); });
  }

  var qrbox = document.getElementById('qrbox');
  var abrir = document.querySelector('[data-qr]');
  if (qrbox && abrir) {
    abrir.addEventListener('click', function () { qrbox.showModal(); });
    qrbox.querySelector('[data-close]').addEventListener('click', function () { qrbox.close(); });
    qrbox.addEventListener('click', function (e) { if (e.target === qrbox) { qrbox.close(); } });
  }

  var compartir = document.querySelector('[data-share]');
  compartir.addEventListener('click', function () {
    var datos = { title: <?= json_encode($p['display_name'], JSON_UNESCAPED_UNICODE) ?>, url: location.href };

    // `navigator.share` no existe fuera de un origen seguro, y esto se prueba por IP en
    // la red local: el respaldo copia el enlace y lo dice.
    if (navigator.share) { navigator.share(datos).catch(function () {}); return; }

    var etiqueta = compartir.querySelector('[data-share-label]');
    var previo   = etiqueta.textContent;
    var listo    = function () {
      etiqueta.textContent = <?= json_encode(lang('Lfl.dcCopied'), JSON_UNESCAPED_UNICODE) ?>;
      setTimeout(function () { etiqueta.textContent = previo; }, 1800);
    };

    if (navigator.clipboard) { navigator.clipboard.writeText(location.href).then(listo, function () {}); return; }

    var t = document.createElement('textarea');
    t.value = location.href; t.className = 'dc-hidden';
    document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); listo(); } catch (e) { /* sin portapapeles: no pasa nada */ }
    document.body.removeChild(t);
  });
}());
</script>
</body>
</html>
