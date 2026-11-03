<?php

use Components\LeadFlow\Libraries\Meta;

/**
 * Una consulta con sus acciones. La usan la ficha del lead y la agenda.
 *
 * Borrar va aparte de editar: se puede borrar una consulta ya cerrada —para
 * limpiar una que se agendó por error—, y por eso el botón queda fuera del bloque
 * de acciones de estado, que solo aparece mientras sigue abierta.
 *
 * @var array     $ap
 * @var array     $team
 * @var bool      $canEdit
 * @var bool|null $canDelete
 * @var string    $volver
 * @var bool      $showLead
 */
$canDelete ??= false;
$aid    = (int) $ap['id'];
$closed = in_array($ap['status'], ['completed', 'cancelled'], true);
?>
<div class="card">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <div class="text-sm font-semibold">
                    <?= esc(Meta::date($ap['scheduled_at'])) ?> · <?= (int) $ap['duration_min'] ?> min
                    · <?= esc(Meta::label(Meta::APPT_MODES, $ap['mode'])) ?>
                </div>
                <?php if ($showLead): ?>
                    <a class="text-sm text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $ap['lead_id'] . '?tab=consultas') ?>">
                        <?= esc(trim(($ap['first_name'] ?? '') . ' ' . ($ap['last_name'] ?? ''))) ?>
                    </a>
                <?php endif; ?>
                <div class="text-xs text-muted">
                    <?= esc(lang('Lfl.lawyer')) ?>: <?= esc($ap['lawyer_user_id'] ? ($team[(int) $ap['lawyer_user_id']] ?? '—') : lang('Lfl.nobody')) ?>
                    <?= (int) $ap['reschedule_count'] > 0 ? ' · ' . esc(lang('Lfl.rescheduledTimes', [(int) $ap['reschedule_count']])) : '' ?>
                </div>
                <?php if ($ap['location']): ?>
                    <div class="break-all text-xs"><i class="icon icon-geo-alt"></i> <?= esc($ap['location']) ?></div>
                <?php endif; ?>
                <?php if ($ap['result']): ?>
                    <div class="mt-1 text-xs"><?= lang('Lfl.result') ?>: <strong><?= esc(Meta::label(Meta::APPT_RESULTS, $ap['result'])) ?></strong></div>
                <?php endif; ?>
                <?php if ($ap['notes']): ?>
                    <p class="mt-1 whitespace-pre-line text-xs text-muted"><?= esc($ap['notes']) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge <?= Meta::color(Meta::APPT_COLORS, $ap['status']) ?>"><?= esc(Meta::label(Meta::APPT_STATUSES, $ap['status'])) ?></span>
                <?php if ($canDelete): ?>
                    <?= view('partials/delete_button', [
                        'action'  => base_url('leadflow/consultas/eliminar/' . $aid),
                        'confirm' => lang('Lfl.apDeleteConfirm'),
                        'label'   => lang('Lfl.delete'),
                    ]) ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canEdit && ! $closed): ?>
            <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-line pt-3">
                <?php foreach (['confirmed' => ['icon-check-circle', 'apConfirm'], 'no_show' => ['icon-dash-circle', 'apNoShow'], 'cancelled' => ['icon-x-circle', 'apCancel']] as $st => [$icon, $label]): ?>
                    <?php if ($st === 'confirmed' && $ap['status'] === 'confirmed') {
                        continue;
                    } ?>
                    <form action="<?= base_url('leadflow/consultas/estado/' . $aid) ?>" method="post"
                        <?= $st === 'cancelled' ? 'onsubmit="return confirm(\'' . esc(lang('Lfl.apCancelConfirm'), 'js') . '\')"' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= esc($st, 'attr') ?>">
                        <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="icon <?= esc($icon, 'attr') ?>"></i> <?= lang('Lfl.' . $label) ?></button>
                    </form>
                <?php endforeach; ?>

                <details class="w-full">
                    <summary class="cursor-pointer text-sm text-primary"><?= lang('Lfl.apRegisterResult') ?></summary>
                    <form action="<?= base_url('leadflow/consultas/resultado/' . $aid) ?>" method="post" class="mt-2 flex flex-wrap items-end gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                        <div class="form-group mb-0">
                            <label for="res-<?= (int) $aid ?>" class="form-label text-xs"><?= lang('Lfl.result') ?></label>
                            <select id="res-<?= (int) $aid ?>" name="result" class="form-select" required>
                                <option value=""><?= lang('Lfl.choose') ?></option>
                                <?php foreach (Meta::options(Meta::APPT_RESULTS) as $val => $lbl): ?>
                                    <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0 flex-1">
                            <label for="rn-<?= (int) $aid ?>" class="form-label text-xs"><?= lang('Lfl.notes') ?></label>
                            <input type="text" id="rn-<?= (int) $aid ?>" name="notes" maxlength="500" class="form-input">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-check-circle"></i> <?= lang('Lfl.apAttended') ?></button>
                    </form>
                </details>

                <details class="w-full">
                    <summary class="cursor-pointer text-sm text-primary"><?= lang('Lfl.apReschedule') ?></summary>
                    <form action="<?= base_url('leadflow/consultas/reprogramar/' . $aid) ?>" method="post" class="mt-2 flex flex-wrap items-end gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                        <div class="form-group mb-0">
                            <label for="rd-<?= (int) $aid ?>" class="form-label text-xs"><?= lang('Lfl.date') ?></label>
                            <input type="date" id="rd-<?= (int) $aid ?>" name="date" class="form-input" required value="<?= esc(substr($ap['scheduled_at'], 0, 10), 'attr') ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label for="rt-<?= (int) $aid ?>" class="form-label text-xs"><?= lang('Lfl.time') ?></label>
                            <input type="time" id="rt-<?= (int) $aid ?>" name="time" class="form-input" required value="<?= esc(substr($ap['scheduled_at'], 11, 5), 'attr') ?>">
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-repeat"></i> <?= lang('Lfl.apReschedule') ?></button>
                    </form>
                </details>
            </div>
        <?php endif; ?>
    </div>
</div>
