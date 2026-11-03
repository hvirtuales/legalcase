<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\AutomationRegistry;
use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\LeadModel;

?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-clock-history"></i><h5 class="font-semibold"><?= lang('Lfl.autTabRuns') ?></h5>
    </div>
    <div class="card-body">
        <?php if ($runs === null): ?>
            <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
        <?php else: ?>
            <?= view('Components\LeadFlow\Views\partials\_automations_tabs', ['active' => 'runs']) ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Lfl.autRunsIntro') ?></p>

            <?php if (! $runs): ?>
                <p class="text-sm text-muted"><?= lang('Lfl.autNoRuns') ?></p>
            <?php else: ?>
                <div class="table-card overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Lfl.date') ?></th>
                                <th><?= lang('Lfl.navAutomations') ?></th>
                                <th><?= lang('Lfl.lead') ?></th>
                                <th><?= lang('Lfl.autRunActions') ?></th>
                                <th><?= lang('Lfl.status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($runs as $r): ?>
                                <?php $applied = json_decode((string) $r['actions'], true) ?: []; ?>
                                <tr>
                                    <td class="whitespace-nowrap text-sm"><?= esc(Meta::date($r['created_at'])) ?></td>
                                    <td class="font-medium"><?= esc($r['automation_name']) ?></td>
                                    <td>
                                        <?php if (! empty($r['lead_id']) && ($r['first_name'] ?? '') !== ''): ?>
                                            <a class="text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $r['lead_id']) ?>">
                                                <?= esc(LeadModel::fullName(['first_name' => $r['first_name'], 'last_name' => $r['last_name']])) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm">
                                        <?php if (! $applied): ?>
                                            <span class="text-muted">—</span>
                                        <?php else: ?>
                                            <?php foreach ($applied as $a): ?>
                                                <span class="badge badge-light">
                                                    <?= esc(lang('Lfl.' . (AutomationRegistry::ACTIONS[$a['type'] ?? ''][0] ?? 'action'))) ?><?= ($a['detail'] ?? '') !== '' ? esc(': ' . $a['detail']) : '' ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if (! empty($r['message'])): ?>
                                            <span class="block text-xs text-danger"><?= esc($r['message']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= $r['status'] === 'ok' ? 'badge-success' : 'badge-danger' ?>"><?= lang($r['status'] === 'ok' ? 'Lfl.autRunOk' : 'Lfl.autRunError') ?></span></td>
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
