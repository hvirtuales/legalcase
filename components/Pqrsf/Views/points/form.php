<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta y edición de un punto.
 *
 * El token no se edita: una vez impreso el QR, cambiarlo dejaría carteles
 * muertos pegados en la pared.
 *
 * @var array $row @var string $action
 */
$editing = !empty($row['id']);
?>

<div class="mx-auto max-w-2xl">
    <div class="card">
        <div class="card-header">
            <i class="icon icon-qr-code"></i>
            <h5 class="font-semibold"><?= $editing ? lang('Pqr.pointsEdit') : lang('Pqr.pointsNew') ?></h5>
        </div>
        <div class="card-body">
            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <i class="icon icon-exclamation-circle"></i>
                    <span><?= implode(' ', (array) session()->getFlashdata('errors')) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= esc($action) ?>">
                <?= csrf_field() ?>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Pqr.pointWhere') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.pointWhereHint') ?></p>

                    <div class="form-group">
                        <label for="name" class="form-label"><?= lang('Pqr.pointName') ?> *</label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="150"
                            value="<?= esc(old('name', $row['name'] ?? ''), 'attr') ?>"
                            placeholder="<?= esc(lang('Pqr.pointNamePh'), 'attr') ?>">
                    </div>

                    <div class="form-group">
                        <label for="area" class="form-label"><?= lang('Pqr.pointArea') ?></label>
                        <input type="text" id="area" name="area" class="form-input" maxlength="150"
                            value="<?= esc(old('area', $row['area'] ?? ''), 'attr') ?>"
                            placeholder="<?= esc(lang('Pqr.pointAreaPh'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Pqr.pointAreaHint') ?></small>
                    </div>

                    <div class="form-group">
                        <label for="notes" class="form-label"><?= lang('Pqr.pointNotes') ?></label>
                        <textarea id="notes" name="notes" rows="2" class="form-input"><?= esc(old('notes', $row['notes'] ?? '')) ?></textarea>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input"
                            <?= old('is_active', $row['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active" class="form-check-label">
                            <?= lang('Pqr.pointActive') ?>
                            <span class="block text-xs text-muted"><?= lang('Pqr.pointActiveHint') ?></span>
                        </label>
                    </div>
                </fieldset>

                <?php if ($editing): ?>
                    <fieldset class="form-section">
                        <legend class="form-section-title"><?= lang('Pqr.pointUrl') ?></legend>
                        <p class="form-section-hint"><?= lang('Pqr.pointUrlHint') ?></p>
                        <code class="block break-all rounded-md border border-line p-2 text-xs">
                            <?= esc(site_url('pqrsf/p/' . $row['token'])) ?>
                        </code>
                        <a href="<?= base_url('pqrsf/puntos/cartel/' . (int) $row['id']) ?>" target="_blank" rel="noopener"
                            class="btn btn-secondary btn-sm mt-3">
                            <i class="icon icon-printer"></i> <?= lang('Pqr.poster') ?>
                        </a>
                    </fieldset>
                <?php endif; ?>

                <div class="mt-4 flex justify-end gap-2">
                    <a href="<?= base_url('pqrsf/puntos') ?>" class="btn btn-ghost"><?= lang('Pqr.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Pqr.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
