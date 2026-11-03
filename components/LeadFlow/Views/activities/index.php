<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;

$volver = 'leadflow/actividades?vista=' . ($view ?? 'mias');
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-list-check"></i>
        <h5 class="font-semibold"><?= lang('Lfl.navActivities') ?></h5>
    </div>
    <div class="card-body">
        <?php if ($rows === null): ?>
            <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
        <?php elseif ($access->scope === 'none'): ?>
            <?= view('Components\LeadFlow\Views\partials\_noaccess') ?>
        <?php else: ?>
            <nav class="tabs mb-4">
                <?php foreach (['mias' => 'actMine', 'vencidas' => 'actOverdue', 'hoy' => 'actToday', 'todas' => 'actAllPending', 'hechas' => 'actDone'] as $v => $label): ?>
                    <a href="<?= base_url('leadflow/actividades?vista=' . $v) ?>" class="tab-link <?= $view === $v ? 'active' : '' ?>"><?= lang('Lfl.' . $label) ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if (! $rows): ?>
                <p class="text-sm text-muted"><?= lang('Lfl.noActivities') ?></p>
            <?php else: ?>
                <div class="table-card overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Lfl.activity') ?></th>
                                <th><?= lang('Lfl.lead') ?></th>
                                <th><?= lang('Lfl.dueAt') ?></th>
                                <th><?= lang('Lfl.owner') ?></th>
                                <th><?= lang('Lfl.priority') ?></th>
                                <th class="text-right"><?= lang('Lfl.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $act): ?>
                                <?php $late = $act['status'] === 'pending' && $act['due_at'] && $act['due_at'] < date('Y-m-d H:i:s'); ?>
                                <tr>
                                    <td>
                                        <div class="font-medium"><i class="icon <?= esc(Meta::ACTIVITY_ICONS[$act['activity_type']] ?? 'icon-circle', 'attr') ?>"></i> <?= esc($act['title']) ?></div>
                                        <div class="text-xs text-muted"><?= esc(Meta::label(Meta::ACTIVITY_TYPES, $act['activity_type'])) ?></div>
                                    </td>
                                    <td>
                                        <a class="text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $act['lead_id'] . '?tab=actividades') ?>">
                                            <?= esc(trim($act['first_name'] . ' ' . $act['last_name'])) ?>
                                        </a>
                                    </td>
                                    <td class="<?= $late ? 'text-danger' : '' ?>">
                                        <?= esc(Meta::date($act['status'] === 'done' ? $act['completed_at'] : $act['due_at'])) ?>
                                    </td>
                                    <td><?= esc($team[(int) $act['assigned_user_id']] ?? '—') ?></td>
                                    <td><span class="badge <?= Meta::color(Meta::PRIORITY_COLORS, $act['priority']) ?>"><?= esc(Meta::label(Meta::PRIORITIES, $act['priority'])) ?></span></td>
                                    <td>
                                        <?php if ($canEdit): ?>
                                            <form action="<?= base_url('leadflow/actividades/' . ($act['status'] === 'done' ? 'reabrir/' : 'completar/') . (int) $act['id']) ?>" method="post" class="flex justify-end">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    <i class="icon <?= $act['status'] === 'done' ? 'icon-arrow-counterclockwise' : 'icon-check-circle' ?>"></i>
                                                    <?= lang($act['status'] === 'done' ? 'Lfl.reopen' : 'Lfl.complete') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
