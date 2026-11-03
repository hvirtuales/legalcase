<?= $this->extend('Components\Contabilidad\Views\public\_layout') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body text-center">
        <div class="mb-2 text-5xl text-success"><i class="icon icon-check-circle"></i></div>
        <h5 class="text-lg font-semibold">¡Cuenta de cobro enviada!</h5>
        <p class="text-sm text-muted">Tu solicitud fue recibida y está <strong>pendiente de revisión</strong>.</p>
        <div class="mt-4 inline-block rounded-lg border border-line bg-surface-muted px-6 py-3">
            <div class="text-xs text-muted">Referencia</div>
            <div class="font-mono text-xl font-bold text-primary"><?= esc($ok['reference']) ?></div>
            <div class="mt-1 text-sm">Total: <strong class="font-mono"><?= number_format((float) $ok['total'], 2) ?></strong></div>
        </div>
        <div class="mt-4">
            <a href="<?= base_url('contabilidad/solicitud/' . $company['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-plus-circle"></i> Enviar otra</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
