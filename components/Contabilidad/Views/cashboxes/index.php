<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-wallet"></i><h5 class="font-semibold">Cajas menores</h5></div>
        <?php if ($boxes !== null): ?>
            <a href="<?= base_url('contabilidad/cajas/nueva') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> Nueva caja</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($boxes === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span>Selecciona una <strong>empresa activa</strong> para gestionar sus cajas menores.</span></div>
        <?php elseif (empty($boxes)): ?>
            <p class="py-8 text-center text-muted">No hay cajas menores. Crea la primera para registrar movimientos de efectivo.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($boxes as $b): ?>
                    <a href="<?= base_url('contabilidad/cajas/' . $b['id']) ?>" class="block rounded-lg border border-line p-4 transition hover:border-primary hover:shadow-sm">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <div class="truncate font-semibold"><?= esc($b['name']) ?></div>
                                <div class="truncate text-xs text-muted"><?= esc($b['description'] ?: 'Sin descripción') ?></div>
                            </div>
                            <i class="icon icon-wallet text-2xl text-primary"></i>
                        </div>
                        <div class="mt-3 text-xs uppercase tracking-wide text-muted">Saldo actual</div>
                        <div class="font-mono text-xl font-bold <?= $b['balance'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format((float) $b['balance'], 2) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
