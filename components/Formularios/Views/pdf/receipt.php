<?php
/**
 * Comprobante en PDF.
 *
 * HTML simple basado en TABLAS y estilos en línea: dompdf no soporta flexbox ni
 * grid, así que NO se reutiliza la vista web. Es la misma decisión que toman
 * Contabilidad y PQRSF con sus plantillas.
 *
 * @var array $form
 * @var array $company
 * @var array $response
 * @var array $labels
 * @var array $values
 * @var array $files
 */

$logo = (new Components\Formularios\Libraries\PdfService())->localImage($company['logo'] ?? null);
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8">
<style>
  body{font-family:"DejaVu Sans",sans-serif;font-size:10pt;color:#111827;margin:0}
  .head{border-bottom:2px solid #111827;padding-bottom:10px;margin-bottom:16px}
  .head td{vertical-align:middle}
  .name{font-size:9pt;color:#6b7280;text-transform:uppercase;letter-spacing:.05em}
  h1{font-size:15pt;margin:2px 0 0}
  .ref{font-size:12pt;font-weight:bold;border:1px solid #d1d5db;padding:6px 12px;display:inline-block;margin-bottom:14px}
  table.data{width:100%;border-collapse:collapse}
  table.data td{padding:7px 0;border-bottom:1px solid #e5e7eb;vertical-align:top}
  td.k{width:38%;font-weight:bold;color:#374151}
  .muted{color:#9ca3af}
  .foot{margin-top:24px;padding-top:10px;border-top:1px solid #e5e7eb;font-size:8pt;color:#6b7280}
</style>
</head>
<body>
  <table class="head" width="100%"><tr>
    <?php if ($logo): ?><td width="70"><img src="<?= esc($logo, 'attr') ?>" height="46"></td><?php endif; ?>
    <td>
      <?php if (!empty($company['name'])): ?><div class="name"><?= esc($company['name']) ?></div><?php endif; ?>
      <h1><?= esc($form['title']) ?></h1>
    </td>
  </tr></table>

  <div class="ref"><?= esc($response['reference']) ?></div>
  <p class="muted"><?= esc(lang('Frm.receiptDate', [$response['created_at']])) ?></p>

  <table class="data">
    <?php foreach ($labels as $fieldId => $label): ?>
      <?php $v = $values[$fieldId] ?? null; ?>
      <tr>
        <td class="k"><?= esc((string) $label) ?></td>
        <td>
          <?php if (isset($files[$fieldId])): ?>
            <?= esc(implode(', ', $files[$fieldId])) ?>
          <?php elseif (is_array($v)): ?>
            <?= esc(implode(', ', $v)) ?>
          <?php elseif ($v === null || $v === ''): ?>
            <span class="muted"><?= esc(lang('Frm.noAnswer')) ?></span>
          <?php else: ?>
            <?= nl2br(esc((string) $v)) ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="foot">
    <?= esc(lang('Frm.receiptOf', [$response['reference']])) ?>
    <?php if (!empty($company['name'])): ?> &middot; <?= esc($company['name']) ?><?php endif; ?>
  </div>
</body>
</html>
