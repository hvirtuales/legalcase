<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <i class="icon icon-key"></i>
        <h5 class="font-semibold"><?= lang('App.systemPermissions') ?></h5>
    </div>
    <div class="card-body">
        <?php if (empty($permissions)): ?>
            <p class="py-4 text-center text-muted"><?= lang('App.noPermissionsRegistered') ?></p>
        <?php else: ?>
            <?php foreach ($permissions as $module => $perms): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="icon icon-folder"></i>
                        <h6 class="font-semibold uppercase"><?= esc(ucfirst($module)) ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?= lang('App.name') ?></th>
                                        <th><?= lang('App.description') ?></th>
                                        <th><?= lang('App.created') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($perms as $permission): ?>
                                        <tr>
                                            <td><strong><?= esc($permission['name']) ?></strong></td>
                                            <td><?= esc($permission['description'] ?? '-') ?></td>
                                            <td><?= date('d/m/Y', strtotime($permission['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
