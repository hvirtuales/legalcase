<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <i class="icon icon-pencil-square"></i>
        <h5 class="font-semibold">Editar Rol: <?= esc($role['name']) ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('roles/edit/' . $role['id']) ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Nombre del Rol *</label>
                <input type="text" class="form-input" id="name" name="name" value="<?= old('name', $role['name']) ?>"
                    required minlength="3" maxlength="50">
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Descripción</label>
                <textarea class="form-textarea" id="description" name="description" rows="3"
                    maxlength="500"><?= old('description', $role['description']) ?></textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-save"></i>
                    Actualizar Rol
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
