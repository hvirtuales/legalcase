<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Libraries\ScoringService;

$id = (int) $lead['id'];
?>
<?php
/*
 * El caso en USCIS, si este lead trae número de recibo.
 *
 * Va LO PRIMERO del resumen y no en una pestaña aparte porque su razón de ser es que
 * el asesor lo vea antes de llamar: enterrado en la octava pestaña llega tarde.
 *
 * El texto es el de USCIS, en el idioma de la interfaz, sin interpretar. «Consultar
 * ahora» existe porque el refresco programado va por tandas y alguien que está a punto
 * de llamar no puede esperar a la próxima pasada; gasta una consulta del cupo y por eso
 * no se hace solo al abrir la ficha.
 */
$recibo = trim((string) ($lead['uscis_receipt'] ?? ''));
$uscis  = null;
if ($recibo !== '') {
    $uscis = (new \Components\LeadFlow\Libraries\Uscis\UscisService())->config((int) $lead['company_id']);
    $filaU = (new \Components\LeadFlow\Models\CaseStatusModel())->lookup((int) $lead['company_id'], $recibo);
    $casoU = $filaU ? \Components\LeadFlow\Models\CaseStatusModel::toCase($filaU) : null;
    $esU   = str_starts_with(service('request')->getLocale(), 'es');
}
?>
<?php if ($recibo !== ''): ?>
    <div class="card mb-4">
        <div class="card-header justify-between">
            <div class="flex items-center gap-2">
                <i class="icon icon-landmark"></i>
                <h5 class="font-semibold"><?= lang('Lfl.uscLeadTitle') ?></h5>
                <code class="badge badge-light"><?= esc($recibo) ?></code>
            </div>
            <?php if ($uscis['mode'] === 'live' && $uscis['configured']): ?>
                <form action="<?= base_url('leadflow/leads/caso/' . (int) $lead['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">
                        <i class="icon icon-arrow-repeat"></i> <?= lang('Lfl.uscLeadRefresh') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($uscis['mode'] !== 'live' || ! $uscis['configured']): ?>
                <p class="text-sm text-muted"><?= lang('Lfl.uscLeadOff') ?></p>
            <?php elseif ($casoU === null): ?>
                <p class="text-sm text-muted"><?= lang('Lfl.uscLeadNever') ?></p>
            <?php else: ?>
                <p class="font-semibold">
                    <?= esc(($esU ? ($casoU['status_es'] ?? $casoU['status']) : $casoU['status']) ?: lang('Lfl.uscPubNoStatus')) ?>
                </p>
                <?php if ($d = ($esU ? ($casoU['detail_es'] ?? $casoU['detail']) : $casoU['detail'])): ?>
                    <p class="mt-1 text-sm text-muted"><?= esc($d) ?></p>
                <?php endif; ?>
                <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                    <?php foreach ([
                        'uscPubForm'      => $casoU['form_type'] ?: '—',
                        'uscPubSubmitted' => $casoU['submitted_at'] ? date('d/m/Y', strtotime((string) $casoU['submitted_at'])) : '—',
                        'uscPubUpdated'   => $casoU['updated_at'] ? date('d/m/Y', strtotime((string) $casoU['updated_at'])) : '—',
                    ] as $cl => $val): ?>
                        <div><dt class="text-muted"><?= lang('Lfl.' . $cl) ?></dt><dd class="font-medium"><?= esc($val) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <?php if (! empty($filaU['checked_at'])): ?>
                    <p class="mt-3 text-xs text-muted">
                        <?= esc(lang('Lfl.uscCheckedAt', [date('d/m/Y H:i', strtotime((string) $filaU['checked_at']))])) ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-info-circle"></i><h5 class="font-semibold"><?= lang('Lfl.keyFacts') ?></h5></div>
            <div class="card-body">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <?php
                    $facts = [
                        [lang('Lfl.source'), $source['name'] ?? '—'],
                        [lang('Lfl.campaign'), $campaign['name'] ?? '—'],
                        [lang('Lfl.potentialValue'), Meta::money($lead['potential_value'], $currency)],
                        [lang('Lfl.wonValue'), Meta::money($lead['won_value'], $currency)],
                        [lang('Lfl.requestedConsultation'), $lead['requested_consultation'] ? lang('Lfl.yes') : lang('Lfl.no')],
                        [lang('Lfl.created'), Meta::date($lead['created_at']) . ' (' . Meta::since($lead['created_at']) . ')'],
                        [lang('Lfl.firstResponse'), Meta::date($lead['first_response_at'])],
                        [lang('Lfl.lastContact'), Meta::date($lead['last_contact_at'])],
                        [lang('Lfl.lastInbound'), Meta::date($lead['last_inbound_at'])],
                        [lang('Lfl.timeInStage'), Meta::since($lead['stage_entered_at'])],
                    ];
                    foreach ($facts as [$label, $value]): ?>
                        <div class="flex justify-between gap-2 border-b border-line py-1">
                            <dt class="text-muted"><?= esc($label) ?></dt>
                            <dd class="text-right font-medium"><?= esc($value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <?php if ($lead['status'] === 'lost'): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="icon icon-x-circle"></i>
                        <span><?= esc(lang('Lfl.lostReasonIs', [Meta::label(Meta::LOST_REASONS, $lead['lost_reason'])])) ?>
                            <?= $lead['lost_note'] ? ' — ' . esc($lead['lost_note']) : '' ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($lead['summary']): ?>
                    <h6 class="mt-4 text-sm font-semibold"><?= lang('Lfl.summary') ?></h6>
                    <p class="mt-1 whitespace-pre-line text-sm"><?= esc($lead['summary']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-star"></i><h5 class="font-semibold"><?= lang('Lfl.scoreBreakdown') ?></h5></div>
            <div class="card-body">
                <p class="mb-3 text-xs text-muted"><?= lang('Lfl.scoreBreakdownHint') ?></p>
                <ul class="divide-y divide-line">
                    <?php foreach ($breakdown as $criterion => $b): ?>
                        <li class="flex items-center justify-between py-1.5 text-sm <?= $b['hit'] && $b['active'] ? '' : 'text-muted' ?>">
                            <span>
                                <i class="icon <?= $b['hit'] ? 'icon-check-circle text-success' : 'icon-circle' ?>"></i>
                                <?= lang('Lfl.' . ScoringService::CRITERIA_LABELS[$criterion]) ?>
                                <?= $b['active'] ? '' : '<span class="badge badge-light">' . esc(lang('Lfl.inactive')) . '</span>' ?>
                            </span>
                            <span class="font-semibold <?= $b['points'] < 0 ? 'text-danger' : '' ?>"><?= $b['points'] > 0 ? '+' : '' ?><?= (int) $b['points'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2"><i class="icon icon-clock-history"></i><h5 class="font-semibold"><?= lang('Lfl.tabTimeline') ?></h5></div>
                <a href="<?= base_url('leadflow/leads/ficha/' . $id . '?tab=timeline') ?>" class="btn btn-ghost btn-sm"><?= lang('Lfl.viewAll') ?> <i class="icon icon-chevron-right"></i></a>
            </div>
            <div class="card-body">
                <?= $this->include('Components\LeadFlow\Views\leads\tabs\_timeline_list') ?>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <?php if ($canEdit): ?>
            <div class="card">
                <div class="card-header"><i class="icon icon-arrow-repeat"></i><h5 class="font-semibold"><?= lang('Lfl.changeStage') ?></h5></div>
                <div class="card-body">
                    <?php if ($lead['status'] === 'converted'): ?>
                        <p class="text-sm text-muted"><?= lang('Lfl.errLeadConverted') ?></p>
                    <?php else: ?>
                        <form action="<?= base_url('leadflow/leads/etapa/' . $id) ?>" method="post" data-stage-form>
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label for="stage_id" class="form-label"><?= lang('Lfl.stage') ?></label>
                                <select id="stage_id" name="stage_id" class="form-select" data-stage-select>
                                    <?php foreach ($stages as $s): ?>
                                        <?php if ((int) $s['is_active'] !== 1 || $s['kind'] === 'converted') {
                                            continue;
                                        } ?>
                                        <option value="<?= (int) $s['id'] ?>" data-kind="<?= esc($s['kind'], 'attr') ?>" <?= (int) $s['id'] === (int) $lead['stage_id'] ? 'selected' : '' ?>>
                                            <?= esc($s['name']) ?><?= $s['kind'] === 'lost' ? ' · ' . esc(lang('Lfl.kindLost')) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div data-lost-fields hidden>
                                <div class="form-group">
                                    <label for="reason" class="form-label"><?= lang('Lfl.lostReason') ?> <span class="text-danger">*</span></label>
                                    <select id="reason" name="reason" class="form-select">
                                        <option value=""><?= lang('Lfl.choose') ?></option>
                                        <?php foreach (Meta::options(Meta::LOST_REASONS) as $val => $lbl): ?>
                                            <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="note" class="form-label"><?= lang('Lfl.lostNote') ?></label>
                                    <input type="text" id="note" name="note" maxlength="255" class="form-input">
                                </div>
                            </div>
                            <div data-won-fields hidden>
                                <div class="form-group">
                                    <label for="won_value" class="form-label"><?= lang('Lfl.wonValue') ?> (<?= esc($currency) ?>)</label>
                                    <input type="number" step="0.01" min="0" id="won_value" name="won_value" class="form-input"
                                        value="<?= esc((string) ($lead['won_value'] ?? $lead['potential_value'] ?? ''), 'attr') ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lfl.move') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="icon icon-person-badge"></i><h5 class="font-semibold"><?= lang('Lfl.assignment') ?></h5></div>
                <div class="card-body">
                    <?php if ($access->canAssignOthers()): ?>
                        <form action="<?= base_url('leadflow/leads/asignar/' . $id) ?>" method="post">
                            <?= csrf_field() ?>
                            <?php foreach (['assigned_user_id' => 'advisor', 'lawyer_user_id' => 'lawyer'] as $field => $label): ?>
                                <div class="form-group">
                                    <label for="a-<?= esc($field, 'attr') ?>" class="form-label"><?= lang('Lfl.' . $label) ?></label>
                                    <select id="a-<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-select">
                                        <option value=""><?= lang('Lfl.nobody') ?></option>
                                        <?php foreach ($team as $uid => $uname): ?>
                                            <option value="<?= (int) $uid ?>" <?= (int) $lead[$field] === (int) $uid ? 'selected' : '' ?>><?= esc($uname) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                        </form>
                    <?php elseif (empty($lead['assigned_user_id'])): ?>
                        <?php /* Un asesor no reparte leads: se lleva los que no tienen dueño. */ ?>
                        <form action="<?= base_url('leadflow/leads/asignar/' . $id) ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="assigned_user_id" value="<?= (int) $access->userId ?>">
                            <input type="hidden" name="lawyer_user_id" value="<?= (int) ($lead['lawyer_user_id'] ?? 0) ?>">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-person-plus"></i> <?= lang('Lfl.takeLead') ?></button>
                        </form>
                    <?php else: ?>
                        <p class="text-sm text-muted"><?= lang('Lfl.assignOnlySupervisor') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="icon icon-exclamation-circle"></i><h5 class="font-semibold"><?= lang('Lfl.priority') ?></h5></div>
                <div class="card-body">
                    <form action="<?= base_url('leadflow/leads/prioridad/' . $id) ?>" method="post" class="flex gap-2">
                        <?= csrf_field() ?>
                        <select name="priority" class="form-select" aria-label="<?= esc(lang('Lfl.priority'), 'attr') ?>">
                            <option value="auto" <?= (int) $lead['priority_manual'] === 0 ? 'selected' : '' ?>><?= lang('Lfl.priorityAuto') ?></option>
                            <?php foreach (Meta::options(Meta::PRIORITIES) as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= (int) $lead['priority_manual'] === 1 && $lead['priority'] === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-save"></i></button>
                    </form>
                    <p class="form-hint"><?= lang('Lfl.priorityHint') ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="icon icon-list-check"></i><h5 class="font-semibold"><?= lang('Lfl.pendingActivities') ?></h5></div>
            <div class="card-body">
                <?php if (! $activities): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($activities as $act): ?>
                            <li class="flex items-center justify-between gap-2 py-2 text-sm">
                                <span class="min-w-0 truncate"><i class="icon <?= esc(Meta::ACTIVITY_ICONS[$act['activity_type']] ?? 'icon-circle', 'attr') ?>"></i> <?= esc($act['title']) ?></span>
                                <span class="shrink-0 text-xs <?= $act['due_at'] && $act['due_at'] < date('Y-m-d H:i:s') ? 'text-danger' : 'text-muted' ?>"><?= esc(Meta::date($act['due_at'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($nextAppt): ?>
                    <div class="alert alert-info mt-3">
                        <i class="icon icon-headset"></i>
                        <span><?= esc(lang('Lfl.nextAppointment', [Meta::date($nextAppt['scheduled_at']), Meta::label(Meta::APPT_MODES, $nextAppt['mode'])])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canDelete): ?>
            <div class="flex justify-end">
                <?= view('partials/delete_button', [
                    'action'  => base_url('leadflow/leads/eliminar/' . $id),
                    'confirm' => lang('Lfl.leadDelConfirm'),
                    'label'   => lang('Lfl.delete'),
                    'text'    => lang('Lfl.leadDelete'),
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Al elegir un estado alternativo aparece el motivo (obligatorio); al elegir
// «Ganado», el valor ganado. El servidor exige lo mismo: esto solo lo enseña.
(function () {
    var sel = document.querySelector('[data-stage-select]');
    if (!sel) { return; }
    var lost = document.querySelector('[data-lost-fields]');
    var won = document.querySelector('[data-won-fields]');
    var sync = function () {
        var kind = sel.options[sel.selectedIndex].getAttribute('data-kind');
        lost.hidden = kind !== 'lost';
        won.hidden = kind !== 'won';
        var reason = lost.querySelector('select');
        if (reason) { reason.required = kind === 'lost'; }
    };
    sel.addEventListener('change', sync);
    sync();
})();
</script>
