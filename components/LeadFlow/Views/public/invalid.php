<?= $this->extend('Components\LeadFlow\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * Un solo 404 para «no existe», «borrador», «cerrado» y «el plan no lo incluye»:
 * distinguirlos convertiría la URL en una sonda de lo que tiene contratado cada firma.
 */
include __DIR__ . '/_icons.php';
?>
<main class="lf-wrap">
    <section class="lf-card lf-ok">
        <?= lfl_icon('alert') ?>
        <h2><?= lang('Lfl.pubInvalidTitle') ?></h2>
        <p class="lf-hint"><?= lang('Lfl.pubInvalidText') ?></p>
    </section>
</main>
<?= $this->endSection() ?>
