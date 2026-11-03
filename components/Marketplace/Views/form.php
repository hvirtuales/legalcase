<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta y edición de una publicación. Campos agrupados por decisión (§7.1 del
 * contrato): qué publicas → detalle → condiciones → publicación.
 *
 * @var array  $listing @var string $tagsText @var array $categories @var string $action
 */
$v    = static fn (string $k, $def = '') => old($k, $listing[$k] ?? $def);
$meta = require __DIR__ . '/_meta.php';
$types = ['product', 'service', 'need', 'opportunity'];
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-clipboard"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger">
                <ul class="list-inside list-disc">
                    <?php foreach ((array) session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- 1. Qué publicas -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Mkt.sectionWhat') ?></legend>
                <p class="form-section-hint"><?= lang('Mkt.sectionWhatHint') ?></p>

                <div class="form-group">
                    <label class="form-label"><?= lang('Mkt.type') ?> <span class="text-danger">*</span></label>
                    <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                        <?php foreach ($types as $t): ?>
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-line bg-surface p-3 hover:border-primary">
                                <input type="radio" name="type" value="<?= $t ?>" required
                                    <?= (string) $v('type', 'product') === $t ? 'checked' : '' ?>>
                                <span class="text-sm">
                                    <i class="icon <?= esc($meta['typeIcon']($t)) ?>"></i>
                                    <?= esc($meta['typeLabel']($t)) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group mb-2 md:w-1/2">
                    <label for="category_id" class="form-label"><?= lang('Mkt.category') ?></label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value=""><?= lang('Mkt.allCategories') ?></option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $v('category_id', 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>

            <!-- 2. Detalle -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Mkt.sectionDetail') ?></legend>
                <p class="form-section-hint"><?= lang('Mkt.sectionDetailHint') ?></p>

                <div class="form-group">
                    <label for="title" class="form-label"><?= lang('Mkt.titleField') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="title" name="title" class="form-input" required minlength="5" maxlength="180"
                        value="<?= esc($v('title'), 'attr') ?>" placeholder="<?= esc(lang('Mkt.titlePlaceholder'), 'attr') ?>">
                </div>

                <div class="form-group">
                    <label for="summary" class="form-label"><?= lang('Mkt.summary') ?></label>
                    <input type="text" id="summary" name="summary" class="form-input" maxlength="255"
                        value="<?= esc($v('summary'), 'attr') ?>">
                    <small class="form-hint"><?= lang('Mkt.summaryHint') ?></small>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label"><?= lang('Mkt.description') ?></label>
                    <textarea id="description" name="description" class="form-textarea" rows="5"><?= esc($v('description')) ?></textarea>
                </div>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="tags" class="form-label"><?= lang('Mkt.tagsField') ?></label>
                        <input type="text" id="tags" name="tags" class="form-input" value="<?= esc(old('tags', $tagsText), 'attr') ?>"
                            placeholder="<?= esc(lang('Mkt.tagsPlaceholder'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Mkt.tagsHint') ?></small>
                    </div>
                    <div class="form-group mb-2">
                        <label for="image" class="form-label"><?= lang('Mkt.image') ?></label>
                        <?php if (!empty($listing['image'])): ?>
                            <div class="mb-2"><img src="<?= media_url($listing['image']) ?>" alt=""
                                class="aspect-[8/3] w-full max-w-xs rounded border border-line object-cover"></div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image" class="form-input" data-preview
                            accept="<?= esc(\App\Libraries\UploadService::IMAGE_TYPES, 'attr') ?>">
                        <small class="form-hint"><?= lang('Mkt.imageHint') ?></small>
                    </div>
                </div>
            </fieldset>

            <!-- 3. Condiciones -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Mkt.sectionPrice') ?></legend>
                <p class="form-section-hint"><?= lang('Mkt.sectionPriceHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group">
                        <label for="price_type" class="form-label"><?= lang('Mkt.priceType') ?></label>
                        <select id="price_type" name="price_type" class="form-select">
                            <option value="quote" <?= $v('price_type', 'quote') === 'quote' ? 'selected' : '' ?>><?= lang('Mkt.priceQuote') ?></option>
                            <option value="fixed" <?= $v('price_type') === 'fixed' ? 'selected' : '' ?>><?= lang('Mkt.price') ?></option>
                            <option value="from" <?= $v('price_type') === 'from' ? 'selected' : '' ?>><?= lang('Mkt.priceFrom') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="price_amount" class="form-label"><?= lang('Mkt.priceAmount') ?></label>
                        <input type="number" step="0.01" min="0" id="price_amount" name="price_amount" class="form-input"
                            value="<?= esc($v('price_amount', '0'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="price_currency" class="form-label"><?= lang('Mkt.currency') ?></label>
                        <input type="text" id="price_currency" name="price_currency" class="form-input" maxlength="3"
                            value="<?= esc($v('price_currency', 'COP'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="city" class="form-label"><?= lang('Mkt.city') ?></label>
                        <input type="text" id="city" name="city" class="form-input" maxlength="100"
                            value="<?= esc($v('city'), 'attr') ?>">
                    </div>
                </div>

                <div class="form-group mb-2 md:w-1/3">
                    <label for="valid_until" class="form-label"><?= lang('Mkt.validUntil') ?></label>
                    <input type="date" id="valid_until" name="valid_until" class="form-input"
                        value="<?= esc(substr((string) $v('valid_until'), 0, 10), 'attr') ?>">
                </div>
            </fieldset>

            <!-- 4. Publicación -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Mkt.sectionPublish') ?></legend>
                <p class="form-section-hint"><?= lang('Mkt.sectionPublishHint') ?></p>

                <div class="form-group mb-2 md:w-1/3">
                    <label for="status" class="form-label"><?= lang('Mkt.statusCol') ?></label>
                    <select id="status" name="status" class="form-select">
                        <option value="published" <?= $v('status', 'published') === 'published' ? 'selected' : '' ?>><?= lang('Mkt.statusPublished') ?></option>
                        <option value="draft" <?= $v('status') === 'draft' ? 'selected' : '' ?>><?= lang('Mkt.statusDraft') ?></option>
                        <option value="paused" <?= $v('status') === 'paused' ? 'selected' : '' ?>><?= lang('Mkt.statusPaused') ?></option>
                        <option value="closed" <?= $v('status') === 'closed' ? 'selected' : '' ?>><?= lang('Mkt.statusClosed') ?></option>
                    </select>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Mkt.save') ?></button>
                <a href="<?= base_url('marketplace/mis-publicaciones') ?>" class="btn btn-secondary">
                    <i class="icon icon-x-circle"></i> <?= lang('Mkt.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
