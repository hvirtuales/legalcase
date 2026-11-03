<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <i class="icon icon-key"></i>
        <h5 class="font-semibold">Gestionar Permisos del Rol: <?= esc($role['name']) ?></h5>
    </div>
    <div class="card-body">
        <form action="<?= base_url('roles/permissions/' . $role['id']) ?>" method="post">
            <?= csrf_field() ?>

            <?php if (empty($allPermissions)): ?>
                <p class="text-muted">No hay permisos disponibles</p>
            <?php else: ?>
                <?php foreach ($allPermissions as $module => $permissions): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <i class="icon icon-folder"></i>
                            <h6 class="font-semibold uppercase"><?= esc(ucfirst($module)) ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <?php foreach ($permissions as $permission): ?>
                                    <label class="form-check" for="perm_<?= $permission['id'] ?>">
                                        <input class="form-check-input" type="checkbox" id="perm_<?= $permission['id'] ?>"
                                            name="permissions[]" value="<?= $permission['id'] ?>" <?php
                                              if (isset($role['permissions'])) {
                                                  foreach ($role['permissions'] as $rolePerm) {
                                                      if ($rolePerm['id'] == $permission['id']) {
                                                          echo 'checked';
                                                          break;
                                                      }
                                                  }
                                              }
                                              ?>>
                                        <span class="form-check-label">
                                            <strong><?= esc($permission['name']) ?></strong>
                                            <?php if ($permission['description']): ?>
                                                <small class="block text-muted"><?= esc($permission['description']) ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="mt-3 flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-save"></i>
                    Guardar Permisos
                </button>
                <a href="<?= base_url('roles') ?>" class="btn btn-secondary">
                    <i class="icon icon-x-circle"></i>
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
