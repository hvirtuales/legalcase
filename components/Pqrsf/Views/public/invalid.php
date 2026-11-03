<?= $this->extend('Components\Pqrsf\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php
/** Enlace inválido: punto dado de baja, empresa inactiva o ticket inexistente. */
$ic = require __DIR__ . '/_icons.php';
?>
<div class="pq-wrap" style="max-width:30rem;text-align:center;padding-top:60px">
    <div class="pq-card">
        <div class="pq-i" style="font-size:32px;color:var(--pq-muted);justify-content:center"><?= $ic['close'] ?></div>
        <h2 style="margin-top:10px"><?= lang('Pqr.pubInvalidTitle') ?></h2>
        <p class="pq-hint" style="margin:6px 0 0"><?= lang('Pqr.pubInvalidHint') ?></p>
    </div>
</div>
<?= $this->endSection() ?>
