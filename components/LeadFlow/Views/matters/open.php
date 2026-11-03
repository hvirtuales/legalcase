<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Models\LeadModel;

/*
 * Apertura del expediente.
 *
 * Estructura de `plans/_form.php`: secciones `.form-section` en orden decisión → datos.
 * Lo primero que se ve son los impedimentos, si hay: igual que la pantalla de
 * conversión a Docketwise, porque son la misma clase de decisión y conviene que se
 * lean igual.
 *
 * @var array    $lead
 * @var string[] $problems
 * @var array    $team
 * @var array    $services
 * @var array    $stages
 */
$puede = $problems === [];
?>
<div class="card">
    <div class="card-header">
        <div class="flex items-center gap-2">
            <i class="icon icon-folder"></i>
            <h5 class="font-semibold"><?= lang('Lfl.mtrOpenTitle') ?></h5>
        </div>
    </div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>

        <p class="mb-4 text-sm text-muted">
            <?= esc(lang('Lfl.mtrOpenHint')) ?>
        </p>

        <?php if (! $puede): ?>
            <div class="alert alert-warning">
                <i class="icon icon-exclamation-circle"></i>
                <span>
                    <?php foreach ($problems as $p): ?>
                        <span class="block"><?= esc($p) ?></span>
                    <?php endforeach; ?>
                </span>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('leadflow/expedientes/abrir/' . (int) $lead['id']) ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-section">
                <h6 class="form-section-title"><?= lang('Lfl.mtrLead') ?></h6>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted"><?= lang('Lfl.mtrClient') ?></dt>
                        <dd class="font-medium">
                            <a class="text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $lead['id']) ?>">
                                <?= esc(LeadModel::fullName($lead)) ?>
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted"><?= lang('Lfl.wonValue') ?></dt>
                        <dd class="font-medium"><?= esc($lead['won_value'] ?: '—') ?></dd>
                    </div>
                </dl>
            </div>

            <div class="form-section">
                <h6 class="form-section-title"><?= lang('Lfl.mtrTitleField') ?></h6>

                <div class="form-group">
                    <label for="service_id" class="form-label"><?= lang('Lfl.mtrService') ?></label>
                    <select id="service_id" name="service_id" class="form-select">
                        <option value=""><?= lang('Lfl.noService') ?></option>
                        <?php foreach ($services as $sid => $nombre): ?>
                            <option value="<?= (int) $sid ?>" <?= (int) ($lead['service_id'] ?? 0) === (int) $sid ? 'selected' : '' ?>>
                                <?= esc($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="lawyer_user_id" class="form-label"><?= lang('Lfl.mtrLawyer') ?></label>
                    <select id="lawyer_user_id" name="lawyer_user_id" class="form-select" required>
                        <option value=""><?= lang('Lfl.unassigned') ?></option>
                        <?php foreach ($team as $uid => $nombre): ?>
                            <option value="<?= (int) $uid ?>" <?= (int) ($lead['lawyer_user_id'] ?? 0) === (int) $uid ? 'selected' : '' ?>>
                                <?= esc($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint"><?= lang('Lfl.mtrNeedLawyer') ?></p>
                </div>

                <div class="form-group">
                    <label for="assigned_user_id" class="form-label"><?= lang('Lfl.mtrAssigned') ?></label>
                    <select id="assigned_user_id" name="assigned_user_id" class="form-select">
                        <option value=""><?= lang('Lfl.unassigned') ?></option>
                        <?php foreach ($team as $uid => $nombre): ?>
                            <option value="<?= (int) $uid ?>" <?= (int) ($lead['assigned_user_id'] ?? 0) === (int) $uid ? 'selected' : '' ?>>
                                <?= esc($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title" class="form-label"><?= lang('Lfl.mtrTitleField') ?></label>
                    <input type="text" id="title" name="title" class="form-input" maxlength="160"
                        placeholder="<?= esc(lang('Lfl.mtrService') . ' — ' . LeadModel::fullName($lead), 'attr') ?>">
                </div>

                <div class="form-group">
                    <label for="summary" class="form-label"><?= lang('Lfl.mtrSummary') ?></label>
                    <textarea id="summary" name="summary" class="form-input" rows="4"><?= esc($lead['summary'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" <?= $puede ? '' : 'disabled' ?>>
                    <i class="icon icon-folder"></i> <?= lang('Lfl.mtrOpen') ?>
                </button>
                <a href="<?= base_url('leadflow/leads/ficha/' . (int) $lead['id']) ?>" class="btn btn-secondary">
                    <?= lang('Lfl.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
