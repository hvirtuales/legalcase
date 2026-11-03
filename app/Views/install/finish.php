<?= $this->extend('install/_layout') ?>

<?= $this->section('content') ?>

<h2 class="mb-1 text-lg font-semibold"><i class="icon icon-check-circle"></i> Verificación final</h2>
<p class="mb-4 text-sm text-muted">Comprobamos que la plataforma quedó lista para operar.</p>

<div class="overflow-x-auto">
    <table class="table">
        <tbody>
            <?php foreach ($health['checks'] as $c): ?>
                <tr>
                    <td class="font-medium"><?= esc($c['label']) ?>
                        <?php if (!empty($c['detail'])): ?><div class="text-xs text-danger"><?= esc($c['detail']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-right">
                        <?php if ($c['ok']): ?>
                            <span class="badge badge-success"><i class="icon icon-check-circle"></i> OK</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="icon icon-x-circle"></i> Falla</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($health['ok']): ?>
    <div class="alert alert-success mt-4"><i class="icon icon-check-circle"></i><span>Todo listo. Finaliza para bloquear el asistente e iniciar sesión.</span></div>
    <form action="<?= base_url('install/finish') ?>" method="post" class="flex items-center justify-between gap-2">
        <?= csrf_field() ?>
        <a href="<?= base_url('install/settings') ?>" class="btn btn-outline"><i class="icon icon-chevron-left"></i> Atrás</a>
        <button type="submit" class="btn btn-success"><i class="icon icon-check-circle"></i> Finalizar instalación</button>
    </form>
<?php else: ?>
    <div class="alert alert-error mt-4"><i class="icon icon-exclamation-circle"></i><span>Hay comprobaciones fallidas. Revisa los pasos anteriores antes de finalizar.</span></div>
    <a href="<?= base_url('install/migrate') ?>" class="btn btn-primary"><i class="icon icon-chevron-left"></i> Revisar</a>
<?php endif; ?>

<?= $this->endSection() ?>
