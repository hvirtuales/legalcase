<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$box    = $box ?? [];
$isEdit = !empty($box['id']);
$val    = static fn (string $k, $def = '') => old($k, $box[$k] ?? $def);
$action = $isEdit ? base_url('contabilidad/cajas/editar/' . $box['id']) : base_url('contabilidad/cajas/nueva');
?>
<div class="card">
    <div class="card-header"><i class="icon icon-wallet"></i><h5 class="font-semibold"><?= $isEdit ? 'Editar caja menor' : 'Nueva caja menor' ?></h5></div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?><div class="alert alert-danger"><ul class="list-inside list-disc"><?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                <div class="form-group md:col-span-2"><label class="form-label">Nombre *</label><input type="text" name="name" class="form-input" value="<?= esc($val('name'), 'attr') ?>" required maxlength="120" placeholder="Caja principal"></div>
                <div class="form-group"><label class="form-label">Saldo inicial</label><input type="number" step="0.01" name="initial_balance" class="form-input text-right" value="<?= esc($val('initial_balance', 0), 'attr') ?>" <?= $isEdit ? '' : '' ?>></div>
            </div>
            <div class="form-group"><label class="form-label">Descripción</label><input type="text" name="description" class="form-input" value="<?= esc($val('description'), 'attr') ?>" maxlength="255"></div>
            <?php if ($isEdit): ?><p class="mb-3 text-xs text-muted">Nota: cambiar el saldo inicial recalcula el saldo actual (que se reproduce a partir de los movimientos).</p><?php endif; ?>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
                <a href="<?= base_url('contabilidad/cajas') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
