<?php
/**
 * Comprobante de la respuesta: lo único que se le entrega a quien contestó.
 *
 * Se pintan las etiquetas CONGELADAS en la respuesta, no las del formulario de
 * hoy: quien respondió tiene derecho a ver lo que se le preguntó, no lo que se
 * pregunta ahora.
 *
 * @var array  $form
 * @var array  $company
 * @var array  $response
 * @var array  $labels
 * @var array  $values
 * @var array  $files
 * @var string $qr
 */

use Components\Formularios\Libraries\Icons;

helper('media');
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= esc(lang('Frm.receiptOf', [$response['reference']])) ?></title>
<?= view('Components\Formularios\Views\public\_styles', ['brand' => '#111827', 'ink' => '#ffffff']) ?>
</head>
<body>
<main class="ff-wrap">
  <div class="ff-card">
    <div class="ff-accent"></div>
    <header class="ff-head">
      <?php if (!empty($company['logo'])): ?>
        <div class="ff-brand"><img src="<?= esc(media_url($company['logo']), 'attr') ?>" alt=""></div>
      <?php endif; ?>
      <h1 class="ff-title"><?= esc($form['title']) ?></h1>
      <p class="ff-desc"><?= esc(lang('Frm.receiptDate', [$response['created_at']])) ?></p>
    </header>

    <div class="ff-body">
      <div class="ff-ref"><?= esc($response['reference']) ?></div>

      <?php foreach ($labels as $fieldId => $label): ?>
        <?php $v = $values[$fieldId] ?? null; ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--ff-line)">
          <div class="ff-label" style="margin-bottom:2px"><?= esc((string) $label) ?></div>
          <div style="font-size:.95rem">
            <?php if (isset($files[$fieldId])): ?>
              <?= esc(implode(', ', $files[$fieldId])) ?>
            <?php elseif (is_array($v)): ?>
              <?= esc(implode(', ', $v)) ?>
            <?php elseif ($v === null || $v === ''): ?>
              <span style="color:var(--ff-muted)"><?= esc(lang('Frm.noAnswer')) ?></span>
            <?php else: ?>
              <?= nl2br(esc((string) $v)) ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($qr): ?>
        <div style="text-align:center;margin-top:24px">
          <img src="<?= esc($qr, 'attr') ?>" alt="" width="140" height="140">
        </div>
      <?php endif; ?>
    </div>

    <div class="ff-foot" style="justify-content:center">
      <a class="ff-btn ff-btn-main" href="<?= esc(base_url('formulario/r/' . $response['token'] . '/pdf'), 'attr') ?>">
        <?= Icons::svg('send') ?><?= esc(lang('Frm.downloadPdf')) ?>
      </a>
    </div>
  </div>
</main>
</body>
</html>
