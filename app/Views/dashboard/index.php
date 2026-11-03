<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<!-- Welcome Card -->
<div class="card mb-6">
    <div class="card-body">
        <h2 class="mb-2 text-xl font-semibold"><?= lang('App.welcome') ?>, <?= esc($user['first_name']) ?>!</h2>
        <p class="text-muted">
            <?= lang('App.loggedInAs') ?> <strong><?= esc($user['username']) ?></strong>
        </p>
    </div>
</div>

<!-- Stats Row -->
<div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('App.rolesAssigned') ?></p>
                <h3 class="text-2xl font-bold"><?= count($user['roles'] ?? []) ?></h3>
            </div>
            <i class="icon icon-person-badge text-3xl text-primary"></i>
        </div>
    </div>

    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('App.permissions') ?></p>
                <h3 class="text-2xl font-bold"><?= count($permissions ?? []) ?></h3>
            </div>
            <i class="icon icon-key text-3xl text-success"></i>
        </div>
    </div>

    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('App.lastAccess') ?></p>
                <p class="font-medium">
                    <?= $user['last_login_at'] ? date('d/m/Y', strtotime($user['last_login_at'])) : 'N/A' ?>
                </p>
            </div>
            <i class="icon icon-clock-history text-3xl text-info"></i>
        </div>
    </div>

    <div class="card">
        <div class="card-body flex items-center justify-between">
            <div>
                <p class="mb-1 text-sm text-muted"><?= lang('App.status') ?></p>
                <span class="badge badge-success"><?= lang('App.active') ?></span>
            </div>
            <i class="icon icon-check-circle text-3xl text-success"></i>
        </div>
    </div>
</div>

<!-- Roles Card -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-person-badge"></i>
        <h5 class="font-semibold"><?= lang('App.yourRoles') ?></h5>
    </div>
    <div class="card-body">
        <?php if (!empty($user['roles']) && is_array($user['roles'])): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ((array) $user['roles'] as $role): ?>
                    <span class="badge badge-primary px-3 py-1 text-sm">
                        <?= esc(is_array($role) ? $role['name'] : $role) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted"><?= lang('App.noRolesAssigned') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Permissions Card -->
<div class="card">
    <div class="card-header">
        <i class="icon icon-key"></i>
        <h5 class="font-semibold"><?= lang('App.yourPermissions') ?></h5>
    </div>
    <div class="card-body">
        <?php if (!empty($permissions)): ?>
            <?php
            $grouped = [];
            foreach ($permissions as $permission) {
                $grouped[$permission['module']][] = $permission;
            }
            ?>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <?php foreach ($grouped as $module => $perms): ?>
                    <div>
                        <h6 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                            <?= esc(ucfirst($module)) ?>
                        </h6>
                        <ul class="space-y-1">
                            <?php foreach ($perms as $perm): ?>
                                <li class="flex items-center gap-1.5 text-sm">
                                    <i class="icon icon-check-circle text-success"></i>
                                    <span><?= esc($perm['description'] ?? $perm['name']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted"><?= lang('App.noPermissionsAssigned') ?></p>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
