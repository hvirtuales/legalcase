<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$account = $account ?? [];
$isEdit  = !empty($account['id']);
$val     = static fn (string $k, $def = '') => old($k, $account[$k] ?? $def);
$action  = $isEdit ? base_url('contabilidad/puc/editar/' . $account['id']) : base_url('contabilidad/puc/nueva');
?>
<div class="card">
    <div class="card-header"><i class="icon icon-diagram-3"></i><h5 class="font-semibold"><?= $isEdit ? 'Editar cuenta PUC' : 'Nueva cuenta PUC' ?></h5></div>
    <div class="card-body">
        <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><span><?= esc(session()->getFlashdata('error')) ?></span></div><?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?><div class="alert alert-danger"><ul class="list-inside list-disc"><?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                <div class="form-group">
                    <label for="code" class="form-label">Código *</label>
                    <input type="text" class="form-input font-mono" id="code" name="code" value="<?= esc($val('code'), 'attr') ?>" required maxlength="20">
                </div>
                <div class="form-group md:col-span-2">
                    <label for="name" class="form-label">Nombre *</label>
                    <input type="text" class="form-input" id="name" name="name" value="<?= esc($val('name'), 'attr') ?>" required maxlength="200">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                <div class="form-group">
                    <label for="type" class="form-label">Tipo *</label>
                    <select class="form-select" id="type" name="type">
                        <?php foreach (['activo', 'pasivo', 'patrimonio', 'ingreso', 'gasto', 'costo'] as $t): ?>
                            <option value="<?= $t ?>" <?= $val('type', 'activo') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nature" class="form-label">Naturaleza *</label>
                    <select class="form-select" id="nature" name="nature">
                        <option value="debito" <?= $val('nature', 'debito') === 'debito' ? 'selected' : '' ?>>Débito</option>
                        <option value="credito" <?= $val('nature') === 'credito' ? 'selected' : '' ?>>Crédito</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="level" class="form-label">Nivel (1–4)</label>
                    <input type="number" class="form-input" id="level" name="level" value="<?= esc($val('level', 1), 'attr') ?>" min="1" max="4">
                </div>
                <div class="form-group">
                    <label for="parent_code" class="form-label">Código padre</label>
                    <input type="text" class="form-input font-mono" id="parent_code" name="parent_code" value="<?= esc($val('parent_code'), 'attr') ?>" maxlength="20">
                </div>
            </div>
            <div class="form-group">
                <label for="description" class="form-label">Descripción</label>
                <input type="text" class="form-input" id="description" name="description" value="<?= esc($val('description'), 'attr') ?>" maxlength="255">
            </div>
            <label class="form-check mb-4" for="is_active">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $val('is_active', 1) ? 'checked' : '' ?>>
                <span class="form-check-label">Activa</span>
            </label>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
                <a href="<?= base_url('contabilidad/puc') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
