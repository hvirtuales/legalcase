<?= $this->extend('Components\Servicios\Views\public\_shop_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * Pedido recibido. Ya quedó guardado como cotización; aquí solo se le entrega al
 * cliente su referencia y el botón para avisarle al vendedor por WhatsApp.
 *
 * @var array   $catalog @var array $company @var array $seller @var array $quote
 * @var ?string $whatsapp @var string $backUrl
 */
$ic = require __DIR__ . '/_icons.php';
?>

<header class="sh-top">
    <div class="sh-wrap sh-top-in">
        <?php if (!empty($company['logo'])): ?>
            <img class="sh-logo" src="<?= media_url($company['logo']) ?>" alt="">
        <?php endif; ?>
        <span class="sh-brand"><?= esc($company['name']) ?></span>
    </div>
</header>

<div class="sh-done">
    <div class="sh-mark sh-i"><?= $ic['check'] ?></div>
    <h1><?= lang('Quo.orderDoneTitle') ?></h1>
    <p><?= lang('Quo.orderDoneHint', [$seller['name']]) ?></p>
    <span class="sh-ref"><?= esc($quote['number']) ?></span>

    <div>
        <?php if ($whatsapp): ?>
            <a class="sh-btn" href="<?= esc($whatsapp, 'attr') ?>" id="waBtn" target="_blank" rel="noopener">
                <span class="sh-i"><?= $ic['whatsapp'] ?></span> <?= lang('Quo.orderSendWa') ?>
            </a>
        <?php endif; ?>
    </div>

    <p style="margin-top:26px">
        <a class="sh-btn sh-btn-ghost" href="<?= esc($backUrl, 'attr') ?>">
            <span class="sh-i"><?= $ic['back'] ?></span> <?= lang('Quo.orderBack') ?>
        </a>
    </p>
</div>

<?php if ($whatsapp): ?>
    <script>
    // Se abre WhatsApp solo; si el navegador lo bloquea, queda el botón.
    setTimeout(function () { window.open(document.getElementById('waBtn').href, '_blank'); }, 700);
    </script>
<?php endif; ?>
<?= $this->endSection() ?>
