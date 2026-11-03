<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * El cotizador. Secciones en el orden en que se decide (§7.1): documento →
 * partes → introducción → ítems → términos → resumen.
 *
 * @var array $quote @var array $items @var array $clients @var array $sellers
 */
$meta = require __DIR__ . '/_meta.php';
$v    = static fn (string $k, $d = '') => old($k, $quote[$k] ?? $d);
$cur  = (string) $v('currency', 'COP');
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-receipt"></i>
            <h5 class="font-semibold"><?= esc($title) ?></h5>
        </div>
        <a href="<?= base_url('servicios/cotizaciones') ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-chevron-left"></i> <?= lang('Quo.back') ?>
        </a>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger"><ul class="list-inside list-disc">
                <?php foreach ((array) session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post" id="quoteForm">
            <?= csrf_field() ?>

            <!-- 1. Documento -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.secDocument') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.secDocumentHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <div class="form-group">
                        <label for="doc_type" class="form-label"><?= lang('Quo.docType') ?></label>
                        <select id="doc_type" name="doc_type" class="form-select">
                            <?php foreach (\Components\Servicios\Models\QuoteModel::DOC_TYPES as $t): ?>
                                <option value="<?= esc($t, 'attr') ?>" <?= $v('doc_type') === $t ? 'selected' : '' ?>><?= esc($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="issue_date" class="form-label"><?= lang('Quo.issueDate') ?></label>
                        <input type="date" id="issue_date" name="issue_date" class="form-input"
                            value="<?= esc(substr((string) $v('issue_date', date('Y-m-d')), 0, 10), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="validity_days" class="form-label"><?= lang('Quo.validityDays') ?></label>
                        <input type="number" min="0" id="validity_days" name="validity_days" class="form-input"
                            value="<?= esc($v('validity_days', 15), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="currency" class="form-label"><?= lang('Quo.currency') ?></label>
                        <select id="currency" name="currency" class="form-select">
                            <?php foreach (\Components\Servicios\Models\QuoteModel::CURRENCIES as $c): ?>
                                <option value="<?= $c ?>" <?= $cur === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group mb-2">
                        <label for="payment_form" class="form-label"><?= lang('Quo.paymentForm') ?></label>
                        <select id="payment_form" name="payment_form" class="form-select">
                            <?php foreach (\Components\Servicios\Models\QuoteModel::PAYMENT_FORMS as $f): ?>
                                <option value="<?= esc($f, 'attr') ?>" <?= $v('payment_form') === $f ? 'selected' : '' ?>><?= esc($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <label for="payment_method" class="form-label"><?= lang('Quo.paymentMethod') ?></label>
                        <select id="payment_method" name="payment_method" class="form-select">
                            <?php foreach (\Components\Servicios\Models\QuoteModel::PAYMENT_METHODS as $m): ?>
                                <option value="<?= esc($m, 'attr') ?>" <?= $v('payment_method') === $m ? 'selected' : '' ?>><?= esc($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <label for="retention_percent" class="form-label"><?= lang('Quo.retention') ?></label>
                        <input type="number" step="0.01" min="0" id="retention_percent" name="retention_percent"
                            class="form-input" value="<?= esc($v('retention_percent', 0), 'attr') ?>">
                        <small class="form-hint"><?= lang('Quo.retentionHint') ?></small>
                    </div>
                </div>
            </fieldset>

            <!-- 2. Cliente y vendedor -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.secParties') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.secPartiesHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group mb-2">
                        <label for="client_id" class="form-label"><?= lang('Quo.client') ?></label>
                        <div class="flex gap-2">
                            <select id="client_id" name="client_id" class="form-select">
                                <option value=""><?= lang('Quo.selectClient') ?></option>
                                <?php foreach ($clients as $c): ?>
                                    <?php /* El valor del cliente viaja en el option: el cotizador lo aplica al añadir productos. */ ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= (int) $v('client_id', 0) === (int) $c['id'] ? 'selected' : '' ?>
                                        data-tier="<?= (int) ($c['price_tier'] ?? 0) ?>">
                                        <?= esc($c['name']) ?><?= $c['nit'] ? ' — ' . esc($c['nit']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?= base_url('servicios/clientes/nuevo') ?>" target="_blank"
                                class="btn btn-secondary" title="<?= esc(lang('Quo.clientsNew'), 'attr') ?>">+</a>
                        </div>
                        <small class="form-hint" id="priceListHint"></small>
                    </div>
                    <div class="form-group mb-2">
                        <label for="seller_id" class="form-label"><?= lang('Quo.seller') ?></label>
                        <div class="flex gap-2">
                            <select id="seller_id" name="seller_id" class="form-select">
                                <option value=""><?= lang('Quo.selectSeller') ?></option>
                                <?php foreach ($sellers as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>" <?= (int) $v('seller_id', 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                        <?= esc($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?= base_url('servicios/vendedores/nuevo') ?>" target="_blank"
                                class="btn btn-secondary" title="<?= esc(lang('Quo.sellersNew'), 'attr') ?>">+</a>
                        </div>
                    </div>
                </div>
            </fieldset>

            <!-- 3. Introducción -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.secIntro') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.secIntroHint') ?></p>
                <?= view('Components\Servicios\Views\_editor', [
                    'name'        => 'intro_html',
                    'value'       => (string) $v('intro_html'),
                    'placeholder' => lang('Quo.secIntroHint'),
                ]) ?>
            </fieldset>

            <!-- 4. Ítems -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.secItems') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.secItemsHint') ?></p>

                <div class="mb-3 flex flex-wrap items-end gap-2">
                    <div class="form-group mb-0 grow">
                        <div class="input-group">
                            <span class="input-group-text"><i class="icon icon-search"></i></span>
                            <input type="text" id="productSearch" class="form-input" autocomplete="off"
                                placeholder="<?= esc(lang('Quo.searchProduct'), 'attr') ?>">
                        </div>
                        <div id="productResults" class="relative"></div>
                    </div>
                    <button type="button" id="addLine" class="btn btn-secondary btn-sm mb-1">
                        <i class="icon icon-plus-circle"></i> <?= lang('Quo.addLine') ?>
                    </button>
                    <a href="<?= base_url('servicios/productos/nuevo') ?>" target="_blank" class="btn btn-ghost btn-sm mb-1">
                        <?= lang('Quo.productsNew') ?>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width:9rem"><?= lang('Quo.colCode') ?></th>
                                <th><?= lang('Quo.colProduct') ?></th>
                                <th style="width:9rem" class="text-right"><?= lang('Quo.colUnitPrice') ?></th>
                                <th style="width:6rem" class="text-right"><?= lang('Quo.colQuantity') ?></th>
                                <th style="width:10rem"><?= lang('Quo.colDiscount') ?></th>
                                <th style="width:6rem" class="text-right"><?= lang('Quo.colTax') ?></th>
                                <th style="width:9rem" class="text-right"><?= lang('Quo.colTotal') ?></th>
                                <th style="width:3rem"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody"></tbody>
                    </table>
                </div>
                <p id="noItems" class="py-4 text-center text-sm text-muted"><?= lang('Quo.noItemsYet') ?></p>
            </fieldset>

            <!-- 5. Términos -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.secTerms') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.secTermsHint') ?></p>
                <?= view('Components\Servicios\Views\_editor', [
                    'name'        => 'terms_html',
                    'value'       => (string) $v('terms_html'),
                    'placeholder' => lang('Quo.secTerms'),
                ]) ?>
            </fieldset>

            <!-- 6. Resumen -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.secSummary') ?></legend>

                <label class="form-check mb-3" for="show_detail">
                    <input type="checkbox" class="form-check-input" id="show_detail" name="show_detail" value="1"
                        <?= $v('show_detail', 1) ? 'checked' : '' ?>>
                    <span class="form-check-label">
                        <span class="font-semibold"><?= lang('Quo.showDetail') ?></span>
                        <span class="mt-0.5 block text-xs text-muted"><?= lang('Quo.showDetailHint') ?></span>
                    </span>
                </label>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group mb-0">
                        <label for="status" class="form-label"><?= lang('Quo.status') ?></label>
                        <select id="status" name="status" class="form-select">
                            <?php foreach ($meta['statuses'] as $k => $label): ?>
                                <option value="<?= $k ?>" <?= $v('status', 'pendiente') === $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rounded-md border border-line bg-surface p-3 text-sm">
                        <div class="flex justify-between"><span class="text-muted"><?= lang('Quo.subtotal') ?></span><span id="sumSubtotal">0</span></div>
                        <div class="flex justify-between"><span class="text-muted">− <?= lang('Quo.discount') ?></span><span id="sumDiscount">0</span></div>
                        <div class="flex justify-between"><span class="text-muted"><?= lang('Quo.taxableBase') ?></span><span id="sumBase">0</span></div>
                        <div class="flex justify-between"><span class="text-muted">+ <?= lang('Quo.taxes') ?></span><span id="sumTax">0</span></div>
                        <div class="flex justify-between"><span class="text-muted">− <?= lang('Quo.retention') ?></span><span id="sumRetention">0</span></div>
                        <div class="mt-1 flex justify-between border-t border-line pt-1 text-base font-semibold">
                            <span><?= lang('Quo.netTotal') ?></span><span id="sumTotal">0</span>
                        </div>
                    </div>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Quo.save') ?></button>
                <a href="<?= base_url('servicios/cotizaciones') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Quo.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= view('Components\Servicios\Views\_form_js', ['items' => $items]) ?>
<?= $this->endSection() ?>
