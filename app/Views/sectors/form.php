<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta y edición de un sector: es el mismo formulario, cambia el destino.
 *
 * @var array|null $sector
 */
$v      = static fn (string $k, $def = '') => old($k, $sector[$k] ?? $def);
$accion = $sector ? base_url('sectors/edit/' . $sector['id']) : base_url('sectors/create');
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-diagram-3"></i>
        <h5 class="font-semibold"><?= $sector ? lang('Sector.editSector') : lang('Sector.newSector') ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= $accion ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Sector.secIdentity') ?></legend>
                <p class="form-section-hint"><?= lang('Sector.secIdentityHint') ?></p>

                <div class="form-group">
                    <label for="name" class="form-label"><?= lang('Sector.fName') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" required maxlength="100"
                        value="<?= esc($v('name'), 'attr') ?>">
                    <?php // El slug NO se escribe: lo calcula el modelo. Es un
                          // identificador que acaba en una URL pública. ?>
                    <?php if ($sector): ?>
                        <p class="form-hint"><?= lang('Sector.fSlugHint') ?> <code><?= esc($sector['slug']) ?></code></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label"><?= lang('Sector.fDescription') ?></label>
                    <input type="text" id="description" name="description" class="form-input" maxlength="255"
                        value="<?= esc($v('description'), 'attr') ?>">
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Sector.secLook') ?></legend>
                <p class="form-section-hint"><?= lang('Sector.secLookHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="icon" class="form-label"><?= lang('Sector.fIcon') ?></label>
                        <input type="text" id="icon" name="icon" class="form-input" maxlength="60"
                            placeholder="icon-shop" value="<?= esc($v('icon'), 'attr') ?>">
                        <p class="form-hint"><?= lang('Sector.fIconHint') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="color" class="form-label"><?= lang('Sector.fColor') ?></label>
                        <input type="color" id="color" name="color" class="form-input"
                            value="<?= esc($v('color') ?: '#2563eb', 'attr') ?>">
                        <p class="form-hint"><?= lang('Sector.fColorHint') ?></p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Sector.secPublish') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="sort_order" class="form-label"><?= lang('Sector.fOrder') ?></label>
                        <input type="number" id="sort_order" name="sort_order" class="form-input"
                            value="<?= esc((string) $v('sort_order', '0'), 'attr') ?>">
                        <p class="form-hint"><?= lang('Sector.fOrderHint') ?></p>
                    </div>
                    <div class="form-group">
                        <div class="form-check mt-6">
                            <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input"
                                <?= old('is_active', $sector['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active"><?= lang('Sector.fActive') ?></label>
                        </div>
                        <p class="form-hint"><?= lang('Sector.fActiveHint') ?></p>
                    </div>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-save"></i> <?= lang('Sector.save') ?>
                </button>
                <a href="<?= base_url('sectors') ?>" class="btn btn-secondary">
                    <i class="icon icon-x-circle"></i> <?= lang('Sector.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
