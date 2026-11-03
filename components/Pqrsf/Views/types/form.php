<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta y edición de un tipo.
 *
 * El código no se edita después de crearlo: es lo que quedó guardado en cada
 * solicitud radicada con ese tipo.
 *
 * @var array $row @var int $used @var string $action
 */
$editing = !empty($row['id']);
?>

<div class="mx-auto max-w-2xl">
    <div class="card">
        <div class="card-header">
            <i class="icon icon-list-check"></i>
            <h5 class="font-semibold"><?= $editing ? lang('Pqr.typesEdit') : lang('Pqr.typesNew') ?></h5>
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
                    <legend class="form-section-title"><?= lang('Pqr.typeWhat') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.typeWhatHint') ?></p>

                    <div class="form-group">
                        <label for="name" class="form-label"><?= lang('Pqr.typeName') ?> *</label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="60"
                            value="<?= esc(old('name', $row['name'] ?? ''), 'attr') ?>"
                            placeholder="<?= esc(lang('Pqr.typeNamePh'), 'attr') ?>">
                    </div>

                    <div class="form-group">
                        <label for="code" class="form-label"><?= lang('Pqr.typeCode') ?></label>
                        <?php if ($editing): ?>
                            <p class="rounded-md border border-line px-3 py-2 font-mono text-sm"><?= esc($row['code']) ?></p>
                            <small class="form-hint"><?= lang('Pqr.typeCodeFixed') ?></small>
                        <?php else: ?>
                            <input type="text" id="code" name="code" class="form-input" maxlength="20"
                                value="<?= esc(old('code'), 'attr') ?>" placeholder="<?= esc(lang('Pqr.typeCodePh'), 'attr') ?>">
                            <small class="form-hint"><?= lang('Pqr.typeCodeHint') ?></small>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Pqr.typeRules') ?></legend>
                    <p class="form-section-hint"><?= lang('Pqr.typeRulesHint') ?></p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="form-group">
                            <label for="days" class="form-label"><?= lang('Pqr.typeDays') ?> *</label>
                            <input type="number" id="days" name="days" class="form-input" min="1" max="365" required
                                value="<?= (int) old('days', $row['days'] ?? 15) ?>">
                        </div>
                        <div class="form-group">
                            <label for="sort_order" class="form-label"><?= lang('Pqr.typeOrder') ?></label>
                            <input type="number" id="sort_order" name="sort_order" class="form-input" min="0" max="999"
                                value="<?= (int) old('sort_order', $row['sort_order'] ?? 0) ?>">
                            <small class="form-hint"><?= lang('Pqr.typeOrderHint') ?></small>
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input"
                            <?= old('is_active', $row['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active" class="form-check-label">
                            <?= lang('Pqr.typeActive') ?>
                            <span class="block text-xs text-muted"><?= lang('Pqr.typeActiveHint') ?></span>
                        </label>
                    </div>

                    <?php if ($editing && $used > 0): ?>
                        <div class="alert alert-info mt-3">
                            <i class="icon icon-info-circle"></i>
                            <span><?= lang('Pqr.typeUsedNote', [$used]) ?></span>
                        </div>
                    <?php endif; ?>
                </fieldset>

                <div class="mt-4 flex justify-end gap-2">
                    <a href="<?= base_url('pqrsf/tipos') ?>" class="btn btn-ghost"><?= lang('Pqr.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Pqr.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
