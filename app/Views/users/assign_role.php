<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-person-badge"></i>
            <h5 class="font-semibold"><?= lang('App.assignRoles') ?></h5>
        </div>
        <a href="<?= base_url('users') ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-arrow-left"></i>
            <?= lang('App.back') ?>
        </a>
    </div>
    <div class="card-body">
        <!-- User Info -->
        <div class="alert alert-info">
            <span><strong><?= lang('App.user') ?>:</strong> <?= esc($user['username']) ?>
                (<?= esc($user['first_name'] . ' ' . $user['last_name']) ?>)</span>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error"><span><?= session()->getFlashdata('error') ?></span></div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><span><?= session()->getFlashdata('success') ?></span></div>
        <?php endif; ?>

        <form action="<?= base_url('users/assign-role/' . $user['id']) ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label mb-3 font-bold"><?= lang('App.selectRoles') ?></label>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <?php foreach ($roles as $role): ?>
                        <label class="form-check" for="role_<?= $role['id'] ?>">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="<?= $role['id'] ?>"
                                id="role_<?= $role['id'] ?>" <?= in_array($role['id'], array_column($userRoles, 'id')) ? 'checked' : '' ?>>
                            <span class="form-check-label">
                                <strong><?= esc($role['name']) ?></strong>
                                <?php if ($role['description']): ?>
                                    <small class="block text-muted"><?= esc($role['description']) ?></small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <small class="form-hint"><?= lang('App.selectMultipleRoles') ?></small>
            </div>

            <div class="mt-6 flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-save"></i>
                    <?= lang('App.save') ?>
                </button>
                <a href="<?= base_url('users') ?>" class="btn btn-secondary">
                    <i class="icon icon-x-circle"></i>
                    <?= lang('App.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
