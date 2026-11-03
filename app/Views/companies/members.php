<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="mb-4 flex items-center gap-2">
    <a href="<?= base_url('companies') ?>" class="btn btn-outline btn-sm"><i class="icon icon-arrow-left"></i> Empresas</a>
    <h1 class="text-lg font-semibold">Miembros de <?= esc($company['name']) ?></h1>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Agregar -->
    <div class="lg:col-span-1">
        <div class="card">
            <div class="card-header"><i class="icon icon-person-plus"></i><h6 class="font-semibold">Agregar usuario</h6></div>
            <div class="card-body">
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif; ?>
                <form action="<?= base_url('companies/members/' . $company['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="user_id" class="form-label">Usuario</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">— Selecciona —</option>
                            <?php foreach ($available as $u): ?>
                                <option value="<?= esc($u['id'], 'attr') ?>">
                                    <?= esc(trim($u['first_name'] . ' ' . $u['last_name'])) ?> (<?= esc($u['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">Sólo los miembros son visibles vía API con un token de esta empresa.</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="icon icon-plus-circle"></i> Agregar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-people"></i><h6 class="font-semibold">Miembros (<?= count($members) ?>)</h6></div>
            <div class="card-body">
                <?php if (empty($members)): ?>
                    <p class="py-4 text-center text-muted">Esta empresa aún no tiene miembros.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Usuario</th><th>Email</th><th>Rol</th><th class="text-right">Acción</th></tr></thead>
                            <tbody>
                                <?php foreach ($members as $m): ?>
                                    <tr>
                                        <td><strong><?= esc(trim($m['first_name'] . ' ' . $m['last_name'])) ?></strong></td>
                                        <td class="text-muted"><?= esc($m['email']) ?></td>
                                        <td>
                                            <?php if ((int) $company['owner_id'] === (int) $m['id']): ?>
                                                <span class="badge badge-primary">Dueño</span>
                                            <?php elseif (!empty($m['is_admin'])): ?>
                                                <span class="badge badge-info">Admin</span>
                                            <?php else: ?>
                                                <span class="badge badge-light">Miembro</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex justify-end">
                                                <?php if ((int) $company['owner_id'] === (int) $m['id']): ?>
                                                    <span class="text-xs text-muted">—</span>
                                                <?php else: ?>
                                                    <?= view('partials/delete_button', [
                                                        'action'  => base_url('companies/members/' . $company['id'] . '/remove/' . $m['id']),
                                                        'confirm' => '¿Quitar a este usuario de la empresa?',
                                                        'icon'    => 'person-dash',
                                                        'label'   => 'Quitar',
                                                        'text'    => 'Quitar',
                                                    ]) ?>
                                                <?php endif; ?>
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
    </div>
</div>

<?= $this->endSection() ?>
