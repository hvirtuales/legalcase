<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array|null $cfg */
$v = static fn (string $k, $d = '') => old($k, $cfg[$k] ?? $d);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-gear"></i>
        <h5 class="font-semibold"><?= lang('Quo.settings') ?></h5>
    </div>
    <div class="card-body">
        <?php if ($cfg === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.errNoCompany') ?></span></div>
        <?php else: ?>
            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger"><ul class="list-inside list-disc">
                    <?php foreach ((array) session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>

            <form action="<?= base_url('servicios/configuracion') ?>" method="post">
                <?= csrf_field() ?>

                <!-- 1. Numeración -->
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Quo.secNumbering') ?></legend>
                    <p class="form-section-hint"><?= lang('Quo.secNumberingHint') ?></p>

                    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                        <div class="form-group">
                            <label for="doc_type" class="form-label"><?= lang('Quo.docType') ?> <span class="text-danger">*</span></label>
                            <select id="doc_type" name="doc_type" class="form-select">
                                <?php foreach (\Components\Servicios\Models\QuoteModel::DOC_TYPES as $t): ?>
                                    <option value="<?= esc($t, 'attr') ?>" <?= $v('doc_type') === $t ? 'selected' : '' ?>><?= esc($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="prefix" class="form-label"><?= lang('Quo.prefix') ?> <span class="text-danger">*</span></label>
                            <input type="text" id="prefix" name="prefix" class="form-input" maxlength="10" required
                                value="<?= esc($v('prefix', 'COT'), 'attr') ?>">
                            <small class="form-hint"><?= lang('Quo.prefixHint') ?></small>
                        </div>
                        <div class="form-group">
                            <label for="initial_number" class="form-label"><?= lang('Quo.initialNumber') ?></label>
                            <input type="number" min="1" id="initial_number" name="initial_number" class="form-input"
                                value="<?= esc($v('initial_number', 1), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= lang('Quo.nextNumber') ?></label>
                            <input type="text" class="form-input bg-surface-muted" value="<?= esc($preview ?? '', 'attr') ?>" readonly>
                            <small class="form-hint"><?= lang('Quo.nextNumberHint') ?></small>
                        </div>
                    </div>
                </fieldset>

                <!-- 2. Valores por defecto -->
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Quo.secDefaults') ?></legend>
                    <p class="form-section-hint"><?= lang('Quo.secDefaultsHint') ?></p>

                    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                        <div class="form-group">
                            <label for="default_currency" class="form-label"><?= lang('Quo.currency') ?></label>
                            <select id="default_currency" name="default_currency" class="form-select">
                                <?php foreach (\Components\Servicios\Models\QuoteModel::CURRENCIES as $c): ?>
                                    <option value="<?= $c ?>" <?= $v('default_currency', 'COP') === $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="default_validity_days" class="form-label"><?= lang('Quo.validityDays') ?></label>
                            <input type="number" min="0" id="default_validity_days" name="default_validity_days" class="form-input"
                                value="<?= esc($v('default_validity_days', 15), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label for="default_retention" class="form-label"><?= lang('Quo.retention') ?></label>
                            <input type="number" step="0.01" min="0" id="default_retention" name="default_retention" class="form-input"
                                value="<?= esc($v('default_retention', 0), 'attr') ?>">
                            <small class="form-hint"><?= lang('Quo.retentionHint') ?></small>
                        </div>
                    </div>

                    <label class="form-check mb-3" for="show_detail_default">
                        <input type="checkbox" class="form-check-input" id="show_detail_default" name="show_detail_default" value="1"
                            <?= $v('show_detail_default', 1) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= lang('Quo.showDetail') ?></span>
                    </label>

                    <div class="form-group mb-2">
                        <label class="form-label"><?= lang('Quo.defaultTerms') ?></label>
                        <?= view('Components\Servicios\Views\_editor', [
                            'name'        => 'terms_html',
                            'value'       => (string) $v('terms_html'),
                            'placeholder' => lang('Quo.defaultTerms'),
                        ]) ?>
                    </div>
                </fieldset>

                <!-- 3. Carta de presentación -->
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Quo.secCover') ?></legend>
                    <p class="form-section-hint"><?= lang('Quo.secCoverHint') ?></p>

                    <label class="form-check mb-3" for="cover_enabled">
                        <input type="checkbox" class="form-check-input" id="cover_enabled" name="cover_enabled" value="1"
                            <?= $v('cover_enabled') ? 'checked' : '' ?>>
                        <span class="form-check-label font-semibold"><?= lang('Quo.coverEnabled') ?></span>
                    </label>

                    <div class="form-group">
                        <label for="cover_title" class="form-label"><?= lang('Quo.coverTitle') ?></label>
                        <input type="text" id="cover_title" name="cover_title" class="form-input" maxlength="150"
                            value="<?= esc($v('cover_title'), 'attr') ?>">
                    </div>

                    <div class="form-group mb-2">
                        <label class="form-label"><?= lang('Quo.coverBody') ?></label>
                        <?= view('Components\Servicios\Views\_editor', [
                            'name'        => 'cover_html',
                            'value'       => (string) $v('cover_html'),
                            'placeholder' => lang('Quo.coverBody'),
                        ]) ?>
                    </div>
                </fieldset>

                <p class="mb-4 text-xs text-muted">
                    <i class="icon icon-info-circle"></i> <?= lang('Quo.brandNotice') ?>
                    <a href="<?= base_url('mi-empresa') ?>" class="text-primary"><?= lang('Quo.brandLink') ?></a>.
                </p>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Quo.save') ?></button>
                    <a href="<?= base_url('servicios/cotizaciones') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Quo.cancel') ?></a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// El editor de esta pantalla usa el mismo comportamiento que el del cotizador.
(function () {
    document.querySelectorAll('[data-editor]').forEach(function (box) {
        var area = box.querySelector('[contenteditable]');
        var hidden = box.querySelector('input[type=hidden]');
        if (!area || !hidden) { return; }
        box.querySelectorAll('[data-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cmd = btn.dataset.cmd;
                area.focus();
                if (cmd === 'createLink') {
                    var url = window.prompt(<?= json_encode(lang('Quo.edLinkAsk')) ?>, 'https://');
                    if (!url) { return; }
                    document.execCommand('createLink', false, url);
                } else {
                    document.execCommand(cmd, false, null);
                }
                hidden.value = area.innerHTML;
            });
        });
        area.addEventListener('input', function () { hidden.value = area.innerHTML; });
        area.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });
    });
})();
</script>
<?= $this->endSection() ?>
