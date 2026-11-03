<?php
/**
 * Aviso de privacidad y autorización de tratamiento de datos (Ley 1581 de 2012, art. 9).
 * Se muestra antes de cualquier contenido y otra vez cuando el despacho publica una versión nueva.
 * Si el despacho no redactó su aviso, se muestra la plantilla base del componente.
 *
 * @var string $version @var string $text @var array $company
 */
use Components\LegalCase\Libraries\Portal;

require_once __DIR__ . '/_icons.php';
$body = trim($text) !== '' ? $text : lang('Lgc.ptPrivacyDefault', [(string) ($company['name'] ?? '')]);
ob_start(); ?>
<div class="lc-card" style="max-width:640px;margin:24px auto">
  <h1 class="lc-title"><?= lgc_icon('lock') ?> <?= esc(lang('Lgc.ptPrivacyTitle')) ?></h1>
  <p class="lc-muted lc-small"><?= esc(lang('Lgc.ptPrivacyVersion', [$version])) ?></p>
  <div class="lc-pre" style="max-height:50vh;overflow:auto;border:1px solid var(--lc-line);border-radius:10px;padding:12px"><?= esc($body) ?></div>
  <form method="post" action="<?= esc(Portal::url('privacidad'), 'attr') ?>" style="margin-top:14px">
    <?= csrf_field() ?>
    <label style="display:flex;gap:8px;align-items:flex-start">
      <input type="checkbox" name="accept" value="1" required style="width:20px;height:20px;margin-top:2px">
      <span><?= esc(lang('Lgc.ptPrivacyAccept')) ?></span>
    </label>
    <p style="margin-top:12px"><button type="submit" class="lc-btn"><?= lgc_icon('check') ?> <?= esc(lang('Lgc.ptPrivacyContinue')) ?></button></p>
  </form>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/_layout.php';
