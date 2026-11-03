<?php
/** Pedir un enlace de acceso. @var ?string $error */
use Components\LegalCase\Libraries\Portal;

require_once __DIR__ . '/_icons.php';
ob_start(); ?>
<div class="lc-card" style="max-width:460px;margin:24px auto">
  <h1 class="lc-title"><?= lgc_icon('lock') ?> <?= esc(lang('Lgc.ptLoginTitle')) ?></h1>
  <p class="lc-muted"><?= esc(lang('Lgc.ptLoginIntro')) ?></p>
  <?php if (!empty($error)): ?><div class="lc-alert lc-err" role="alert"><?= esc($error) ?></div><?php endif; ?>
  <form method="post" action="<?= esc(Portal::url('acceso'), 'attr') ?>">
    <?= csrf_field() ?>
    <label class="lc-label" for="email"><?= esc(lang('Lgc.fEmail')) ?></label>
    <input class="lc-in" type="email" id="email" name="email" required autocomplete="email" inputmode="email" maxlength="190">
    <p style="margin-top:16px"><button type="submit" class="lc-btn"><?= lgc_icon('mail') ?> <?= esc(lang('Lgc.ptSendLink')) ?></button></p>
  </form>
  <p class="lc-muted lc-small"><?= esc(lang('Lgc.ptLoginNote')) ?></p>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/_layout.php';
