<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Armado del catálogo, en el orden en que se decide: qué es → con qué precios →
 * qué productos → quién lo comparte.
 *
 * @var array $row @var array $products @var array $sellers
 * @var array $picked ids elegidos @var array $assigned vendedores con enlace
 */
$v         = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$pickedIds = old('products') ?: $picked;
$assignIds = old('sellers') ?: array_map(static fn ($a) => (int) $a['seller_id'], $assigned);
$meta      = require __DIR__ . '/../_meta.php';
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-book"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger"><ul class="list-inside list-disc">
                <?php foreach ((array) session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <!-- 1. Qué es -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.catalogSection') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.catalogSectionHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="name" class="form-label"><?= lang('Quo.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="150"
                            value="<?= esc($v('name'), 'attr') ?>" placeholder="<?= esc(lang('Quo.catalogNamePlaceholder'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="headline" class="form-label"><?= lang('Quo.catalogHeadline') ?></label>
                        <input type="text" id="headline" name="headline" class="form-input" maxlength="255"
                            value="<?= esc($v('headline'), 'attr') ?>">
                    </div>
                </div>

                <div class="form-group mb-2">
                    <label for="intro" class="form-label"><?= lang('Quo.catalogIntro') ?></label>
                    <textarea id="intro" name="intro" class="form-textarea" rows="2"><?= esc($v('intro')) ?></textarea>
                </div>
            </fieldset>

            <!-- 2. Con qué precios -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.catalogPricesSection') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.catalogPricesHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="price_tier" class="form-label"><?= lang('Quo.priceTier') ?></label>
                        <select id="price_tier" name="price_tier" class="form-select">
                            <option value=""><?= lang('Quo.tierBase') ?></option>
                            <?php foreach (\Components\Servicios\Models\ProductPriceModel::TIERS as $t): ?>
                                <option value="<?= $t ?>" <?= (int) $v('price_tier') === $t ? 'selected' : '' ?>><?= lang('Quo.tierN', [$t]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group md:col-span-2">
                        <label class="form-check mt-6" for="show_prices">
                            <input type="checkbox" class="form-check-input" id="show_prices" name="show_prices" value="1"
                                <?= $v('show_prices', 1) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= lang('Quo.catalogShowPrices') ?></span>
                        </label>
                    </div>
                </div>
            </fieldset>

            <!-- 3. Qué productos -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.catalogProducts') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.catalogProductsHint') ?></p>

                <?php if (!$products): ?>
                    <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.noProducts') ?></span></div>
                <?php else: ?>
                    <input type="text" class="form-input mb-2 md:w-1/2" id="prodFilter"
                        placeholder="<?= esc(lang('Quo.searchProduct'), 'attr') ?>">
                    <div class="max-h-72 overflow-y-auto rounded-md border border-line">
                        <?php foreach ($products as $p): ?>
                            <label class="flex items-center gap-3 border-b border-line px-3 py-2 last:border-0 hover:bg-canvas" data-prod>
                                <input type="checkbox" class="form-check-input" name="products[]" value="<?= (int) $p['id'] ?>"
                                    <?= in_array((int) $p['id'], array_map('intval', (array) $pickedIds), true) ? 'checked' : '' ?>>
                                <?php if (!empty($p['photo'])): ?>
                                    <img src="<?= media_url($p['photo']) ?>" alt="" class="size-9 shrink-0 rounded border border-line object-cover">
                                <?php else: ?>
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded bg-surface-muted text-muted"><i class="icon icon-image"></i></span>
                                <?php endif; ?>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold"><?= esc($p['name']) ?></span>
                                    <span class="block text-xs text-muted"><?= esc($p['code']) ?></span>
                                </span>
                                <span class="text-sm text-muted"><?= $meta['money']($p['unit_price']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>

            <!-- 4. Quién lo comparte -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.catalogSellers') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.catalogSellersHint') ?></p>

                <?php if (!$sellers): ?>
                    <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.noSellers') ?></span></div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        <?php foreach ($sellers as $s): ?>
                            <label class="flex items-center gap-2 rounded-md border border-line px-3 py-2">
                                <input type="checkbox" class="form-check-input" name="sellers[]" value="<?= (int) $s['id'] ?>"
                                    <?= in_array((int) $s['id'], array_map('intval', (array) $assignIds), true) ? 'checked' : '' ?>>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold"><?= esc($s['name']) ?></span>
                                    <span class="block text-xs text-muted"><?= esc($s['phone'] ?: lang('Quo.sellerNoPhone')) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <label class="form-check mt-3" for="is_published">
                    <input type="checkbox" class="form-check-input" id="is_published" name="is_published" value="1"
                        <?= $v('is_published', 0) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Quo.catalogPublish') ?></span>
                </label>
                <small class="form-hint"><?= lang('Quo.catalogPublishHint') ?></small>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Quo.save') ?></button>
                <a href="<?= base_url('servicios/catalogo-digital') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Quo.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<script>
// Filtro del listado de productos: solo esconde filas, no toca lo marcado.
(function () {
    var box = document.getElementById('prodFilter');
    if (!box) { return; }
    box.addEventListener('input', function () {
        var q = box.value.trim().toLowerCase();
        document.querySelectorAll('[data-prod]').forEach(function (row) {
            row.style.display = !q || row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
        });
    });
}());
</script>
<?= $this->endSection() ?>
