<?php
/** Respuesta única tras pedir un enlace (no revela si el correo existe). */
use Components\LegalCase\Libraries\Portal;

require_once __DIR__ . '/_icons.php';
ob_start(); ?>
<div class="lc-card" style="max-width:460px;margin:24px auto">
  <h1 class="lc-title"><?= lgc_icon('mail') ?> <?= esc(lang('Lgc.ptSentTitle')) ?></h1>
  <p><?= esc(lang('Lgc.ptSentBody', [(int) (Portal::LOGIN_TTL / 60)])) ?></p>
  <p><a class="lc-btn lc-btn-ghost" href="<?= esc(Portal::url(), 'attr') ?>"><?= lgc_icon('back') ?> <?= esc(lang('Lgc.ptBack')) ?></a></p>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/_layout.php';
