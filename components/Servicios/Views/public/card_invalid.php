<?= $this->extend('Components\Servicios\Views\public\_card_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * Enlace de tarjeta inválido o apagado (vendedor o empresa inactivos).
 * Usa el lienzo propio del componente: la tarjeta no depende del núcleo ni
 * siquiera para su página de error.
 */
$ic = require __DIR__ . '/_icons.php';
?>
<div class="dc-stage" style="padding-bottom:16px">
    <div class="dc-wrap">
        <div class="dc-face" style="position:static;padding:40px 24px;text-align:center;align-items:center">
            <span class="dc-chip dc-i" style="width:48px;height:48px;border-radius:14px;font-size:22px"><?= $ic['close'] ?></span>
            <h1 class="dc-name" style="font-size:18px;margin-top:16px"><?= lang('Quo.pubInvalidTitle') ?></h1>
            <p class="dc-bio" style="border:0;padding:0;margin-top:8px"><?= lang('Quo.pubInvalidHint') ?></p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
