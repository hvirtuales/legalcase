<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Detalle de una respuesta.
 *
 * Se pintan las etiquetas CONGELADAS en la respuesta (`labels`), no las del
 * formulario de hoy: si alguien renombró una pregunta el mes pasado, esta
 * respuesta tiene que seguir diciendo lo que se preguntó entonces.
 *
 * @var array $response
 * @var array $form
 * @var array $labels
 * @var array $values
 * @var array $files
 */
$byField = [];
foreach ($files as $f) {
    $byField[(string) $f['field_id']][] = $f;
}
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <a href="<?= base_url('formularios/respuestas/' . $form['id']) ?>" class="btn btn-secondary btn-sm">
        <i class="icon icon-arrow-left"></i> <?= esc($form['title']) ?>
    </a>
    <div class="flex gap-2">
        <form method="post" action="<?= base_url('formularios/respuesta/leida/' . $response['id']) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-secondary btn-sm">
                <i class="icon icon-check-circle"></i>
                <?= (int) $response['is_read'] === 1 ? lang('Frm.markUnread') : lang('Frm.markRead') ?>
            </button>
        </form>
        <a class="btn btn-secondary btn-sm" href="<?= base_url('formulario/r/' . $response['token']) ?>" target="_blank" rel="noopener">
            <i class="icon icon-eye"></i> <?= lang('Frm.receipt') ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-envelope"></i>
            <h5 class="font-mono font-semibold"><?= esc($response['reference']) ?></h5>
        </div>
        <span class="text-xs text-muted">
            <?= esc($response['created_at']) ?> &middot; <?= esc($response['device']) ?>
            <?php $s = (int) $response['duration_seconds']; ?>
            &middot; <?= $s >= 60 ? lang('Frm.minutes', [intdiv($s, 60), $s % 60]) : lang('Frm.seconds', [$s]) ?>
        </span>
    </div>

    <div class="card-body space-y-3">
        <?php foreach ($labels as $fieldId => $label): ?>
            <?php $v = $values[$fieldId] ?? null; ?>
            <div class="rounded-xl border border-line p-3">
                <div class="mb-1 text-xs font-bold uppercase tracking-wide text-muted"><?= esc((string) $label) ?></div>
                <div class="text-sm">
                    <?php if (!empty($byField[$fieldId])): ?>
                        <?php foreach ($byField[$fieldId] as $att): ?>
                            <a class="btn btn-secondary btn-sm mr-1" href="<?= base_url('formularios/adjunto/' . $att['id']) ?>">
                                <i class="icon icon-download"></i> <?= esc($att['original_name']) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php elseif (is_array($v)): ?>
                        <?= esc(implode(', ', $v)) ?>
                    <?php elseif ($v === null || $v === ''): ?>
                        <span class="text-muted italic"><?= lang('Frm.noAnswer') ?></span>
                    <?php elseif ($v === '1' || $v === '0'): ?>
                        <?= $v === '1' ? lang('Frm.yes') : lang('Frm.no') ?>
                    <?php else: ?>
                        <?= nl2br(esc((string) $v)) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$labels): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Frm.noAnswer') ?></span></div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
