<?php
/**
 * Tarjeta de producto de la vitrina. Se pinta en el servidor para la primera
 * página; las siguientes las arma el JS con la misma estructura (ver `cardHtml`
 * en `catalog.php`) — si cambia una, cambia la otra.
 *
 * @var array    $p  @var array $ic @var bool $show
 * @var callable $net @var callable $money
 */
?>
<article class="sh-card" data-card data-id="<?= (int) $p['id'] ?>" data-price="<?= $net($p) ?>"
    data-name="<?= esc($p['name'], 'attr') ?>"
    data-code="<?= esc($p['code'], 'attr') ?>"
    data-desc="<?= esc($p['description'], 'attr') ?>"
    data-photo="<?= esc(!empty($p['photo']) ? media_url($p['photo']) : '', 'attr') ?>"
    data-before="<?= $net($p) < (float) $p['unit_price'] ? $money($p['unit_price']) : '' ?>">

    <!-- La foto abre el detalle del producto -->
    <button type="button" class="sh-photo" data-detail aria-label="<?= esc($p['name'], 'attr') ?>">
        <?php if (!empty($p['photo'])): ?>
            <img src="<?= media_url($p['photo']) ?>" alt="<?= esc($p['name'], 'attr') ?>" loading="lazy" decoding="async">
        <?php else: ?>
            <span class="sh-i"><?= $ic['image'] ?></span>
        <?php endif; ?>
    </button>

    <?php if ($p['code'] !== ''): ?>
        <div class="sh-code"><?= esc($p['code']) ?></div>
    <?php endif; ?>
    <h2 class="sh-name"><?= esc($p['name']) ?></h2>

    <?php if ($show): ?>
        <div class="sh-price">
            <?= $money($net($p)) ?>
            <?php if ($net($p) < (float) $p['unit_price']): ?>
                <small><?= lang('Quo.shopBefore') ?> <?= $money($p['unit_price']) ?></small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="sh-qty">
        <button type="button" data-step="-1" aria-label="−">−</button>
        <input type="number" min="0" step="1" value="0" data-qty aria-label="<?= esc($p['name'], 'attr') ?>">
        <button type="button" data-step="1" aria-label="+">+</button>
    </div>
</article>
