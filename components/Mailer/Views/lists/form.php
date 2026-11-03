<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="mx-auto max-w-xl">
    <div class="mb-4 flex items-center gap-2">
        <a href="<?= base_url('mailer/listas') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Mailer.back') ?></a>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (session('errors')): ?>
                <div class="alert alert-danger"><i class="icon icon-exclamation-circle"></i>
                    <ul class="mb-0"><?php foreach ((array) session('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="<?= $list ? base_url('mailer/listas/editar/' . $list['id']) : base_url('mailer/listas/crear') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="name"><?= lang('Mailer.listName') ?></label>
                    <input type="text" name="name" id="name" class="form-input" required value="<?= esc($list['name'] ?? old('name'), 'attr') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="description"><?= lang('Mailer.listDesc') ?></label>
                    <input type="text" name="description" id="description" class="form-input" value="<?= esc($list['description'] ?? old('description'), 'attr') ?>">
                </div>
                <?php if ($list): ?>
                    <!-- El alta pública solo tiene sentido sobre una lista que ya
                         existe: el enlace lleva su token, y al crearla todavía no
                         hay ninguno. -->
                    <fieldset class="form-section">
                        <legend class="font-semibold"><?= lang('Mailer.publicSignup') ?></legend>

                        <label class="flex items-start gap-2 mb-3">
                            <input type="checkbox" name="public_enabled" value="1" class="mt-1"
                                <?= !empty($list['public_enabled']) ? 'checked' : '' ?>>
                            <span>
                                <span class="font-medium"><?= lang('Mailer.publicEnabled') ?></span>
                                <span class="block text-xs text-muted"><?= lang('Mailer.publicHint') ?></span>
                            </span>
                        </label>

                        <div class="form-group">
                            <label class="form-label" for="consent_text"><?= lang('Mailer.consentText') ?></label>
                            <input type="text" name="consent_text" id="consent_text" class="form-input"
                                   value="<?= esc($list['consent_text'] ?? '', 'attr') ?>"
                                   placeholder="<?= esc(lang('Mailer.signupDefaultConsent'), 'attr') ?>">
                            <p class="mt-1 text-xs text-muted"><?= lang('Mailer.consentTextHint') ?></p>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label"><?= lang('Mailer.publicLink') ?></label>
                            <?php if ($link !== '' && !empty($list['public_enabled'])): ?>
                                <div class="flex gap-2">
                                    <input type="text" class="form-input" id="signupLink" readonly value="<?= esc($link, 'attr') ?>">
                                    <button type="button" class="btn btn-secondary" id="copySignup">
                                        <i class="icon icon-clipboard"></i> <?= lang('Mailer.publicCopy') ?>
                                    </button>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-muted"><?= lang('Mailer.publicOff') ?></p>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <div class="flex justify-end gap-2">
                    <a href="<?= base_url('mailer/listas') ?>" class="btn btn-secondary"><?= lang('Mailer.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-check"></i> <?= lang('Mailer.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        var btn = document.getElementById('copySignup');
        var box = document.getElementById('signupLink');
        if (!btn || !box) { return; }
        btn.addEventListener('click', function () {
            box.select();
            var hecho = false;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(box.value).then(function () {}, function () {});
                hecho = true;
            }
            // Respaldo para http://una-ip, donde `clipboard` no existe.
            if (!hecho) { try { document.execCommand('copy'); } catch (e) {} }
            btn.textContent = <?= json_encode(lang('Mailer.publicCopied'), JSON_UNESCAPED_UNICODE) ?>;
        });
    })();
</script>
<?= $this->endSection() ?>