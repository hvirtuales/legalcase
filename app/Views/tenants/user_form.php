<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta de un usuario en una empresa del grupo: uno nuevo, o uno que ya tiene
 * cuenta en la plataforma. Misma forma que `/users/create`.
 *
 * @var array $tenant @var array $children @var array $roles @var array $users
 */
$mode = old('mode', 'new');
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-person-plus"></i>
            <h5 class="font-semibold"><?= lang('Tenant.newUser') ?></h5>
            <span class="badge badge-light"><i class="icon icon-diagram-3"></i> <?= esc($tenant['name']) ?></span>
        </div>
        <a href="<?= base_url('tenant/usuarios') ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-arrow-left"></i> <?= lang('App.back') ?>
        </a>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-error">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('tenant/usuarios/nuevo') ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group md:w-1/2">
                <label for="company_id" class="form-label"><?= lang('Tenant.company') ?> *</label>
                <select class="form-select" id="company_id" name="company_id" required>
                    <option value="">—</option>
                    <?php foreach ($children as $c): ?>
                        <option value="<?= esc($c['id'], 'attr') ?>" <?= (string) old('company_id') === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= esc($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint"><?= lang('Tenant.newUserHint') ?></small>
            </div>

            <div class="mb-4 flex flex-wrap gap-4">
                <label class="form-check" for="mode_new">
                    <input class="form-check-input" type="radio" id="mode_new" name="mode" value="new"
                        <?= $mode === 'new' ? 'checked' : '' ?> onchange="toggleUserMode()">
                    <span class="form-check-label"><?= lang('Tenant.adminNew') ?></span>
                </label>
                <label class="form-check" for="mode_existing">
                    <input class="form-check-input" type="radio" id="mode_existing" name="mode" value="existing"
                        <?= $mode === 'existing' ? 'checked' : '' ?> onchange="toggleUserMode()">
                    <span class="form-check-label"><?= lang('Tenant.adminExisting') ?></span>
                </label>
            </div>

            <div id="userExisting" class="hidden">
                <div class="form-group md:w-1/2">
                    <label for="user_id" class="form-label"><?= lang('Tenant.user') ?> *</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">—</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= esc($u['id'], 'attr') ?>" <?= (string) old('user_id') === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= esc(trim($u['first_name'] . ' ' . $u['last_name'])) ?> (<?= esc($u['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint"><?= lang('Tenant.addUserHint') ?></small>
                </div>
            </div>

            <div id="userNew" class="hidden">
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="first_name" class="form-label"><?= lang('Tenant.firstName') ?> *</label>
                        <input type="text" class="form-input" id="first_name" name="first_name" value="<?= esc(old('first_name'), 'attr') ?>" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="last_name" class="form-label"><?= lang('Tenant.lastName') ?> *</label>
                        <input type="text" class="form-input" id="last_name" name="last_name" value="<?= esc(old('last_name'), 'attr') ?>" maxlength="50">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="username" class="form-label"><?= lang('Tenant.username') ?> *</label>
                        <input type="text" class="form-input" id="username" name="username" value="<?= esc(old('username'), 'attr') ?>" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label"><?= lang('Tenant.email') ?> *</label>
                        <input type="email" class="form-input" id="email" name="email" value="<?= esc(old('email'), 'attr') ?>" maxlength="100">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="phone" class="form-label"><?= lang('Tenant.phone') ?></label>
                        <input type="tel" class="form-input" id="phone" name="phone" data-phone value="<?= esc(old('phone'), 'attr') ?>" maxlength="30" placeholder="300 123 4567">
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label"><?= lang('Tenant.password') ?> *</label>
                        <input type="password" class="form-input" id="password" name="password" autocomplete="new-password" minlength="8">
                        <small class="form-hint"><?= lang('Tenant.passwordHint') ?></small>
                    </div>
                </div>

                <div class="form-group md:w-1/2">
                    <label for="preferred_language" class="form-label"><?= lang('Tenant.language') ?></label>
                    <select class="form-select" id="preferred_language" name="preferred_language">
                        <option value="es" <?= old('preferred_language') === 'en' ? '' : 'selected' ?>>Español</option>
                        <option value="en" <?= old('preferred_language') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
            </div>

            <div class="form-group md:w-1/2">
                <label for="role_id" class="form-label"><?= lang('Tenant.role') ?></label>
                <select class="form-select" id="role_id" name="role_id">
                    <option value="0"><?= lang('Tenant.noRole') ?></option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= esc($r['id'], 'attr') ?>" <?= (string) old('role_id') === (string) $r['id'] ? 'selected' : '' ?>>
                            <?= esc($r['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint"><?= lang('Tenant.roleHint') ?></small>
            </div>

            <label class="form-check mb-4" for="is_admin">
                <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1" <?= old('is_admin') ? 'checked' : '' ?>>
                <span class="form-check-label"><?= lang('Tenant.isAdmin') ?></span>
            </label>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Tenant.save') ?></button>
                <a href="<?= base_url('tenant/usuarios') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> <?= lang('Tenant.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function toggleUserMode() {
        const isNew = document.getElementById('mode_new').checked;
        document.getElementById('userNew').classList.toggle('hidden', !isNew);
        document.getElementById('userExisting').classList.toggle('hidden', isNew);
    }
    toggleUserMode();
</script>
<?= $this->endSection() ?>
