<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d); ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-person-badge"></i>
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

            <!-- 1. Quién es -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.sellers') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.sellerIdentityHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="name" class="form-label"><?= lang('Quo.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required maxlength="150" value="<?= esc($v('name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="position" class="form-label"><?= lang('Quo.sellerPosition') ?></label>
                        <input type="text" id="position" name="position" class="form-input" maxlength="120"
                            value="<?= esc($v('position'), 'attr') ?>" placeholder="<?= esc(lang('Quo.sellerPositionPlaceholder'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Quo.sellerPositionHint') ?></small>
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label"><?= lang('Quo.email') ?></label>
                        <input type="email" id="email" name="email" class="form-input" maxlength="190" value="<?= esc($v('email'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label"><?= lang('Quo.phone') ?></label>
                        <input type="text" id="phone" name="phone" class="form-input" maxlength="50" value="<?= esc($v('phone'), 'attr') ?>">
                    </div>
                </div>

                <div class="form-group mb-2 md:w-1/3">
                    <label for="status" class="form-label"><?= lang('Quo.sellerStatus') ?></label>
                    <select id="status" name="status" class="form-select">
                        <option value="activo" <?= $v('status', 'activo') === 'activo' ? 'selected' : '' ?>><?= lang('Quo.active') ?></option>
                        <option value="inactivo" <?= $v('status') === 'inactivo' ? 'selected' : '' ?>><?= lang('Quo.inactive') ?></option>
                    </select>
                </div>
            </fieldset>

            <!-- 2. Cómo aparece en los documentos -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Quo.sellerDocSection') ?></legend>
                <p class="form-section-hint"><?= lang('Quo.sellerDocSectionHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="photo" class="form-label"><?= lang('Quo.sellerPhoto') ?></label>
                        <?php if (!empty($row['photo'])): ?>
                            <div class="mb-2">
                                <img src="<?= media_url($row['photo']) ?>" alt=""
                                    class="size-16 rounded-full border border-line object-cover">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="photo" name="photo" class="form-input" data-preview
                            accept="<?= esc(\App\Libraries\UploadService::IMAGE_TYPES, 'attr') ?>">
                        <small class="form-hint"><?= lang('Quo.sellerPhotoHint') ?></small>
                    </div>

                    <div class="form-group">
                        <label for="signature" class="form-label"><?= lang('Quo.sellerSignature') ?></label>
                        <?php if (!empty($row['signature'])): ?>
                            <div class="mb-2">
                                <img src="<?= media_url($row['signature']) ?>" alt=""
                                    class="max-h-16 rounded border border-line bg-canvas p-1">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="signature" name="signature" class="form-input" data-preview
                            accept="<?= esc(\App\Libraries\UploadService::IMAGE_TYPES, 'attr') ?>">
                        <small class="form-hint"><?= lang('Quo.sellerSignatureHint') ?></small>
                    </div>
                </div>

                <div class="form-group mb-2">
                    <label for="card_url" class="form-label"><?= lang('Quo.cardUrl') ?></label>
                    <input type="url" id="card_url" name="card_url" class="form-input" maxlength="255"
                        value="<?= esc($v('card_url'), 'attr') ?>" placeholder="https://…">
                    <small class="form-hint"><?= lang('Quo.cardUrlHint') ?></small>
                </div>
            </fieldset>

            <?php if (!empty($row['id'])): ?>
                <!-- 3. Su tarjeta digital en la plataforma (existe siempre; el campo
                     de arriba es para quien ya tenga una tarjeta externa). -->
                <?php $cardUrl = (new \Components\Servicios\Libraries\SellerCardService())->publicUrl($row); ?>
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Quo.cardPublic') ?></legend>
                    <p class="form-section-hint"><?= lang('Quo.cardPublicHint') ?></p>

                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" class="form-input flex-1 min-w-0" id="seller-card-url" readonly
                            value="<?= esc($cardUrl, 'attr') ?>">
                        <button type="button" class="btn btn-secondary" id="seller-card-copy">
                            <i class="icon icon-link"></i> <?= lang('Quo.cardCopyLink') ?>
                        </button>
                        <a href="<?= esc($cardUrl, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-secondary">
                            <i class="icon icon-box-arrow-up-right"></i> <?= lang('Quo.cardOpen') ?>
                        </a>
                    </div>
                </fieldset>

                <script>
                (function () {
                    var btn = document.getElementById('seller-card-copy');
                    var box = document.getElementById('seller-card-url');
                    btn.addEventListener('click', function () {
                        box.select();
                        var done = function () {
                            var html = btn.innerHTML;
                            btn.innerHTML = '<i class="icon icon-check-circle"></i> <?= esc(lang('Quo.cardCopied')) ?>';
                            setTimeout(function () { btn.innerHTML = html; }, 2000);
                        };
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(box.value).then(done);
                        } else {
                            try { document.execCommand('copy'); done(); } catch (e) {}
                        }
                    });
                }());
                </script>
            <?php endif; ?>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Quo.save') ?></button>
                <a href="<?= base_url('servicios/vendedores') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Quo.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
