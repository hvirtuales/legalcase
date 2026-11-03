<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-center gap-2">
        <a href="<?= base_url('mailer/listas/' . $list['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Mailer.back') ?></a>
        <h5 class="font-semibold"><?= lang('Mailer.importTitle', [esc($list['name'])]) ?></h5>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="alert alert-info"><i class="icon icon-info-circle"></i>
                <div>
                    <p><?= lang('Mailer.importDelim') ?></p>
                    <p class="mt-1 font-mono text-xs">email;nombre;entidad</p>
                    <p class="mt-1 text-xs text-muted"><?= lang('Mailer.importOnlyMail') ?></p>
                    <p class="mt-1 text-xs text-muted"><?= lang('Mailer.importRules') ?></p>
                    <p class="mt-2"><a href="<?= base_url('mailer/contactos/plantilla') ?>" class="text-primary"><i class="icon icon-download"></i> <?= lang('Mailer.downloadTpl') ?></a></p>
                </div>
            </div>
            <form action="<?= base_url('mailer/listas/' . $list['id'] . '/contactos/importar') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="csv"><?= lang('Mailer.csvFile') ?></label>
                    <input type="file" name="csv" id="csv" class="form-input" accept=".csv,text/csv" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="consent_note"><?= lang('Mailer.consentOrigin') ?></label>
                    <input type="text" name="consent_note" id="consent_note" class="form-input"
                           placeholder="<?= esc(lang('Mailer.consentOriginPh'), 'attr') ?>">
                    <p class="mt-1 text-xs text-muted"><?= lang('Mailer.consentHint') ?></p>
                </div>
                <div class="flex justify-end gap-2">
                    <a href="<?= base_url('mailer/listas/' . $list['id']) ?>" class="btn btn-secondary"><?= lang('Mailer.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-upload"></i> <?= lang('Mailer.import') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
