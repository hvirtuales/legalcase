<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Compartir: el enlace público, su QR y el fragmento para incrustarlo.
 *
 * Lo que se incrusta es un `iframe` al formulario vivo y no una copia de su
 * HTML: así lo publicado sigue teniendo su validación, su cupo y su lógica, en
 * vez de ser una fotografía que deja de parecerse al original en cuanto se
 * edita. (La app de la que nace el componente exportaba HTML suelto, y además
 * sin escapar.)
 *
 * @var array  $form
 * @var string $url
 * @var string $embed
 */
?>
<div class="mb-4">
    <a href="<?= base_url('formularios') ?>" class="btn btn-secondary btn-sm">
        <i class="icon icon-arrow-left"></i> <?= lang('Frm.forms') ?>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <div class="flex items-center gap-2">
            <i class="icon icon-share"></i>
            <h5 class="font-semibold"><?= lang('Frm.shareTitle') ?></h5>
        </div>
    </div>

    <div class="card-body space-y-6">
        <?php if ((string) $form['status'] !== 'published'): ?>
            <div class="alert alert-warning">
                <i class="icon icon-exclamation-circle"></i><span><?= lang('Frm.shareDraft') ?></span>
            </div>
        <?php endif; ?>

        <div>
            <label class="form-label"><?= lang('Frm.shareLink') ?></label>
            <div class="flex gap-2">
                <input class="form-input font-mono" readonly value="<?= esc($url, 'attr') ?>" data-frm-copy-src>
                <button type="button" class="btn btn-secondary" data-frm-copy="<?= esc($url, 'attr') ?>">
                    <i class="icon icon-arrow-repeat"></i> <?= lang('Frm.copy') ?>
                </button>
                <a class="btn btn-primary" href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener">
                    <i class="icon icon-eye"></i> <?= lang('Frm.open') ?>
                </a>
            </div>
        </div>

        <?php if ($qr): ?>
            <div>
                <label class="form-label"><?= lang('Frm.shareQr') ?></label>
                <img src="<?= esc($qr, 'attr') ?>" alt="" width="180" height="180"
                     class="rounded-xl border border-line bg-white p-2">
            </div>
        <?php endif; ?>

        <div>
            <label class="form-label"><?= lang('Frm.shareEmbed') ?></label>
            <p class="mb-2 text-xs text-muted"><?= lang('Frm.shareEmbedHint') ?></p>
            <textarea class="form-input font-mono text-xs" rows="3" readonly><?= esc($embed) ?></textarea>
            <button type="button" class="btn btn-secondary btn-sm mt-2" data-frm-copy="<?= esc($embed, 'attr') ?>">
                <i class="icon icon-arrow-repeat"></i> <?= lang('Frm.copy') ?>
            </button>
        </div>
    </div>
</div>

<script>
/*
 * `navigator.clipboard` no existe fuera de un origen seguro, y este panel se usa
 * por IP en la red local: de ahí el respaldo con `execCommand`.
 */
document.querySelectorAll('[data-frm-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var text = btn.getAttribute('data-frm-copy');
    var done = function () {
      var old = btn.innerHTML;
      btn.innerHTML = '<i class="icon icon-check-circle"></i> <?= esc(lang('Frm.copied'), 'js') ?>';
      setTimeout(function () { btn.innerHTML = old; }, 1800);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done);
    } else {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (e) { /* nada que hacer */ }
      document.body.removeChild(ta);
    }
  });
});
</script>
<?= $this->endSection() ?>
