<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="mx-auto max-w-3xl">
    <div class="mb-4 flex items-center gap-2">
        <a href="<?= base_url('mailer/listas/' . $list['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Mailer.back') ?></a>
        <h5 class="font-semibold"><?= lang('Mailer.reviewTitle', [esc($list['name'])]) ?></h5>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="icon icon-exclamation-circle"></i>
                <div>
                    <p><?= lang('Mailer.reviewHelp') ?></p>
                </div>
            </div>

            <form method="post" action="<?= base_url('mailer/listas/' . $list['id'] . '/contactos/revisar') ?>">
                <?= csrf_field() ?>
                <div class="overflow-x-auto">
                    <table class="table text-sm">
                        <thead>
                            <tr>
                                <th style="min-width:220px"><?= lang('Mailer.email') ?></th>
                                <th style="min-width:150px"><?= lang('Mailer.name') ?></th>
                                <th style="min-width:150px"><?= lang('Mailer.entity') ?></th>
                                <th><?= lang('Mailer.reason') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><input type="email" name="email[]" class="form-input" value="<?= esc($r['email'] ?? '', 'attr') ?>" placeholder="<?= esc(lang('Mailer.mailPh'), 'attr') ?>"></td>
                                    <td><input type="text" name="name[]" class="form-input" value="<?= esc($r['name'] ?? '', 'attr') ?>"></td>
                                    <td><input type="text" name="entity[]" class="form-input" value="<?= esc($r['entity'] ?? '', 'attr') ?>"></td>
                                    <td><span class="badge badge-danger"><?= esc($r['reason'] ?? lang('Mailer.invalid')) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <a href="<?= base_url('mailer/listas/' . $list['id']) ?>" class="btn btn-secondary"><?= lang('Mailer.discardAll') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-check"></i> <?= lang('Mailer.importFixed') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
