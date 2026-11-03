<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$money = static fn ($n) => number_format((float) $n, 2);
$typeBadge = [
    'ingreso' => '<span class="badge badge-success">Ingreso</span>',
    'egreso'  => '<span class="badge badge-danger">Egreso</span>',
    'arqueo'  => '<span class="badge badge-secondary">Arqueo</span>',
    'ajuste'  => '<span class="badge badge-warning">Ajuste</span>',
];
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <a href="<?= base_url('contabilidad/cajas') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-chevron-left"></i> Volver</a>
    <div class="flex gap-1">
        <a href="<?= base_url('contabilidad/cajas/editar/' . $box['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i> Editar caja</a>
        <?= view('partials/delete_button', ['action' => base_url('contabilidad/cajas/eliminar/' . $box['id']), 'confirm' => '¿Eliminar la caja y todos sus movimientos?', 'label' => 'Eliminar']) ?>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <!-- Movimientos -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-wallet"></i><h6 class="font-semibold">Movimientos</h6></div>
            <div class="card-body">
                <?php if (empty($movements)): ?>
                    <p class="py-6 text-center text-muted">Sin movimientos. Registra el primero a la derecha.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table text-sm">
                            <thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th class="text-right">Monto</th><th class="text-right">Saldo</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($movements as $m): ?>
                                    <tr>
                                        <td class="whitespace-nowrap"><?= esc($m['date'] ?: substr((string) $m['created_at'], 0, 10)) ?></td>
                                        <td><?= $typeBadge[$m['type']] ?? esc($m['type']) ?></td>
                                        <td><?= esc($m['concept'] ?: '—') ?><?php if (!empty($m['reference'])): ?><div class="text-xs text-muted"><?= esc($m['reference']) ?></div><?php endif; ?></td>
                                        <td class="text-right font-mono <?= $m['type'] === 'ingreso' ? 'text-success' : ($m['type'] === 'egreso' ? 'text-danger' : 'text-muted') ?>">
                                            <?= $m['type'] === 'ingreso' ? '+' : ($m['type'] === 'egreso' ? '−' : '') ?><?= $money(abs((float) $m['amount'])) ?>
                                        </td>
                                        <td class="text-right font-mono"><?= $money($m['balance_after']) ?></td>
                                        <td class="text-right"><?= view('partials/delete_button', ['action' => base_url('contabilidad/cajas/' . $box['id'] . '/movimiento/eliminar/' . $m['id']), 'confirm' => '¿Eliminar este movimiento?']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Saldo + registrar movimiento -->
    <div class="space-y-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-sm font-semibold"><?= esc($box['name']) ?></div>
                <div class="mt-2 text-xs uppercase tracking-wide text-muted">Saldo actual</div>
                <div class="font-mono text-3xl font-bold <?= $balance >= 0 ? 'text-success' : 'text-danger' ?>"><?= $money($balance) ?></div>
                <div class="mt-1 text-xs text-muted">Inicial: <?= $money($box['initial_balance']) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-plus-circle"></i><h6 class="font-semibold">Registrar movimiento</h6></div>
            <div class="card-body">
                <form action="<?= base_url('contabilidad/cajas/' . $box['id'] . '/movimiento') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select name="type" id="mvType" class="form-select">
                            <option value="ingreso">Ingreso (entra efectivo)</option>
                            <option value="egreso">Egreso (sale efectivo)</option>
                            <option value="arqueo">Arqueo (efectivo contado)</option>
                            <option value="ajuste">Ajuste (fijar saldo)</option>
                        </select>
                    </div>
                    <div class="form-group" id="mvAmountBox">
                        <label class="form-label">Monto</label>
                        <input type="number" step="0.01" name="amount" class="form-input text-right" value="">
                    </div>
                    <div class="form-group" id="mvBalanceBox" hidden>
                        <label class="form-label">Efectivo contado (nuevo saldo)</label>
                        <input type="number" step="0.01" name="balance_after" class="form-input text-right" value="">
                    </div>
                    <div class="form-group"><label class="form-label">Concepto</label><input type="text" name="concept" class="form-input" maxlength="255"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="form-group"><label class="form-label">Fecha</label><input type="date" name="date" class="form-input" value="<?= date('Y-m-d') ?>"></div>
                        <div class="form-group"><label class="form-label">Referencia</label><input type="text" name="reference" class="form-input" maxlength="60"></div>
                    </div>
                    <button class="btn btn-primary btn-sm w-full"><i class="icon icon-save"></i> Registrar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        var type = document.getElementById('mvType');
        if (!type) return;
        function sync() {
            var counted = (type.value === 'arqueo' || type.value === 'ajuste');
            document.getElementById('mvAmountBox').hidden = counted;
            document.getElementById('mvBalanceBox').hidden = !counted;
        }
        type.addEventListener('change', sync);
        sync();
    })();
</script>
<?= $this->endSection() ?>
