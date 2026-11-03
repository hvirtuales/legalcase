<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d); ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-list"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger"><ul class="list-inside list-disc">
                <?php foreach ((array) session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.categories') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.categoriesHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group md:col-span-2">
                        <label for="name" class="form-label"><?= lang('Quo.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="120"
                            value="<?= esc($v('name'), 'attr') ?>" placeholder="<?= esc(lang('Quo.categoryPlaceholder'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="sort_order" class="form-label"><?= lang('Quo.sortOrder') ?></label>
                        <input type="number" id="sort_order" name="sort_order" class="form-input" step="1"
                            value="<?= esc($v('sort_order', 0), 'attr') ?>">
                        <small class="form-hint"><?= lang('Quo.sortOrderHint') ?></small>
                    </div>
                </div>

                <div class="form-group mb-2">
                    <label for="description" class="form-label"><?= lang('Quo.description') ?></label>
                    <input type="text" id="description" name="description" class="form-input" maxlength="255"
                        value="<?= esc($v('description'), 'attr') ?>">
                </div>

                <label class="form-check mb-2" for="is_active">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                        <?= $v('is_active', 1) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Quo.categoryActive') ?></span>
                </label>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Quo.save') ?></button>
                <a href="<?= base_url('servicios/categorias') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Quo.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
