<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\AutomationRegistry;
use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\AutomationModel;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-robot"></i><h5 class="font-semibold"><?= lang('Lfl.navAutomations') ?></h5></div>
        <?php if ($rows !== null): ?>
            <a href="<?= base_url('leadflow/automatizaciones/nueva') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Lfl.autNew') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($rows === null): ?>
            <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
        <?php else: ?>
            <?= view('Components\LeadFlow\Views\partials\_automations_tabs', ['active' => 'list']) ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Lfl.autIntro') ?></p>

            <?php if (! $rows): ?>
                <p class="mb-4 text-sm text-muted"><?= lang('Lfl.autNoAutomations') ?></p>
                <div class="alert alert-info">
                    <i class="icon icon-info-circle"></i>
                    <span class="flex flex-1 flex-wrap items-center justify-between gap-2">
                        <?= lang('Lfl.autDemoHint') ?>
                        <form action="<?= base_url('leadflow/automatizaciones/demo') ?>" method="post" onsubmit="return confirm('<?= esc(lang('Lfl.autDemoConfirm'), 'js') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-secondary btn-sm"><i class="icon icon-sparkles"></i> <?= lang('Lfl.demoButton') ?></button>
                        </form>
                    </span>
                </div>
            <?php else: ?>
                <div class="table-card overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Lfl.order') ?></th>
                                <th><?= lang('Lfl.name') ?></th>
                                <th><?= lang('Lfl.autTrigger') ?></th>
                                <th><?= lang('Lfl.autConditions') ?></th>
                                <th><?= lang('Lfl.autActions') ?></th>
                                <th><?= lang('Lfl.autRuns') ?></th>
                                <th><?= lang('Lfl.status') ?></th>
                                <th class="text-right"><?= lang('Lfl.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $conds   = AutomationModel::conditions($r);
                                $acts    = AutomationModel::actions($r);
                                $trigger = AutomationRegistry::TRIGGERS[$r['trigger_event']] ?? null;
                                ?>
                                <tr>
                                    <td class="text-muted"><?= (int) $r['sort'] ?></td>
                                    <td class="font-medium">
                                        <?= esc($r['name']) ?>
                                        <?php if ((int) $r['once_per_lead'] === 1): ?>
                                            <span class="badge badge-light" title="<?= esc(lang('Lfl.autOncePerLeadHint'), 'attr') ?>"><?= lang('Lfl.autOncePerLead') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-info"><?= esc($trigger ? lang('Lfl.' . $trigger) : $r['trigger_event']) ?></span></td>
                                    <td class="text-sm">
                                        <?php if (! $conds): ?>
                                            <span class="text-muted"><?= lang('Lfl.autAlways') ?></span>
                                        <?php else: ?>
                                            <?= esc(count($conds) . ' · ' . lang($r['match_mode'] === 'any' ? 'Lfl.autMatchAny' : 'Lfl.autMatchAll')) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm">
                                        <?php if (! $acts): ?>
                                            <span class="text-warning"><?= lang('Lfl.autNoActions') ?></span>
                                        <?php else: ?>
                                            <?php foreach ($acts as $a): ?>
                                                <span class="badge badge-light"><?= esc(lang('Lfl.' . (AutomationRegistry::ACTIONS[$a['type']][0] ?? 'action'))) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm">
                                        <?= (int) $r['runs'] ?>
                                        <span class="block text-xs text-muted"><?= esc($r['last_run_at'] ? Meta::date($r['last_run_at']) : lang('Lfl.autNever')) ?></span>
                                    </td>
                                    <td><span class="badge <?= (int) $r['is_active'] === 1 ? 'badge-success' : 'badge-secondary' ?>"><?= lang((int) $r['is_active'] === 1 ? 'Lfl.active' : 'Lfl.inactive') ?></span></td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <form action="<?= base_url('leadflow/automatizaciones/estado/' . (int) $r['id']) ?>" method="post">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang((int) $r['is_active'] === 1 ? 'Lfl.autDeactivate' : 'Lfl.autActivate'), 'attr') ?>">
                                                    <i class="icon <?= (int) $r['is_active'] === 1 ? 'icon-x-circle' : 'icon-play-circle' ?>"></i>
                                                </button>
                                            </form>
                                            <a href="<?= base_url('leadflow/automatizaciones/editar/' . (int) $r['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>
                                            <?php if ($canDelete): ?>
                                                <?= view('partials/delete_button', ['action' => base_url('leadflow/automatizaciones/eliminar/' . (int) $r['id']),
                                                    'confirm' => lang('Lfl.autDeleteConfirm'), 'label' => lang('Lfl.delete')]) ?>
                                            <?php endif; ?>
                                        </div>
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
