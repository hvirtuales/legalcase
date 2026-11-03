<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d); ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-people"></i>
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

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.clients') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group md:col-span-2">
                        <label for="name" class="form-label"><?= lang('Quo.businessName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="200" value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="nit" class="form-label"><?= lang('Quo.nit') ?></label>
                        <input type="text" id="nit" name="nit" class="form-input" maxlength="50" value="<?= esc($v('nit'), 'attr') ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="city" class="form-label"><?= lang('Quo.city') ?></label>
                        <input type="text" id="city" name="city" class="form-input" maxlength="100" value="<?= esc($v('city'), 'attr') ?>">
                    </div>
                    <div class="form-group md:col-span-2">
                        <label for="address" class="form-label"><?= lang('Quo.address') ?></label>
                        <input type="text" id="address" name="address" class="form-input" maxlength="255" value="<?= esc($v('address'), 'attr') ?>">
                    </div>
                </div>

                <!-- Qué valor del producto se le aplica: el cotizador toma de ese
                     escalón el precio, la cantidad, el descuento y el impuesto. -->
                <div class="form-group mb-2 md:w-1/3">
                    <label for="price_tier" class="form-label"><?= lang('Quo.priceTier') ?></label>
                    <select id="price_tier" name="price_tier" class="form-select">
                        <option value=""><?= lang('Quo.tierBase') ?></option>
                        <?php foreach (\Components\Servicios\Models\ProductPriceModel::TIERS as $t): ?>
                            <option value="<?= $t ?>" <?= (int) $v('price_tier') === $t ? 'selected' : '' ?>>
                                <?= lang('Quo.tierN', [$t]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint"><?= lang('Quo.priceTierClientHint') ?></small>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.contactName') ?></legend>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="contact_name" class="form-label"><?= lang('Quo.contactName') ?></label>
                        <input type="text" id="contact_name" name="contact_name" class="form-input" maxlength="150" value="<?= esc($v('contact_name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label"><?= lang('Quo.phone') ?></label>
                        <input type="text" id="phone" name="phone" class="form-input" maxlength="50" value="<?= esc($v('phone'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label"><?= lang('Quo.email') ?></label>
                        <input type="email" id="email" name="email" class="form-input" maxlength="190" value="<?= esc($v('email'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email2" class="form-label"><?= lang('Quo.email2') ?></label>
                        <input type="email" id="email2" name="email2" class="form-input" maxlength="190" value="<?= esc($v('email2'), 'attr') ?>">
                    </div>
                </div>

                <div class="form-group mb-2">
                    <label for="notes" class="form-label"><?= lang('Quo.notes') ?></label>
                    <textarea id="notes" name="notes" class="form-textarea" rows="2"><?= esc($v('notes')) ?></textarea>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Quo.save') ?></button>
                <a href="<?= base_url('servicios/clientes') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Quo.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
