<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$account = $account ?? [];
$isEdit  = !empty($account['id']);
$val     = static fn (string $k, $def = '') => old($k, $account[$k] ?? $def);
$action  = $isEdit ? base_url('contabilidad/cuentas-bancarias/editar/' . $account['id']) : base_url('contabilidad/cuentas-bancarias/nueva');
$types   = ['Ahorros', 'Corriente', 'Fiduciaria', 'Otro'];
?>
<div class="card">
    <div class="card-header"><i class="icon icon-landmark"></i><h5 class="font-semibold"><?= $isEdit ? 'Editar cuenta bancaria' : 'Nueva cuenta bancaria' ?></h5></div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?><div class="alert alert-danger"><ul class="list-inside list-disc"><?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                <div class="form-group md:col-span-1"><label class="form-label">Banco *</label><input type="text" name="bank_name" class="form-input" value="<?= esc($val('bank_name'), 'attr') ?>" required maxlength="120"></div>
                <div class="form-group">
                    <label class="form-label">Tipo de cuenta</label>
                    <select name="account_type" class="form-select">
                        <?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= $val('account_type', 'Ahorros') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Número de cuenta</label><input type="text" name="account_number" class="form-input font-mono" value="<?= esc($val('account_number'), 'attr') ?>" maxlength="50"></div>
            </div>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group"><label class="form-label">Titular</label><input type="text" name="account_holder" class="form-input" value="<?= esc($val('account_holder'), 'attr') ?>" maxlength="200"></div>
                <div class="form-group"><label class="form-label">Saldo inicial</label><input type="number" step="0.01" name="initial_balance" class="form-input text-right" value="<?= esc($val('initial_balance', 0), 'attr') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Notas</label><textarea name="notes" class="form-textarea" rows="2"><?= esc($val('notes')) ?></textarea></div>
            <label class="form-check mb-4" for="is_active">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $val('is_active', 1) ? 'checked' : '' ?>>
                <span class="form-check-label">Cuenta activa (disponible como origen de pagos)</span>
            </label>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
                <a href="<?= base_url('contabilidad/cuentas-bancarias') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
