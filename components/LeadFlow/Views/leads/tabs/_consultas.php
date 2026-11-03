<?php

use Components\LeadFlow\Libraries\Meta;

$id     = (int) $lead['id'];
$volver = 'leadflow/leads/ficha/' . $id . '?tab=consultas';
?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <?php if (! $appointments): ?>
            <div class="card"><div class="card-body"><p class="text-sm text-muted"><?= lang('Lfl.noAppointments') ?></p></div></div>
        <?php endif; ?>
        <?php foreach ($appointments as $ap): ?>
            <?= view('Components\LeadFlow\Views\appointments\_card', ['ap' => $ap, 'team' => $team, 'canEdit' => $canEdit, 'canDelete' => $canDelete, 'volver' => $volver, 'showLead' => false]) ?>
        <?php endforeach; ?>
    </div>

    <div>
        <?php if ($canCreate): ?>
            <form id="nueva" action="<?= base_url('leadflow/consultas/nueva/' . $id) ?>" method="post" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><i class="icon icon-plus-circle"></i><h5 class="font-semibold"><?= lang('Lfl.apptNew') ?></h5></div>
                <div class="card-body">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="form-group">
                            <label for="ap-date" class="form-label"><?= lang('Lfl.date') ?> <span class="text-danger">*</span></label>
                            <input type="date" id="ap-date" name="date" class="form-input" required value="<?= date('Y-m-d', time() + 86400) ?>">
                        </div>
                        <div class="form-group">
                            <label for="ap-time" class="form-label"><?= lang('Lfl.time') ?> <span class="text-danger">*</span></label>
                            <input type="time" id="ap-time" name="time" class="form-input" required value="10:00">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="form-group">
                            <label for="ap-mode" class="form-label"><?= lang('Lfl.mode') ?></label>
                            <select id="ap-mode" name="mode" class="form-select">
                                <?php foreach (Meta::options(Meta::APPT_MODES) as $val => $lbl): ?>
                                    <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ap-duration" class="form-label"><?= lang('Lfl.durationMin') ?></label>
                            <input type="number" id="ap-duration" name="duration_min" min="5" max="480" step="5" class="form-input" value="30">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ap-lawyer" class="form-label"><?= lang('Lfl.lawyer') ?></label>
                        <select id="ap-lawyer" name="lawyer_user_id" class="form-select">
                            <option value=""><?= lang('Lfl.nobody') ?></option>
                            <?php foreach ($team as $uid => $uname): ?>
                                <option value="<?= (int) $uid ?>" <?= (int) $lead['lawyer_user_id'] === (int) $uid ? 'selected' : '' ?>><?= esc($uname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ap-location" class="form-label"><?= lang('Lfl.location') ?></label>
                        <input type="text" id="ap-location" name="location" maxlength="255" class="form-input" placeholder="<?= esc(lang('Lfl.locationPh'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="ap-notes" class="form-label"><?= lang('Lfl.notes') ?></label>
                        <textarea id="ap-notes" name="notes" rows="2" class="form-textarea"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lfl.schedule') ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
