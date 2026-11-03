<?php
/** Enlace inválido o recurso inexistente: el mismo mensaje, sin distinguir la causa. @var ?bool $notFound */
use Components\LegalCase\Libraries\Portal;

require_once __DIR__ . '/_icons.php';
ob_start(); ?>
<div class="lc-card" style="max-width:460px;margin:24px auto">
  <h1 class="lc-title"><?= lgc_icon('info') ?> <?= esc($title) ?></h1>
  <p><?= esc(lang(!empty($notFound) ? 'Lgc.ptNotFoundBody' : 'Lgc.ptInvalidBody')) ?></p>
  <p><a class="lc-btn lc-btn-ghost" href="<?= esc(Portal::url(), 'attr') ?>"><?= lgc_icon('back') ?> <?= esc(lang('Lgc.ptBack')) ?></a></p>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/_layout.php';
