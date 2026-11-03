<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\LeadModel;

/*
 * «Convertir a cliente» (§22). Se decide, en este orden: con qué CONTACTO de
 * Docketwise (uno nuevo o uno que ya existe), el abogado del Matter, y qué
 * información viaja. El formulario de sincronizar contactos va FUERA del de la
 * conversión: anidar formularios es inválido.
 */
$id       = (int) $lead['id'];
$lawyerId = (int) ($lead['lawyer_user_id'] ?? 0);
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <div>
        <h4 class="text-xl font-semibold"><?= esc(lang('Lfl.convertTitle', [LeadModel::fullName($lead)])) ?></h4>
        <p class="text-sm text-muted"><?= lang('Lfl.convertSubtitle') ?></p>
    </div>
    <a href="<?= base_url('leadflow/leads/ficha/' . $id . '?tab=docketwise') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Lfl.back') ?></a>
</div>

<?php if ($integration['mode'] === 'simulated'): ?>
    <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Lfl.dwSimulatedNotice') ?></span></div>
<?php endif; ?>

<?php if ($problems): ?>
    <div class="alert alert-warning">
        <i class="icon icon-exclamation-circle"></i>
        <ul class="list-inside list-disc">
            <?php foreach ($problems as $p): ?><li><?= esc($p) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form action="<?= base_url('leadflow/convertir/' . $id) ?>" method="post" onsubmit="return confirm('<?= esc(lang('Lfl.convertConfirm'), 'js') ?>')">
                <?= csrf_field() ?>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lfl.cvStepContact') ?></legend>
                    <p class="form-section-hint"><?= lang('Lfl.cvStepContactHint') ?></p>
                    <label class="form-check mb-2">
                        <input type="radio" name="contact_mode" value="new" class="form-check-input" <?= $candidates ? '' : 'checked' ?>>
                        <span class="form-check-label"><strong><?= lang('Lfl.cvNewContact') ?></strong> — <?= esc(LeadModel::fullName($lead)) ?><?= $lead['email'] ? ' · ' . esc($lead['email']) : '' ?></span>
                    </label>
                    <?php if ($candidates): ?>
                        <p class="mb-2 mt-3 text-sm font-semibold text-warning"><i class="icon icon-exclamation-circle"></i> <?= lang('Lfl.cvCandidates') ?></p>
                        <?php foreach ($candidates as $i => $c): ?>
                            <label class="form-check mb-1">
                                <input type="radio" name="contact_mode" value="existing" class="form-check-input" data-candidate="<?= esc($c['contact']['external_id'], 'attr') ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                <span class="form-check-label">
                                    <?= lang('Lfl.cvLinkTo') ?> <strong>#<?= esc($c['contact']['external_id']) ?></strong>
                                    <?= esc(trim(($c['contact']['first_name'] ?? '') . ' ' . ($c['contact']['last_name'] ?? ''))) ?>
                                    <?= $c['contact']['email_norm'] ? ' · ' . esc($c['contact']['email_norm']) : '' ?>
                                    <span class="text-xs text-muted">(<?= esc(implode(', ', array_map(static fn ($r) => lang('Lfl.dupBy_' . $r), $c['reasons']))) ?>)</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <input type="hidden" name="existing_contact_id" value="<?= esc($candidates[0]['contact']['external_id'], 'attr') ?>" data-existing>
                    <?php endif; ?>
                    <p class="form-hint mt-2"><?= esc(lang('Lfl.cvCacheAge', [Meta::date($integration['contacts_synced_at'])])) ?></p>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lfl.cvStepMatter') ?></legend>
                    <p class="form-section-hint"><?= lang('Lfl.cvStepMatterHint') ?></p>
                    <dl class="mb-3 space-y-1 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-muted"><?= lang('Lfl.service') ?></dt><dd><?= esc($service['name'] ?? '—') ?></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-muted"><?= lang('Lfl.matterType') ?></dt>
                            <dd><?= esc($service['matter_type_name'] ?? lang('Lfl.cvNoMatterType')) ?></dd></div>
                    </dl>
                    <div class="form-group">
                        <label for="lawyer_user_id" class="form-label"><?= lang('Lfl.lawyer') ?></label>
                        <select id="lawyer_user_id" name="lawyer_user_id" class="form-select">
                            <option value=""><?= lang('Lfl.nobody') ?></option>
                            <?php foreach ($team as $uid => $uname): ?>
                                <?php $dw = $members[$uid]['docketwise_user_id'] ?? null; ?>
                                <option value="<?= (int) $uid ?>" <?= $lawyerId === (int) $uid ? 'selected' : '' ?>>
                                    <?= esc($uname) ?><?= $dw ? ' → Docketwise #' . esc($dw) : ' · ' . esc(lang('Lfl.cvNoDwUser')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint"><?= lang('Lfl.cvLawyerHint') ?></p>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lfl.cvStepTransfer') ?></legend>
                    <p class="form-section-hint"><?= lang('Lfl.cvStepTransferHint') ?></p>
                    <label class="form-check mb-1">
                        <input type="checkbox" name="transfer_info" value="1" class="form-check-input" checked>
                        <span class="form-check-label"><?= lang('Lfl.cvTransferInfo') ?></span>
                    </label>
                    <label class="form-check mb-3">
                        <input type="checkbox" name="include_summary" value="1" class="form-check-input" checked>
                        <span class="form-check-label"><?= lang('Lfl.cvIncludeSummary') ?></span>
                    </label>

                    <h6 class="mb-1 text-sm font-semibold"><?= lang('Lfl.notes') ?></h6>
                    <?php if (! $notes): ?>
                        <p class="mb-3 text-xs text-muted"><?= lang('Lfl.cvNoNotes') ?></p>
                    <?php else: ?>
                        <?php foreach ($notes as $n): ?>
                            <label class="form-check mb-1">
                                <input type="checkbox" name="notes[]" value="<?= (int) $n['id'] ?>" class="form-check-input" checked>
                                <span class="form-check-label text-sm"><?= esc(mb_strimwidth($n['body'], 0, 120, '…')) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <h6 class="mb-1 mt-3 text-sm font-semibold"><?= lang('Lfl.tabDocuments') ?></h6>
                    <?php if (! $documents): ?>
                        <p class="text-xs text-muted"><?= lang('Lfl.noDocuments') ?></p>
                    <?php else: ?>
                        <?php foreach ($documents as $d): ?>
                            <label class="form-check mb-1 <?= $d['is_authorized'] ? '' : 'opacity-60' ?>">
                                <input type="checkbox" name="documents[]" value="<?= (int) $d['id'] ?>" class="form-check-input"
                                    <?= $d['is_authorized'] ? 'checked' : 'disabled' ?>>
                                <span class="form-check-label text-sm"><?= esc($d['original_name']) ?>
                                    <?= $d['is_authorized'] ? '' : '<span class="text-xs text-muted">— ' . esc(lang('Lfl.cvDocNotAuthorized')) . '</span>' ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </fieldset>

                <p class="mb-3 text-xs text-muted"><?= lang('Lfl.cvIdempotentHint') ?></p>
                <button type="submit" class="btn btn-primary"><i class="icon icon-box-arrow-up-right"></i> <?= lang('Lfl.convertToClient') ?></button>
            </form>
        </div>

        <div class="space-y-4">
            <?php /* Los requisitos de cada paso, antes de enviar: los mismos que quedan como eventos en la bitácora. */ ?>
            <div class="card">
                <div class="card-header"><i class="icon icon-list-check"></i><h5 class="font-semibold"><?= lang('Lfl.cvRequirements') ?></h5></div>
                <div class="card-body">
                    <p class="mb-3 text-xs text-muted"><?= lang('Lfl.cvRequirementsHint') ?></p>
                    <?php foreach ($steps as $step): ?>
                        <div class="mb-3 last:mb-0">
                            <p class="flex items-center gap-2 text-sm font-medium <?= $step['runs'] ? '' : 'text-muted' ?>">
                                <i class="icon <?= esc($step['icon'], 'attr') ?>"></i>
                                <?= esc($step['label']) ?>
                                <?php if (! $step['runs']): ?>
                                    <span class="badge badge-light"><?= lang('Lfl.reqStepSkipped') ?></span>
                                <?php endif; ?>
                            </p>
                            <ul class="mt-1 space-y-1">
                                <?php foreach ($step['checks'] as $check): ?>
                                    <?php
                                    $tone = ['ok' => ['icon-check-circle', 'text-success'], 'warn' => ['icon-exclamation-circle', 'text-warning'],
                                        'error' => ['icon-x-circle', 'text-danger']][$check['status']] ?? ['icon-info-circle', 'text-muted'];
                                    ?>
                                    <li class="flex items-start gap-2 text-xs">
                                        <i class="icon <?= esc($tone[0], 'attr') ?> mt-0.5 shrink-0 <?= esc($tone[1], 'attr') ?>"></i>
                                        <span class="min-w-0"><span class="font-medium"><?= esc($check['label']) ?>:</span>
                                            <span class="break-words text-muted"><?= esc($check['detail']) ?></span></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="icon icon-arrow-repeat"></i><h5 class="font-semibold"><?= lang('Lfl.dwContactsCache') ?></h5></div>
                <div class="card-body">
                    <p class="mb-3 text-xs text-muted"><?= lang('Lfl.cvSyncHint') ?></p>
                    <form action="<?= base_url('leadflow/docketwise/contactos') ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= esc('leadflow/convertir/' . $id, 'attr') ?>">
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-repeat"></i> <?= lang('Lfl.dwSyncNow') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // El id del contacto elegido viaja en un solo campo oculto.
    document.querySelectorAll('[data-candidate]').forEach(function (r) {
        r.addEventListener('change', function () {
            var h = document.querySelector('[data-existing]');
            if (h && r.checked) { h.value = r.getAttribute('data-candidate'); }
        });
    });
    </script>
<?php endif; ?>
<?= $this->endSection() ?>
