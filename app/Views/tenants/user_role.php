<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Rol de un usuario de las empresas del grupo. Misma forma que
 * `/users/assign-role/{id}`, con dos diferencias que vienen del nivel:
 * la lista es la de `TenantGuard::assignableRoles()` (de «Admin» hacia abajo)
 * y se elige **uno**, no varios.
 *
 * @var array $tenant @var array $user @var array $roles @var int $current @var array $locked
 */
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-person-badge"></i>
            <h5 class="font-semibold"><?= lang('Tenant.assignRole') ?></h5>
            <span class="badge badge-light"><i class="icon icon-diagram-3"></i> <?= esc($tenant['name']) ?></span>
        </div>
        <a href="<?= base_url('tenant/usuarios') ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-arrow-left"></i> <?= lang('App.back') ?>
        </a>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <span>
                <strong><?= lang('Tenant.user') ?>:</strong>
                <?= esc($user['username']) ?> (<?= esc(trim($user['first_name'] . ' ' . $user['last_name'])) ?>)
            </span>
        </div>

        <?php if ($locked): ?>
            <div class="alert alert-warning">
                <i class="icon icon-exclamation-circle"></i>
                <span>
                    <?= lang('Tenant.roleNotManageable') ?>
                    <?php foreach ($locked as $r): ?>
                        <span class="badge badge-light ml-1"><?= esc($r['name']) ?></span>
                    <?php endforeach; ?>
                </span>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('tenant/usuarios/' . (int) $user['id'] . '/rol') ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label mb-3 font-bold"><?= lang('Tenant.role') ?></label>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label class="form-check" for="role_none">
                        <input class="form-check-input" type="radio" name="role_id" value="0" id="role_none" <?= $current === 0 ? 'checked' : '' ?>>
                        <span class="form-check-label"><strong><?= lang('Tenant.noRole') ?></strong></span>
                    </label>
                    <?php foreach ($roles as $r): ?>
                        <label class="form-check" for="role_<?= esc($r['id'], 'attr') ?>">
                            <input class="form-check-input" type="radio" name="role_id" value="<?= esc($r['id'], 'attr') ?>"
                                id="role_<?= esc($r['id'], 'attr') ?>" <?= $current === (int) $r['id'] ? 'checked' : '' ?>>
                            <span class="form-check-label">
                                <strong><?= esc($r['name']) ?></strong>
                                <?php if (!empty($r['description'])): ?>
                                    <small class="block text-muted"><?= esc($r['description']) ?></small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <small class="form-hint"><?= lang('Tenant.roleHint') ?></small>
            </div>

            <div class="mt-6 flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Tenant.save') ?></button>
                <a href="<?= base_url('tenant/usuarios') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Tenant.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
