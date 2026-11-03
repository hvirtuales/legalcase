<?php
/**
 * El formulario público, en sus cuatro estados y sus dos presentaciones.
 *
 * Estados (`$mode`): `form`, `closed`, `password` y `done`.
 * Presentaciones (`$display`): `card` (ficha centrada) y `fullscreen` (ocupa la
 * ventana entera, una página por pantalla, con la API Fullscreen del navegador).
 *
 * Autosuficiente (contrato 7.3): CSS en línea, SVG en línea, tipografía del
 * sistema. Ni un recurso externo, ni uno del núcleo.
 *
 * @var array  $form
 * @var array  $company
 * @var array  $settings
 * @var string $display
 * @var string $mode
 * @var string $action
 */

use Components\Formularios\Libraries\Icons;

helper('media');

// El color de marca: el del formulario si lo tiene, si no el de la empresa, y
// si tampoco, uno neutro. Ya viene validado; se emite crudo (ver _styles.php).
$brand = (string) ($settings['primaryColor'] ?? '');
if ($brand === '') {
    $candidate = (string) ($company['brand_color'] ?? '');
    $brand     = preg_match('/^#[0-9a-fA-F]{6}$/', $candidate) === 1 ? $candidate : '#2563eb';
}

// Tinta sobre el acento decidida por luminancia: con una marca muy clara el
// blanco no se leería.
$hex = ltrim($brand, '#');
if (strlen($hex) === 3) {
    $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
}
$lum = (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2))) / 255;
$ink = $lum > 0.65 ? '#111827' : '#ffffff';

$isFull   = ($display ?? 'card') === 'fullscreen';
$pageList = $schema['pages'] ?? [];
$wizard   = ($settings['flow'] ?? 'wizard') === 'wizard' && count($pageList) > 1;
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title><?= esc($form['title']) ?></title>
<?= view('Components\Formularios\Views\public\_styles', ['brand' => $brand, 'ink' => $ink]) ?>
</head>
<body class="<?= $isFull ? 'ff-full' : '' ?>">
<main class="ff-wrap">
  <div class="ff-card">
    <div class="ff-accent"></div>

    <header class="ff-head">
      <?php if (!empty($settings['showBrand'])): ?>
        <div class="ff-brand">
          <?php if (!empty($company['logo'])): ?>
            <img src="<?= esc(media_url($company['logo']), 'attr') ?>" alt="">
          <?php endif; ?>
          <?php if (!empty($company['name'])): ?>
            <span><?= esc($company['name']) ?></span>
          <?php endif; ?>
          <?php if ($isFull): ?>
            <button type="button" class="ff-fs-toggle" data-ff-fullscreen
              style="margin-left:auto" title="<?= esc(lang('Frm.goFullscreen'), 'attr') ?>"
              aria-label="<?= esc(lang('Frm.goFullscreen'), 'attr') ?>"><?= Icons::svg('expand') ?></button>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <h1 class="ff-title"><?= esc($form['title']) ?></h1>
      <?php if (($settings['description'] ?? '') !== ''): ?>
        <p class="ff-desc"><?= esc($settings['description']) ?></p>
      <?php endif; ?>
    </header>

    <?php if ($mode === 'closed'): ?>
      <div class="ff-state">
        <?= Icons::svg('lock') ?>
        <h1><?= esc(lang('Frm.pubNotAvailable')) ?></h1>
        <p><?= esc($reason ?? '') ?></p>
      </div>

    <?php elseif ($mode === 'password'): ?>
      <form method="post" action="<?= esc($action, 'attr') ?>" class="ff-body">
        <?= csrf_field() ?>
        <div class="ff-state" style="padding-top:20px">
          <?= Icons::svg('lock') ?>
          <h1><?= esc(lang('Frm.pubPasswordTitle')) ?></h1>
          <p><?= esc(lang('Frm.pubPasswordHint')) ?></p>
          <?php if (!empty($error)): ?>
            <div class="ff-alert ff-alert-bad"><?= esc($error) ?></div>
          <?php endif; ?>
          <input class="ff-input" type="password" name="password" autocomplete="off"
                 style="max-width:320px;margin:0 auto 18px" required>
          <div><button type="submit" class="ff-btn ff-btn-main"><?= esc(lang('Frm.pubEnter')) ?></button></div>
        </div>
      </form>

    <?php elseif ($mode === 'done'): ?>
      <div class="ff-state">
        <?= Icons::svg('check') ?>
        <h1><?= esc(($settings['successTitle'] ?? '') !== '' ? $settings['successTitle'] : lang('Frm.pubThanks')) ?></h1>
        <p><?= esc(($settings['successMessage'] ?? '') !== '' ? $settings['successMessage'] : lang('Frm.pubThanksBody')) ?></p>
        <?php if (!empty($reference)): ?>
          <div class="ff-ref"><?= esc($reference) ?></div>
        <?php endif; ?>
        <div class="ff-nav" style="justify-content:center">
          <?php if (!empty($receipt)): ?>
            <a class="ff-btn ff-btn-main" href="<?= esc($receipt, 'attr') ?>"><?= esc(lang('Frm.pubSeeReceipt')) ?></a>
          <?php endif; ?>
          <?php if (!empty($form['allow_multiple'])): ?>
            <a class="ff-btn ff-btn-ghost" href="<?= esc(base_url('formulario/' . $form['public_token']), 'attr') ?>">
              <?= esc(lang('Frm.pubAnother')) ?></a>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>
      <form method="post" action="<?= esc($action, 'attr') ?>" enctype="multipart/form-data"
            data-ff-form data-ff-wizard="<?= $wizard ? '1' : '0' ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="ts" value="<?= esc($stamp, 'attr') ?>">
        <input type="hidden" name="elapsed" value="0" data-ff-elapsed>
        <p class="ff-trap" aria-hidden="true">
          <label>No rellenar<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </p>

        <div class="ff-body">
          <div class="ff-inner">
            <?php if (!empty($errors)): ?>
              <div class="ff-alert ff-alert-bad">
                <?= esc($errors['__form'] ?? lang('Frm.pubErrorsTitle')) ?>
              </div>
            <?php endif; ?>

            <?= $body ?>
          </div>
        </div>

        <div class="ff-foot">
          <div style="flex:1;min-width:220px">
            <?php if ($wizard && !empty($settings['showProgressBar'])): ?>
              <div class="ff-steps" data-ff-steps>
                <?php foreach ($pageList as $i => $p): ?>
                  <span class="ff-pill<?= $i === 0 ? ' is-now' : '' ?>" data-ff-pill="<?= $i ?>">
                    <?= esc(($i + 1) . '. ' . $p['title']) ?></span>
                <?php endforeach; ?>
              </div>
              <div class="ff-progress"><i data-ff-bar style="width:<?= (int) round(100 / max(1, count($pageList))) ?>%"></i></div>
            <?php endif; ?>

            <?php if (($captcha ?? '') !== ''): ?>
              <div class="ff-captcha">
                <img src="<?= esc($captcha, 'attr') ?>" alt="" width="190" height="60">
                <div>
                  <label class="ff-label" for="ff-captcha"><?= esc(lang('Frm.pubCaptchaLabel')) ?></label>
                  <input class="ff-input" id="ff-captcha" name="captcha" autocomplete="off" required>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($consentOn)): ?>
              <label class="ff-consent">
                <input type="checkbox" name="consent" value="1" data-ff-consent>
                <span><?= esc(($consent ?? '') !== '' ? $consent : lang('Frm.pubConsent')) ?></span>
              </label>
            <?php endif; ?>

            <p class="ff-legal" style="margin-top:12px">
              <?= Icons::svg('lock') ?><?= esc(lang('Frm.pubRequiredNote')) ?>
            </p>
          </div>

          <div class="ff-nav">
            <?php if ($wizard): ?>
              <button type="button" class="ff-btn ff-btn-ghost" data-ff-prev hidden>
                <?= Icons::svg('left') ?><?= esc(lang('Frm.previous')) ?></button>
              <button type="button" class="ff-btn ff-btn-main" data-ff-next>
                <?= esc(lang('Frm.next')) ?><?= Icons::svg('right') ?></button>
            <?php endif; ?>
            <button type="submit" class="ff-btn ff-btn-main" data-ff-submit <?= $wizard ? 'hidden' : '' ?>>
              <?= Icons::svg('send') ?>
              <?= esc(($settings['submitLabel'] ?? '') !== '' ? $settings['submitLabel'] : lang('Frm.submit')) ?>
            </button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!empty($company['name'])): ?>
    <p class="ff-legal" style="justify-content:center;margin:18px 0 0">
      <?= esc(lang('Frm.poweredBy', [$company['name']])) ?>
    </p>
  <?php endif; ?>
</main>

<?php if ($mode === 'form'): ?>
  <script src="<?= base_url('formulario/recurso/form.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
