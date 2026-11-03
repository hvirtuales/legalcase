<?php
/**
 * LegalCase Connect en la ficha: el plan (compromiso de informar), las actualizaciones
 * con su estado de aprobación y el registro técnico de avisos enviados.
 *
 * @var array $matter @var ?array $plan @var array $updates @var array $deliveries @var array $recipients
 * @var array $users @var array $settings @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\CommPlanModel;

$id   = (int) $matter['id'];
$open = $matter['status'] !== 'closed';
?>
<div class="grid gap-4 lg:grid-cols-3">
    <div class="min-w-0 space-y-4 lg:col-span-2">
        <div class="card">
            <div class="card-header flex-wrap justify-between gap-2">
                <div class="flex items-center gap-2"><i class="icon icon-send"></i><h5 class="font-semibold"><?= lang('Lgc.updates') ?></h5></div>
                <?php if ($open && ($canWork && $acc->can('lgc.create'))): ?>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?= base_url('legalcase/asuntos/actualizacion/' . $id) ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Lgc.updNew') ?></a>
                        <a href="<?= base_url('legalcase/asuntos/actualizacion/' . $id . '?tipo=no_news') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-history"></i> <?= lang('Lgc.updNoNews') ?></a>
                    </div>
                <?php elseif (!$open && ($canWork && $acc->can('lgc.create'))): ?>
                    <a href="<?= base_url('legalcase/asuntos/actualizacion/' . $id . '?tipo=closing') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-check-circle"></i> <?= lang('Lgc.updClosing') ?></a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$updates): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noUpdates') ?></p>
                <?php else: ?>
                    <ul class="divide-y divide-line">
                        <?php foreach ($updates as $u): ?>
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <div>
                                    <a class="font-semibold text-primary" href="<?= base_url('legalcase/actualizaciones/ver/' . (int) $u['id']) ?>"><?= esc($u['title']) ?></a>
                                    <span class="badge badge-light"><?= lang('Lgc.ukind_' . $u['kind']) ?></span>
                                    <?= (int) $u['version'] > 1 ? '<span class="text-xs text-muted">v' . (int) $u['version'] . '</span>' : '' ?>
                                    <div class="text-xs text-muted">
                                        <?= esc($users[(int) $u['author_id']] ?? lang('Lgc.system')) ?> ·
                                        <?= esc(Meta::date($u['published_at'] ?: $u['updated_at'])) ?>
                                    </div>
                                </div>
                                <?= Meta::badge('upd', $u['status']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-envelope"></i><h5 class="font-semibold"><?= lang('Lgc.deliveries') ?></h5></div>
            <div class="card-body">
                <p class="mb-3 text-xs text-muted"><?= lang('Lgc.deliveriesHint') ?></p>
                <?php if (!$deliveries): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noDeliveries') ?></p>
                <?php else: ?>
                    <div class="table-card overflow-x-auto">
                        <table class="table">
                            <thead><tr><th><?= lang('Lgc.fDate') ?></th><th><?= lang('Lgc.fRecipient') ?></th><th><?= lang('Lgc.fKind') ?></th><th><?= lang('Lgc.fStatus') ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($deliveries as $d): ?>
                                <tr>
                                    <td class="whitespace-nowrap text-xs"><?= esc(Meta::date($d['sent_at'] ?: $d['created_at'])) ?></td>
                                    <td class="text-xs"><?= esc($d['recipient']) ?></td>
                                    <td class="text-xs"><?= lang('Lgc.dkind_' . $d['kind']) ?></td>
                                    <td>
                                        <?= Meta::badge('delivery', $d['status']) ?>
                                        <?php if ($d['status'] === 'failed'): ?><div class="max-w-xs truncate text-xs text-danger" title="<?= esc($d['last_error'], 'attr') ?>"><?= esc($d['last_error']) ?> (<?= (int) $d['attempts'] ?>)</div><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="min-w-0 space-y-4">
        <div class="card">
            <div class="card-header"><i class="icon icon-clock-history"></i><h5 class="font-semibold"><?= lang('Lgc.planTitle') ?></h5></div>
            <div class="card-body">
                <p class="mb-3 text-xs text-muted"><?= lang('Lgc.planHint') ?></p>
                <?php if (($canWork && $acc->can('lgc.edit'))): ?>
                    <form method="post" action="<?= base_url('legalcase/asuntos/plan/' . $id) ?>" class="space-y-3">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label" for="pl-mode"><?= lang('Lgc.fMode') ?></label>
                            <select id="pl-mode" name="mode" class="form-select">
                                <?php foreach (CommPlanModel::MODES as $m): ?><option value="<?= $m ?>" <?= ($plan['mode'] ?? 'hybrid') === $m ? 'selected' : '' ?>><?= lang('Lgc.mode_' . $m) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label class="form-label" for="pl-cad"><?= lang('Lgc.fCadence') ?></label>
                                <input id="pl-cad" type="number" min="1" max="365" name="cadence_days" class="form-input" value="<?= (int) ($plan['cadence_days'] ?? $settings['default_cadence_days']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="pl-sla"><?= lang('Lgc.fSla') ?></label>
                                <input id="pl-sla" type="number" min="1" max="720" name="response_sla_hours" class="form-input" value="<?= (int) ($plan['response_sla_hours'] ?? $settings['response_sla_hours']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="pl-next"><?= lang('Lgc.fNextUpdate') ?></label>
                            <input id="pl-next" type="datetime-local" name="next_update_due_at" class="form-input" value="<?= esc(Meta::inputDt($plan['next_update_due_at'] ?? null), 'attr') ?>">
                            <small class="form-hint"><?= lang('Lgc.fNextUpdateHint') ?></small>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="pl-owner"><?= lang('Lgc.fOwner') ?></label>
                            <select id="pl-owner" name="owner_id" class="form-select">
                                <?php foreach ($users as $uid => $name): ?><option value="<?= (int) $uid ?>" <?= (int) ($plan['owner_id'] ?? 0) === (int) $uid ? 'selected' : '' ?>><?= esc($name) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="pl-act" name="is_active" value="1" class="form-check-input" <?= (int) ($plan['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label for="pl-act" class="form-check-label"><?= lang('Lgc.fPlanActive') ?></label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button>
                    </form>
                <?php else: ?>
                    <p class="text-sm"><?= $plan ? lang('Lgc.mode_' . $plan['mode']) : '—' ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-people"></i><h5 class="font-semibold"><?= lang('Lgc.recipients') ?></h5></div>
            <div class="card-body text-sm">
                <?php if (!$recipients): ?>
                    <p class="text-muted"><?= lang('Lgc.noRecipients') ?></p>
                    <a class="text-primary" href="<?= base_url('legalcase/asuntos/ficha/' . $id . '?tab=partes') ?>"><?= lang('Lgc.managePortal') ?></a>
                <?php else: ?>
                    <ul class="space-y-1">
                        <?php foreach ($recipients as $r): ?>
                            <li><i class="icon icon-person-circle"></i> <?= esc($r['email']) ?> <?= (int) $r['notify_email'] ? '' : '<span class="badge badge-secondary">' . lang('Lgc.optedOut') . '</span>' ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
