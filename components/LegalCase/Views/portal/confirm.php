<?php
/** Confirmar la entrada: el enlace del correo solo se consume con este POST. @var string $token */
use Components\LegalCase\Libraries\Portal;

require_once __DIR__ . '/_icons.php';
ob_start(); ?>
<div class="lc-card" style="max-width:460px;margin:24px auto">
  <h1 class="lc-title"><?= lgc_icon('lock') ?> <?= esc(lang('Lgc.ptConfirmTitle')) ?></h1>
  <p class="lc-muted"><?= esc(lang('Lgc.ptConfirmBody')) ?></p>
  <form method="post" action="<?= esc(Portal::url('entrar/' . $token), 'attr') ?>">
    <?= csrf_field() ?>
    <button type="submit" class="lc-btn"><?= lgc_icon('check') ?> <?= esc(lang('Lgc.ptEnter')) ?></button>
  </form>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/_layout.php';
