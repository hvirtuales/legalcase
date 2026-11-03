<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-gear"></i><h5 class="font-semibold"><?= lang('Lfl.navSettings') ?></h5></div>
        <?php if ($rows !== null): ?>
            <a href="<?= base_url('leadflow/configuracion/servicios/nuevo') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Lfl.serviceNew') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($rows === null): ?>
            <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
        <?php else: ?>
            <?= view('Components\LeadFlow\Views\partials\_settings_tabs', ['active' => 'services']) ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Lfl.servicesIntro') ?></p>
            <div class="table-card overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Lfl.name') ?></th>
                            <th><?= lang('Lfl.owner') ?></th>
                            <th><?= lang('Lfl.form') ?></th>
                            <th><?= lang('Lfl.matterType') ?></th>
                            <th><?= lang('Lfl.status') ?></th>
                            <th class="text-right"><?= lang('Lfl.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <span class="font-medium"><?= esc($r['name']) ?></span>
                                    <?php if ($r['is_priority']): ?><span class="badge badge-danger"><?= lang('Lfl.priorityService') ?></span><?php endif; ?>
                                </td>
                                <td><?= esc($team[(int) $r['owner_user_id']] ?? '—') ?></td>
                                <td><?= esc($forms[(int) $r['form_id']] ?? '—') ?></td>
                                <td class="text-sm"><?= esc($r['matter_type_name'] ?? '—') ?></td>
                                <td><span class="badge <?= $r['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= lang($r['is_active'] ? 'Lfl.active' : 'Lfl.inactive') ?></span></td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="<?= base_url('leadflow/configuracion/servicios/editar/' . (int) $r['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>
                                        <?= view('partials/delete_button', ['action' => base_url('leadflow/configuracion/servicios/eliminar/' . (int) $r['id']),
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
