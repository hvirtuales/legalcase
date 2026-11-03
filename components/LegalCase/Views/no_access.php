<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php /* Sin acceso a la empresa: ni el Super Admin ni el distribuidor leen datos jurídicos sin soporte autorizado. */ ?>
<div class="mx-auto max-w-xl">
    <div class="alert alert-warning">
        <i class="icon icon-shield-lock"></i>
        <span><?= lang('Lgc.noAccessBody') ?></span>
    </div>
</div>
<?= $this->endSection() ?>
