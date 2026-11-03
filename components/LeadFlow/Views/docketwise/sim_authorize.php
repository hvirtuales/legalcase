<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/*
 * La «pantalla de consentimiento» del Docketwise simulado. En el modo real, esta
 * pantalla la pinta Docketwise en su dominio; aquí se imita para que el flujo
 * OAuth completo (estado, código, canje por tokens) sea demostrable.
 */
?>
<div class="mx-auto max-w-lg">
    <div class="card">
        <div class="card-header"><i class="icon icon-shield-lock"></i><h5 class="font-semibold"><?= lang('Lfl.simAuthTitle') ?></h5></div>
        <div class="card-body">
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Lfl.simAuthNotice') ?></span></div>
            <p class="mb-3 text-sm"><?= lang('Lfl.simAuthText') ?></p>
            <ul class="mb-4 list-inside list-disc text-sm text-muted">
                <li><?= lang('Lfl.simScope1') ?></li>
                <li><?= lang('Lfl.simScope2') ?></li>
                <li><?= lang('Lfl.simScope3') ?></li>
            </ul>
            <div class="flex gap-2">
                <a href="<?= esc($approve, 'attr') ?>" class="btn btn-primary"><i class="icon icon-check-circle"></i> <?= lang('Lfl.simAuthorize') ?></a>
                <a href="<?= esc($deny, 'attr') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Lfl.cancel') ?></a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
