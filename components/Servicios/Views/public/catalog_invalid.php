<?= $this->extend('Components\Servicios\Views\public\_shop_layout') ?>

<?= $this->section('content') ?>
<?php
/** Enlace de catálogo inválido, sin publicar o con el vendedor dado de baja. */
$ic = require __DIR__ . '/_icons.php';
?>
<div class="sh-done">
    <div class="sh-mark sh-i"><?= $ic['close'] ?></div>
    <h1><?= lang('Quo.pubInvalidTitle') ?></h1>
    <p><?= lang('Quo.pubInvalidHint') ?></p>
</div>
<?= $this->endSection() ?>
