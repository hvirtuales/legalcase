<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\ScoringService;

?>
<div class="card">
    <div class="card-header"><i class="icon icon-gear"></i><h5 class="font-semibold"><?= lang('Lfl.navSettings') ?></h5></div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_settings_tabs', ['active' => 'scoring']) ?>
        <p class="mb-2 text-sm text-muted"><?= lang('Lfl.scoringIntro') ?></p>
        <div class="mb-4 flex flex-wrap gap-2 text-xs">
            <span class="badge badge-secondary"><?= lang('Lfl.bandLow') ?></span>
            <span class="badge badge-warning"><?= lang('Lfl.bandMedium') ?></span>
            <span class="badge badge-danger"><?= lang('Lfl.bandHigh') ?></span>
        </div>

        <form action="<?= base_url('leadflow/configuracion/scoring') ?>" method="post">
            <?= csrf_field() ?>
            <div class="table-card mb-4 overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.criterion') ?></th>
                            <th><?= lang('Lfl.points') ?></th>
                            <th><?= lang('Lfl.active') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (ScoringService::CRITERIA_LABELS as $c => $key): ?>
                            <?php $r = $rules[$c] ?? ['points' => ScoringService::CRITERIA[$c], 'is_active' => 1]; ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?= lang('Lfl.' . $key) ?></div>
                                    <div class="text-xs text-muted"><?= lang('Lfl.' . $key . 'Hint') ?></div>
                                </td>
                                <td><input type="number" min="-100" max="100" name="points[<?= esc($c, 'attr') ?>]" value="<?= (int) $r['points'] ?>" class="form-input w-24"
                                    aria-label="<?= esc(lang('Lfl.points'), 'attr') ?>"></td>
                                <td><input type="checkbox" name="active[<?= esc($c, 'attr') ?>]" value="1" class="form-check-input" <?= (int) $r['is_active'] === 1 ? 'checked' : '' ?>
                                    aria-label="<?= esc(lang('Lfl.active'), 'attr') ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="form-hint mb-3"><?= lang('Lfl.scoringRecalcHint') ?></p>
            <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
