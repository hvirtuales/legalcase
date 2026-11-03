<?= $this->extend('install/_layout') ?>

<?= $this->section('content') ?>

<h2 class="mb-1 text-lg font-semibold"><i class="icon icon-shield-check"></i> Requisitos del servidor</h2>
<p class="mb-4 text-sm text-muted">Verifica que el servidor cumpla lo necesario para operar la plataforma.</p>

<div class="overflow-x-auto">
    <table class="table">
        <tbody>
            <?php foreach ($requirements as $r): ?>
                <tr>
                    <td>
                        <span class="font-medium"><?= esc($r['label']) ?></span>
                        <?php if (!$r['required']): ?><span class="badge badge-light ml-1">opcional</span><?php endif; ?>
                        <div class="text-xs text-muted"><?= esc($r['detail']) ?></div>
                    </td>
                    <td class="text-right">
                        <?php if ($r['ok']): ?>
                            <span class="badge badge-success"><i class="icon icon-check-circle"></i> OK</span>
                        <?php else: ?>
                            <span class="badge <?= $r['required'] ? 'badge-danger' : 'badge-warning' ?>">
                                <i class="icon icon-x-circle"></i> <?= $r['required'] ? 'Falta' : 'Ausente' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<form action="<?= base_url('install/requirements') ?>" method="post" class="mt-4 flex items-center justify-between gap-2">
    <?= csrf_field() ?>
    <a href="<?= base_url('install/requirements') ?>" class="btn btn-outline"><i class="icon icon-arrow-repeat"></i> Volver a verificar</a>
    <?php if ($met): ?>
        <button type="submit" class="btn btn-primary">Continuar <i class="icon icon-chevron-right"></i></button>
    <?php else: ?>
        <span class="text-sm text-danger">Corrige los requisitos obligatorios para continuar.</span>
    <?php endif; ?>
</form>

<?= $this->endSection() ?>
