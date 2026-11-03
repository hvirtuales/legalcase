<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body text-center">
        <?php if (empty($v)): ?>
            <div class="mb-2 text-4xl text-danger"><i class="icon icon-x-circle"></i></div>
            <h5 class="font-semibold">Comprobante no encontrado</h5>
            <p class="text-sm text-muted">El comprobante solicitado no existe o fue eliminado.</p>
        <?php else: ?>
            <?php $isAnulado = $v['status'] === 'anulado'; ?>
            <div class="mb-2 text-4xl <?= $isAnulado ? 'text-danger' : 'text-success' ?>">
                <i class="icon <?= $isAnulado ? 'icon-x-circle' : 'icon-check-circle' ?>"></i>
            </div>
            <h5 class="font-semibold"><?= $isAnulado ? 'Comprobante ANULADO' : 'Comprobante válido' ?></h5>
            <div class="font-mono text-2xl font-bold text-primary"><?= esc($v['number']) ?></div>

            <div class="mt-4 space-y-1 text-left text-sm">
                <div class="flex justify-between border-b border-line py-1"><span class="text-muted">Empresa</span><span><?= esc($company['name'] ?? '—') ?></span></div>
                <div class="flex justify-between border-b border-line py-1"><span class="text-muted">Tipo</span><span><?= esc($type['name'] ?? '—') ?></span></div>
                <div class="flex justify-between border-b border-line py-1"><span class="text-muted">Fecha</span><span><?= esc($v['date']) ?></span></div>
                <div class="flex justify-between border-b border-line py-1"><span class="text-muted">Beneficiario</span><span><?= esc($v['beneficiary_name'] ?: '—') ?></span></div>
                <div class="flex justify-between border-b border-line py-1"><span class="text-muted">Total</span><span class="font-mono font-semibold"><?= number_format((float) $v['total_amount'], 2) ?></span></div>
                <div class="flex justify-between py-1"><span class="text-muted">Estado</span><span><?= esc(ucfirst($v['status'])) ?></span></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
