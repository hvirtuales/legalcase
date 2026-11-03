<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-signpost"></i><h5 class="font-semibold"><?= lang('Lfl.navMarketing') ?></h5></div>
        <?php if ($rows !== null): ?>
            <a href="<?= base_url('leadflow/marketing/fuentes/nuevo') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Lfl.sourceNew') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($rows === null): ?>
            <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
        <?php else: ?>
            <?= view('Components\LeadFlow\Views\partials\_marketing_tabs', ['active' => 'sources']) ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Lfl.sourcesIntro') ?></p>
            <div class="table-card overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.name') ?></th>
                            <th><?= lang('Lfl.type') ?></th>
                            <th>utm_source</th>
                            <th><?= lang('Lfl.status') ?></th>
                            <th class="text-right"><?= lang('Lfl.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="font-medium"><?= esc($r['name']) ?></td>
                                <td><?= esc(Meta::label(Meta::SOURCE_KINDS, $r['kind'])) ?></td>
                                <td><code><?= esc($r['utm_source'] ?? '—') ?></code></td>
                                <td><span class="badge <?= $r['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= lang($r['is_active'] ? 'Lfl.active' : 'Lfl.inactive') ?></span></td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="<?= base_url('leadflow/marketing/fuentes/editar/' . (int) $r['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>
                                        <?= view('partials/delete_button', ['action' => base_url('leadflow/marketing/fuentes/eliminar/' . (int) $r['id']),
                                            'confirm' => lang('Lfl.confirmDelete'), 'label' => lang('Lfl.delete')]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
