<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d); ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-box-seam"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger"><ul class="list-inside list-disc">
                <?php foreach ((array) session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.products') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="code" class="form-label"><?= lang('Quo.code') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="code" name="code" class="form-input" required maxlength="60" value="<?= esc($v('code'), 'attr') ?>">
                    </div>
                    <div class="form-group md:col-span-2">
                        <label for="name" class="form-label"><?= lang('Quo.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="200" value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="category_id" class="form-label"><?= lang('Quo.category') ?></label>
                        <div class="input-group">
                            <select id="category_id" name="category_id" class="form-select">
                                <option value=""><?= lang('Quo.uncategorized') ?></option>
                                <?php foreach (($categories ?? []) as $id => $name): ?>
                                    <option value="<?= (int) $id ?>" <?= (int) $v('category_id') === (int) $id ? 'selected' : '' ?>>
                                        <?= esc($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-secondary" id="cat-add"
                                title="<?= esc(lang('Quo.categoriesNew'), 'attr') ?>"><i class="icon icon-plus-circle"></i></button>
                        </div>
                        <small class="form-hint"><?= lang('Quo.categoryHint') ?></small>
                    </div>
                    <div class="form-group md:col-span-2">
                        <label for="description" class="form-label"><?= lang('Quo.description') ?></label>
                        <textarea id="description" name="description" class="form-textarea" rows="2"><?= esc($v('description')) ?></textarea>
                    </div>
                </div>

                <div class="form-group mb-2 md:w-1/2">
                    <label for="photo" class="form-label"><?= lang('Quo.productPhoto') ?></label>
                    <?php if (!empty($row['photo'])): ?>
                        <div class="mb-2">
                            <img src="<?= media_url($row['photo']) ?>" alt=""
                                class="size-20 rounded-lg border border-line object-cover">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="photo" name="photo" class="form-input" data-preview
                        accept="<?= esc(\App\Libraries\UploadService::IMAGE_TYPES, 'attr') ?>">
                    <small class="form-hint"><?= lang('Quo.productPhotoHint') ?></small>
                </div>
            </fieldset>

            <script>
            // Alta rápida de categoría sin salir del formulario del producto.
            (function () {
                var btn = document.getElementById('cat-add');
                var sel = document.getElementById('category_id');
                btn.addEventListener('click', function () {
                    var name = window.prompt(<?= json_encode(lang('Quo.categoryPrompt')) ?>);
                    if (!name) { return; }

                    var body = new FormData();
                    body.append('name', name);
                    body.append('is_active', '1');
                    body.append('<?= csrf_token() ?>', document.querySelector('input[name="<?= csrf_token() ?>"]').value);

                    fetch('<?= base_url('servicios/categorias/rapido') ?>', { method: 'POST', body: body })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (!j.ok) { window.alert(j.message); return; }
                            var o = document.createElement('option');
                            o.value = j.category.id; o.textContent = j.category.name; o.selected = true;
                            sel.appendChild(o);
                        });
                });
            }());
            </script>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.unitPrice') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.secItemsHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-5">
                    <div class="form-group">
                        <label for="unit_price" class="form-label"><?= lang('Quo.unitPrice') ?></label>
                        <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" class="form-input"
                            value="<?= esc($v('unit_price', 0), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="default_quantity" class="form-label"><?= lang('Quo.defaultQty') ?></label>
                        <input type="number" step="0.01" min="0" id="default_quantity" name="default_quantity" class="form-input"
                            value="<?= esc($v('default_quantity', 1), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="discount_type" class="form-label"><?= lang('Quo.discountType') ?></label>
                        <select id="discount_type" name="discount_type" class="form-select">
                            <option value="%" <?= $v('discount_type', '%') === '%' ? 'selected' : '' ?>>%</option>
                            <option value="$" <?= $v('discount_type') === '$' ? 'selected' : '' ?>>$</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="discount_value" class="form-label"><?= lang('Quo.discountValue') ?></label>
                        <input type="number" step="0.01" min="0" id="discount_value" name="discount_value" class="form-input"
                            value="<?= esc($v('discount_value', 0), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="tax_percent" class="form-label"><?= lang('Quo.taxPercent') ?></label>
                        <input type="number" step="0.01" min="0" id="tax_percent" name="tax_percent" class="form-input"
                            value="<?= esc($v('tax_percent', 0), 'attr') ?>">
                    </div>
                </div>

                <!-- Los 3 valores del producto. Cada uno lleva sus propias
                     condiciones; el cliente indica cuál se le aplica. Un valor
                     SIN precio no existe: ese cliente cotiza con las condiciones
                     base de arriba. -->
                <div class="mt-2 rounded-md border border-line bg-canvas p-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted"><?= lang('Quo.tiers') ?></p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-muted">
                                    <th class="pb-1 pr-2"><?= lang('Quo.tier') ?></th>
                                    <th class="pb-1 pr-2"><?= lang('Quo.tierLabel') ?></th>
                                    <th class="pb-1 pr-2"><?= lang('Quo.unitPrice') ?></th>
                                    <th class="pb-1 pr-2"><?= lang('Quo.colQuantity') ?></th>
                                    <th class="pb-1 pr-2"><?= lang('Quo.discountType') ?></th>
                                    <th class="pb-1 pr-2"><?= lang('Quo.discountValue') ?></th>
                                    <th class="pb-1"><?= lang('Quo.taxPercent') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (\Components\Servicios\Models\ProductPriceModel::TIERS as $t): ?>
                                    <?php $r = $tiers[$t] ?? []; ?>
                                    <tr>
                                        <td class="py-1 pr-2 font-semibold text-muted"><?= (int) $t ?></td>
                                        <td class="py-1 pr-2">
                                            <input type="text" maxlength="60" class="form-input" name="tiers[<?= $t ?>][label]"
                                                value="<?= esc($r['label'] ?? '', 'attr') ?>"
                                                placeholder="<?= esc(lang('Quo.tierLabelPlaceholder'), 'attr') ?>">
                                        </td>
                                        <td class="py-1 pr-2">
                                            <input type="number" step="0.01" min="0" class="form-input" name="tiers[<?= $t ?>][unit_price]"
                                                value="<?= isset($r['unit_price']) ? esc((string) (float) $r['unit_price'], 'attr') : '' ?>"
                                                placeholder="<?= esc(lang('Quo.tierEmpty'), 'attr') ?>">
                                        </td>
                                        <td class="py-1 pr-2">
                                            <input type="number" step="0.01" min="0" class="form-input" name="tiers[<?= $t ?>][quantity]"
                                                value="<?= esc((string) (float) ($r['quantity'] ?? 1), 'attr') ?>">
                                        </td>
                                        <td class="py-1 pr-2">
                                            <select class="form-select" name="tiers[<?= $t ?>][discount_type]">
                                                <option value="%" <?= ($r['discount_type'] ?? '%') === '%' ? 'selected' : '' ?>>%</option>
                                                <option value="$" <?= ($r['discount_type'] ?? '') === '$' ? 'selected' : '' ?>>$</option>
                                            </select>
                                        </td>
                                        <td class="py-1 pr-2">
                                            <input type="number" step="0.01" min="0" class="form-input" name="tiers[<?= $t ?>][discount_value]"
                                                value="<?= esc((string) (float) ($r['discount_value'] ?? 0), 'attr') ?>">
                                        </td>
                                        <td class="py-1">
                                            <input type="number" step="0.01" min="0" class="form-input" name="tiers[<?= $t ?>][tax_percent]"
                                                value="<?= esc((string) (float) ($r['tax_percent'] ?? 0), 'attr') ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-muted"><?= lang('Quo.tiersHint') ?></p>
                </div>

                <div class="form-group mt-2">
                    <label for="details_url" class="form-label"><?= lang('Quo.detailsUrl') ?></label>
                    <input type="url" id="details_url" name="details_url" class="form-input" maxlength="255"
                        value="<?= esc($v('details_url'), 'attr') ?>" placeholder="https://…">
                    <small class="form-hint"><?= lang('Quo.detailsUrlHint') ?></small>
                </div>

                <label class="form-check mb-2" for="is_active">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                        <?= $v('is_active', 1) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Quo.isActive') ?></span>
                </label>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Quo.save') ?></button>
                <a href="<?= base_url('servicios/productos') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Quo.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
