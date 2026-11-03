<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * El micrositio de la firma, **por bloques**.
 *
 * Sigue la estructura de la página de negocio de la vitrina: tarjetas sobre el fondo,
 * en dos columnas y sin pestañas. **A la izquierda lo que se LEE** —quién es, qué
 * hace, quién la forma, qué dicen sus clientes—; **a la derecha lo que se USA**
 * —contacto, QR y el formulario—. La derecha acompaña al bajar, que es justo cuando
 * dan ganas de escribir.
 *
 * Cada bloque se sostiene solo y **el que no tiene nada que enseñar no se pinta**: una
 * firma recién llegada no debe verse como una página rota, sino como una página corta.
 *
 * **La empresa es la firma.** De ella cuelgan sus *abogados* —los perfiles publicados—
 * y sus *servicios* —el catálogo que decidió hacer público—. Son las dos cosas que
 * alguien viene a mirar aquí.
 *
 * **El fondo es color plano** y la portada de la firma vive dentro de la tarjeta de
 * identidad, arriba del todo. Se probó con la portada de fondo a toda página y se
 * descartó: encima de una foto cualquiera el texto deja de leerse —clara o oscura, hay
 * un caso que falla—, y el color plano deja que la portada se vea donde tiene que
 * verse, que es en la cabecera de la ficha.
 *
 * El formulario va el ÚLTIMO de la columna derecha, debajo del teléfono y del correo:
 * quien ya sabe cómo llamar no debería tener que rellenar nada.
 *
 * @var array      $company
 * @var array      $profiles
 * @var array      $services
 * @var array      $areas
 * @var array      $reviews
 * @var array|null $rating
 * @var array      $breakdown  opiniones por estrella, para el desglose
 * @var string|null $qr
 * @var string     $stamp
 * @var bool       $hasGd
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$url  = base_url('firma/' . rawurlencode((string) $company['slug']));

// El color de marca viste la franja bajo la portada. Validado contra un patrón y
// emitido crudo: `esc()` lo convertiría en un color inválido (convención `$…Hex`).
$brandHex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? ''))
    ? (string) $company['brand_color']
    : '#bf8f23';

$img = static function (?string $path): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    return preg_match('~^https?://~i', $path)
        ? $path
        : (function_exists('media_url') ? media_url($path) : base_url(ltrim($path, '/')));
};
$logo    = $img($company['logo'] ?? null);
$portada = $img($company['banner'] ?? null);

// Iniciales de la firma cuando no hay logo, con el mismo criterio que los perfiles.
$iniciales = \Components\LeadFlow\Models\ProfileModel::initials((string) $company['name']);

$redes = array_filter([
    'instagram' => $company['instagram'] ?? '',
    'facebook'  => $company['facebook'] ?? '',
    'linkedin'  => $company['linkedin'] ?? '',
    'youtube'   => $company['youtube'] ?? '',
]);

$wa       = preg_replace('/\D+/', '', (string) ($company['whatsapp'] ?? ''));
$lugar    = trim(implode(', ', array_filter([$company['address'] ?? null, $company['city'] ?? null])));
$comoLleg = (! empty($company['latitude']) && ! empty($company['longitude']))
    ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($company['latitude'] . ',' . $company['longitude'])
    : '';
?>

<?php // El resultado de la consulta se cuenta aquí, que es donde el visitante estaba
      // mirando. La clase se elige en el sitio y no sale de una variable: el auditor
      // exige que nada se imprima sin `esc()`, y un trozo de clase escapado se llena
      // de entidades sin ganar nada.
      foreach (['dir_ok', 'dir_error'] as $clave): ?>
  <?php if (session($clave)): ?>
    <div class="ll-alert<?= $clave === 'dir_error' ? ' ll-alert-bad' : '' ?>" style="margin-bottom:18px">
      <?= esc(session($clave)) ?>
    </div>
  <?php endif; ?>
<?php endforeach; ?>

<div class="ll-biz">

  <?php // ════════════════════════════════════════════════ COLUMNA IZQUIERDA ?>
  <div class="ll-biz-col">

    <?php // ───────────────────────────────────────────────────── identidad ?>
    <section class="ll-card" style="overflow:hidden">
      <div class="ll-biz-cover">
        <?php if ($portada !== ''): ?>
          <?php // Como <img> y no como `background-image`: una URL dentro de un
                // `url()` dentro de un atributo pasa por dos decodificaciones. ?>
          <img src="<?= esc($portada, 'attr') ?>" alt="">
        <?php endif; ?>
      </div>
      <div class="ll-biz-stripe" style="background:<?= $brandHex ?>"></div>

      <div class="ll-biz-id">
        <span class="ll-biz-logo">
          <?php if ($logo !== ''): ?>
            <img src="<?= esc($logo, 'attr') ?>" alt="" width="64" height="64">
          <?php else: ?>
            <?= esc($iniciales) ?>
          <?php endif; ?>
        </span>
        <div style="min-width:0;flex:1">
          <span class="ll-kicker" style="margin-bottom:4px"><?= esc(lang('Lfl.dirKindFirm')) ?></span>
          <h1 style="margin:2px 0 4px;font-size:clamp(20px,3.4vw,28px)"><?= esc($company['name']) ?></h1>
          <?php if ($lugar !== ''): ?>
            <p class="ll-small ll-muted" style="margin:0"><?= $icon('pin') ?> <?= esc($lugar) ?></p>
          <?php endif; ?>
          <?php if ($rating): ?>
            <div style="margin-top:6px">
              <?= view('Components\LeadFlow\Views\public\directory\_stars', [
                  'value' => $rating['avg'], 'count' => $rating['count'], 'size' => 15,
              ]) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($areas): ?>
        <div style="padding:0 18px 16px">
          <div class="ll-tags">
            <?php foreach ($areas as $a): ?>
              <a class="ll-tag" href="<?= base_url('area/' . rawurlencode($a['slug'])) ?>"><?= esc($a['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <?php // ───────────────────────────────────────────── sobre la firma ?>
    <?php if (! empty($company['description']) || $lugar !== '' || ! empty($company['website'])): ?>
      <section class="ll-card ll-blk">
        <h2><?= $icon('briefcase') ?><?= esc(lang('Lfl.dirAboutFirm')) ?></h2>
        <div class="ll-blk-rule"></div>
        <?php if (! empty($company['description'])): ?>
          <p style="margin:14px 0 0;white-space:pre-line"><?= esc($company['description']) ?></p>
        <?php endif; ?>
        <?php if ($lugar !== '' || ! empty($company['website'])): ?>
          <dl class="ll-kv" style="margin-top:16px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
            <?php if ($lugar !== ''): ?>
              <div>
                <dt><?= esc(lang('Lfl.dirFirmAddress')) ?></dt>
                <dd><?= esc($lugar) ?></dd>
                <?php if ($comoLleg !== ''): ?>
                  <p class="ll-small" style="margin:4px 0 0">
                    <a href="<?= esc($comoLleg, 'attr') ?>" target="_blank" rel="noopener nofollow"><?= esc(lang('Lfl.dirHowToGet')) ?></a>
                  </p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if (! empty($company['website'])): ?>
              <div>
                <dt><?= esc(lang('Lfl.dirWebsite')) ?></dt>
                <dd><a href="<?= esc($company['website'], 'attr') ?>" target="_blank" rel="noopener nofollow"><?= esc($company['website']) ?></a></dd>
              </div>
            <?php endif; ?>
          </dl>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php // ──────────────────────────────────────────────────── servicios ?>
    <?php if ($services): ?>
      <section class="ll-card ll-blk" id="servicios">
        <h2><?= $icon('list') ?><?= esc(lang('Lfl.dirFirmServices')) ?></h2>
        <p class="ll-small ll-muted" style="margin:6px 0 0"><?= esc(lang('Lfl.dirFirmServicesSub')) ?></p>
        <div class="ll-blk-rule"></div>
        <ul class="ll-svc" style="margin-top:4px">
          <?php foreach ($services as $s): ?>
            <li>
              <p class="ll-svc-n" style="margin:0">
                <?= esc($s['name']) ?>
                <?php if (! empty($s['area_name'])): ?>
                  <a class="ll-tag" href="<?= base_url('area/' . rawurlencode((string) $s['area_slug'])) ?>"><?= esc($s['area_name']) ?></a>
                <?php endif; ?>
              </p>
              <?php if (! empty($s['description'])): ?>
                <p class="ll-small ll-muted" style="margin:4px 0 0;white-space:pre-line"><?= esc($s['description']) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php
    /*
     * Abogados. Es el único bloque SIN tarjeta alrededor: cada perfil ya viene en la
     * suya con el mismo partial que el buscador, y una tarjeta dentro de otra se lee
     * como un error de maquetación. Se reutiliza `_card` a propósito — con el marcado
     * copiado bastaría añadir un dato para que apareciera en un sitio y faltara aquí.
     */
    ?>
    <section id="abogados">
      <div class="ll-sec-head">
        <h2 style="font-size:20px"><?= esc(lang('Lfl.dirFirmTeam')) ?></h2>
        <span class="ll-small ll-muted"><?= esc(lang('Lfl.dirFirmProfiles', [count($profiles)])) ?></span>
      </div>
      <div class="ll-grid">
        <?php foreach ($profiles as $p): ?>
          <?= view('Components\LeadFlow\Views\public\directory\_card', ['p' => $p]) ?>
        <?php endforeach; ?>
      </div>
    </section>

    <?php // ──────────────────────────────────────────────────── opiniones ?>
    <?php if ($rating && $reviews): ?>
      <section class="ll-card ll-blk" id="opiniones">
        <h2><?= $icon('spark') ?><?= esc(lang('Lfl.revSectionTitle')) ?></h2>
        <?= view('Components\LeadFlow\Views\public\directory\_rating', [
            'rating' => $rating, 'breakdown' => $breakdown,
        ]) ?>
        <?php foreach ($reviews as $rev): ?>
          <article style="border-top:1px solid var(--ll-line);padding:12px 0">
            <div class="ll-row" style="justify-content:space-between">
              <strong><?= esc($rev['author_name'] ?: lang('Lfl.revAnonymous')) ?></strong>
              <?= view('Components\LeadFlow\Views\public\directory\_stars', [
                  'value' => (float) $rev['score'], 'size' => 13,
              ]) ?>
            </div>
            <?php if (! empty($rev['comment'])): ?>
              <p style="margin:6px 0 0;white-space:pre-line"><?= esc($rev['comment']) ?></p>
            <?php endif; ?>
            <?php if (! empty($rev['reply'])): ?>
              <div style="margin:8px 0 0;padding:10px;border-left:3px solid var(--ll-gold);background:var(--ll-bg)">
                <p class="ll-small" style="margin:0 0 2px;font-weight:600"><?= esc(lang('Lfl.revFirmReply')) ?></p>
                <p class="ll-small" style="margin:0;white-space:pre-line"><?= esc($rev['reply']) ?></p>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
        <?php // El descargo va aquí, donde se leen, y no en el pie. ?>
        <p class="ll-legal" style="margin-top:12px"><?= esc(lang('Lfl.revDisclaimer')) ?></p>
      </section>
    <?php endif; ?>

    <p class="ll-legal"><?= esc(lang('Lfl.dirDisclaimer')) ?></p>
    <?php // Discreto y al final: quien lo necesita lo busca, y como botón competiría
          // con la consulta y haría que la ficha se leyera como una advertencia. ?>
    <p class="ll-small ll-muted" style="margin:8px 0 0">
      <a href="<?= esc(base_url('reportar/' . rawurlencode((string) $company['slug'])), 'attr') ?>" rel="nofollow">
        <?= esc(lang('Lfl.repReport')) ?>
      </a>
    </p>
  </div>

  <?php // ═══════════════════════════════════════════════════ COLUMNA DERECHA ?>
  <aside class="ll-biz-aside">

    <?php // ─────────────────────────────────────────────────────── contacto ?>
    <?php if ($wa !== '' || ! empty($company['contact_phone']) || ! empty($company['contact_email']) || $redes): ?>
      <section class="ll-card ll-blk">
        <h2><?= $icon('phone') ?><?= esc(lang('Lfl.dirFirmContact')) ?></h2>
        <div class="ll-blk-rule" style="margin-bottom:14px"></div>

        <?php if ($wa !== ''): ?>
          <a class="ll-btn ll-btn-block" style="margin-bottom:8px"
             href="https://wa.me/<?= esc($wa, 'attr') ?>" target="_blank" rel="noopener nofollow">
            <?= $icon('whatsapp') ?><?= esc(lang('Lfl.dirWriteWhatsApp')) ?>
          </a>
        <?php endif; ?>

        <dl class="ll-kv">
          <?php if (! empty($company['contact_phone'])): ?>
            <div>
              <dt><?= esc(lang('Lfl.phone')) ?></dt>
              <dd><a href="tel:<?= esc(preg_replace('/[^\d+]/', '', (string) $company['contact_phone']), 'attr') ?>"><?= esc($company['contact_phone']) ?></a></dd>
            </div>
          <?php endif; ?>
          <?php if (! empty($company['contact_email'])): ?>
            <div>
              <dt><?= esc(lang('Lfl.email')) ?></dt>
              <dd><a href="mailto:<?= esc($company['contact_email'], 'attr') ?>"><?= esc($company['contact_email']) ?></a></dd>
            </div>
          <?php endif; ?>
        </dl>

        <?php if ($redes): ?>
          <div class="ll-row" style="margin-top:14px">
            <?php foreach ($redes as $red => $u): ?>
              <a class="ll-btn ll-btn-ghost ll-btn-sm" href="<?= esc($u, 'attr') ?>" target="_blank" rel="noopener nofollow">
                <?= $icon($red) ?><?= esc(ucfirst($red)) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php
    /*
     * Consulta de estado de casos, si esta firma la ofrece.
     *
     * Va ARRIBA de la columna derecha, por encima del QR: quien ya es cliente entra a
     * la ficha a mirar su caso, no a leer sobre la firma. Solo aparece si está
     * conectada en modo real — en simulado la página no existe, y enseñar un enlace
     * que lleva a un 404 es peor que no tenerlo.
     */
    $uscisCfg = (new \Components\LeadFlow\Libraries\Uscis\UscisService())->config((int) $company['id']);
    ?>
    <?php if ($uscisCfg['mode'] === 'live' && $uscisCfg['configured']): ?>
      <section class="ll-card ll-blk">
        <h2><?= $icon('search') ?><?= esc(lang('Lfl.uscPubLink')) ?></h2>
        <p class="ll-small ll-muted" style="margin:6px 0 12px"><?= esc(lang('Lfl.uscPubReceiptHint')) ?></p>
        <a class="ll-btn ll-btn-block" href="<?= esc($url . '/caso', 'attr') ?>">
          <?= $icon('search') ?><?= esc(lang('Lfl.uscPubSubmit')) ?>
        </a>
      </section>
    <?php endif; ?>

    <?php // ───────────────────────── QR de esta página, para imprimirlo ?>
    <?php if (! empty($qr)): ?>
      <section class="ll-card ll-blk ll-qr">
        <h2 style="justify-content:center"><?= $icon('qr') ?><?= esc(lang('Lfl.dirFirmQr')) ?></h2>
        <img src="<?= esc($qr, 'attr') ?>" alt="" width="150" height="150" style="margin-top:12px">
        <p class="ll-small ll-muted" style="margin:10px 0 0"><?= esc(lang('Lfl.dirFirmQrHint')) ?></p>
      </section>
    <?php endif; ?>

    <?php
    /*
     * El formulario, el último. No es la llamada a la acción: quien ya tiene el
     * teléfono arriba no debería tener que rellenar nada. Entra como lead de la
     * firma —sin abogado asignado— y pasa por las mismas defensas que el resto del
     * directorio; el servidor las vuelve a comprobar todas.
     */
    ?>
    <section class="ll-card ll-blk" id="consulta">
      <h2><?= $icon('mail') ?><?= esc(lang('Lfl.dirFirmAsk')) ?></h2>
      <p class="ll-small ll-muted" style="margin:6px 0 0"><?= esc(lang('Lfl.dirFirmAskSub')) ?></p>
      <div class="ll-blk-rule" style="margin-bottom:14px"></div>

      <form method="post" action="<?= esc($url . '/consulta', 'attr') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">

        <div class="ll-hp" aria-hidden="true">
          <label><?= esc(lang('Lfl.pubHoneypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <label class="ll-field" style="margin-bottom:10px">
          <span class="ll-label"><?= esc(lang('Lfl.firstName')) ?> <span class="ll-req">*</span></span>
          <input class="ll-input" type="text" name="first_name" maxlength="80" required value="<?= esc(old('first_name'), 'attr') ?>">
        </label>
        <label class="ll-field" style="margin-bottom:10px">
          <span class="ll-label"><?= esc(lang('Lfl.lastName')) ?> <span class="ll-req">*</span></span>
          <input class="ll-input" type="text" name="last_name" maxlength="80" required value="<?= esc(old('last_name'), 'attr') ?>">
        </label>
        <label class="ll-field" style="margin-bottom:10px">
          <span class="ll-label"><?= esc(lang('Lfl.email')) ?></span>
          <input class="ll-input" type="email" name="email" maxlength="150" value="<?= esc(old('email'), 'attr') ?>">
        </label>
        <label class="ll-field" style="margin-bottom:4px">
          <span class="ll-label"><?= esc(lang('Lfl.phone')) ?></span>
          <input class="ll-input" type="tel" name="phone" maxlength="30" value="<?= esc(old('phone'), 'attr') ?>">
        </label>
        <p class="ll-small ll-muted" style="margin:0 0 10px"><?= esc(lang('Lfl.dirNeedContact')) ?></p>

        <?php if ($areas): ?>
          <label class="ll-field" style="margin-bottom:10px">
            <span class="ll-label"><?= esc(lang('Lfl.dirArea')) ?></span>
            <select class="ll-select" name="area_id">
              <option value=""><?= esc(lang('Lfl.choose')) ?></option>
              <?php foreach ($areas as $a): ?>
                <option value="<?= (int) ($a['id'] ?? 0) ?>"><?= esc($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>

        <label class="ll-field" style="margin-bottom:12px">
          <span class="ll-label"><?= esc(lang('Lfl.dirCase')) ?></span>
          <textarea class="ll-textarea" name="message" maxlength="2000" style="min-height:90px"
            placeholder="<?= esc(lang('Lfl.dirCasePh'), 'attr') ?>"><?= esc(old('message')) ?></textarea>
        </label>

        <?php if ($hasGd): ?>
          <div style="margin-bottom:12px">
            <img src="<?= esc($url . '/captcha', 'attr') ?>" alt="" data-captcha hidden width="150" height="46"
              style="border:1px solid var(--ll-line);border-radius:8px;background:#fff;margin-bottom:8px">
            <label class="ll-field">
              <span class="ll-label"><?= esc(lang('Lfl.pubCaptcha')) ?> <span class="ll-req">*</span></span>
              <input class="ll-input" type="text" name="captcha" inputmode="numeric" autocomplete="off" required>
            </label>
          </div>
          <script>
          // Por JSON para poder renovarla sin recargar; el servidor devuelve la imagen
          // en base64 junto con el código que espera en la sesión.
          (function () {
            var img = document.querySelector('[data-captcha]');
            if (!img) { return; }
            fetch(<?= json_encode($url . '/captcha') ?>, { headers: { 'Accept': 'application/json' } })
              .then(function (r) { return r.json(); })
              .then(function (d) { if (d.img) { img.src = d.img; img.hidden = false; } })
              .catch(function () {});
          })();
          </script>
        <?php endif; ?>

        <label class="ll-row" style="gap:9px;align-items:flex-start;margin-bottom:14px">
          <input type="checkbox" name="consent" value="1" required style="margin-top:4px">
          <span class="ll-small"><?= esc(lang('Lfl.dirConsent')) ?></span>
        </label>

        <button class="ll-btn ll-btn-block" type="submit"><?= $icon('mail') ?><?= esc(lang('Lfl.dirSend')) ?></button>
      </form>
    </section>

    <p class="ll-small"><a href="<?= base_url('firmas') ?>"><?= esc(lang('Lfl.dirBackToFirms')) ?></a></p>
  </aside>
</div>
<?= $this->endSection() ?>
