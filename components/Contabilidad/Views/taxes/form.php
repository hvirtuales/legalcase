<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$tax    = $tax ?? [];
$isEdit = !empty($tax['id']);
$val    = static fn (string $k, $def = '') => old($k, $tax[$k] ?? $def);
$action = $isEdit ? base_url('contabilidad/impuestos/editar/' . $tax['id']) : base_url('contabilidad/impuestos/nuevo');
?>
<div class="card">
    <div class="card-header"><i class="icon icon-percent"></i><h5 class="font-semibold"><?= $isEdit ? 'Editar impuesto' : 'Nuevo impuesto' ?></h5></div>
    <div class="card-body">
        <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><span><?= esc(session()->getFlashdata('error')) ?></span></div><?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?><div class="alert alert-danger"><ul class="list-inside list-disc"><?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                <div class="form-group md:col-span-2">
                    <label for="name" class="form-label">Nombre *</label>
                    <input type="text" class="form-input" id="name" name="name" value="<?= esc($val('name'), 'attr') ?>" required maxlength="120">
                </div>
                <div class="form-group">
                    <label for="code" class="form-label">Código *</label>
                    <input type="text" class="form-input" id="code" name="code" value="<?= esc($val('code'), 'attr') ?>" required maxlength="20">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                <div class="form-group">
                    <label for="category" class="form-label">Categoría *</label>
                    <select class="form-select" id="category" name="category">
                        <?php foreach (['iva' => 'IVA', 'retencion' => 'Retención', 'other' => 'Otro'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= $val('category', 'iva') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="rate" class="form-label">Tasa %</label>
                    <input type="number" step="0.0001" class="form-input" id="rate" name="rate" value="<?= esc($val('rate', 0), 'attr') ?>">
                </div>
                <div class="form-group">
                    <label for="sort_order" class="form-label">Orden</label>
                    <input type="number" class="form-input" id="sort_order" name="sort_order" value="<?= esc($val('sort_order', 0), 'attr') ?>">
                </div>
            </div>
            <label class="form-check" for="is_additive">
                <input class="form-check-input" type="checkbox" id="is_additive" name="is_additive" value="1" <?= $val('is_additive', 0) ? 'checked' : '' ?>>
                <span class="form-check-label">Aditivo (suma al neto; si no, resta)</span>
            </label>
            <label class="form-check mb-4" for="is_active">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $val('is_active', 1) ? 'checked' : '' ?>>
                <span class="form-check-label">Activo</span>
            </label>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
                <a href="<?= base_url('contabilidad/impuestos') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
