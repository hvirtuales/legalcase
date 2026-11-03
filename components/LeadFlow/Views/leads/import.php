<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-upload"></i>
            <h5 class="font-semibold"><?= lang('Lfl.importTitle') ?></h5>
        </div>
        <a href="<?= base_url('leadflow/leads/plantilla') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-download"></i> <?= lang('Lfl.importTemplate') ?></a>
    </div>
    <div class="card-body">
        <?php if ($planBlock): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= esc($planBlock) ?></span></div>
        <?php endif; ?>

        <?php if (is_array($result) && ! empty($result['ok'])): ?>
            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i>
                <div class="flex-1">
                    <p><?= esc($result['message']) ?></p>
                    <?php if (! empty($result['errors'])): ?>
                        <ul class="mt-2 list-inside list-disc text-xs">
                            <?php foreach (array_slice($result['errors'], 0, 50, true) as $line => $msg): ?>
                                <li><?= esc(lang('Lfl.importLine', [$line])) ?>: <?= esc($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.importIntro') ?></p>

        <form action="<?= base_url('leadflow/leads/importar') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.importFile') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.importFileHint') ?></p>
                <div class="form-group">
                    <label for="csv" class="form-label"><?= lang('Lfl.importFile') ?> <span class="text-danger">*</span></label>
                    <input type="file" id="csv" name="csv" accept=".csv,text/csv" class="form-input" required>
                </div>
                <label class="form-check mb-2">
                    <input type="checkbox" name="skip_duplicates" value="1" class="form-check-input" checked>
                    <span class="form-check-label"><?= lang('Lfl.importSkipDup') ?></span>
                </label>
                <p class="form-hint"><?= lang('Lfl.importSkipDupHint') ?></p>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.importDefaults') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.importDefaultsHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <?php foreach ([['service_id', 'service', $services], ['source_id', 'source', $sources], ['campaign_id', 'campaign', $campaigns]] as [$field, $label, $opts]): ?>
                        <div class="form-group">
                            <label for="<?= esc($field, 'attr') ?>" class="form-label"><?= lang('Lfl.' . $label) ?></label>
                            <select id="<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-select">
                                <option value=""><?= lang('Lfl.none') ?></option>
                                <?php foreach ($opts as $id => $name): ?>
                                    <option value="<?= (int) $id ?>"><?= esc($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($access->canAssignOthers()): ?>
                        <div class="form-group">
                            <label for="assigned_user_id" class="form-label"><?= lang('Lfl.advisor') ?></label>
                            <select id="assigned_user_id" name="assigned_user_id" class="form-select">
                                <option value=""><?= lang('Lfl.nobody') ?></option>
                                <?php foreach ($team as $uid => $uname): ?>
                                    <option value="<?= (int) $uid ?>"><?= esc($uname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary" <?= $planBlock ? 'disabled' : '' ?>><i class="icon icon-upload"></i> <?= lang('Lfl.import') ?></button>
                <a href="<?= base_url('leadflow/leads') ?>" class="btn btn-secondary"><i class="icon icon-arrow-left"></i> <?= lang('Lfl.back') ?></a>
            </div>
        </form>

        <p class="mt-4 text-xs text-muted"><?= esc(lang('Lfl.importColumns', [implode(';', \Components\LeadFlow\Libraries\ImportService::COLUMNS)])) ?></p>
    </div>
</div>
<?= $this->endSection() ?>
