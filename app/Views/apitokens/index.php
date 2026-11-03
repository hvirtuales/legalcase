<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php $newToken = session()->getFlashdata('newToken'); ?>
<?php if ($newToken): ?>
    <div class="alert alert-success">
        <i class="icon icon-check-circle"></i>
        <div class="w-full">
            <strong>Token generado.</strong> Cópialo ahora — no se volverá a mostrar.
            <div class="mt-2 flex items-stretch gap-2">
                <input type="text" readonly value="<?= esc($newToken, 'attr') ?>" id="newTokenValue"
                    class="form-input font-mono text-xs">
                <button type="button" class="btn btn-outline" onclick="copyToken()"><i class="icon icon-clipboard"></i> Copiar</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Crear token -->
    <div class="lg:col-span-1">
        <div class="card">
            <div class="card-header">
                <i class="icon icon-key"></i>
                <h6 class="font-semibold">Nuevo token</h6>
            </div>
            <div class="card-body">
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif; ?>
                <form action="<?= base_url('apitokens/create') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="name" class="form-label">Nombre / descripción *</label>
                        <input type="text" class="form-input" id="name" name="name" value="<?= old('name') ?>" required
                            placeholder="Ej: Integración ERP">
                    </div>
                    <div class="form-group">
                        <label for="company_id" class="form-label">Empresa *</label>
                        <select class="form-select" id="company_id" name="company_id" required>
                            <option value="">— Selecciona una empresa —</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= esc($c['id'], 'attr') ?>" <?= (string) old('company_id') === (string) $c['id'] ? 'selected' : '' ?>>
                                    <?= esc($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">El token operará en el contexto de esta empresa y respeta la vigencia de su plan.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Roles concedidos *</label>
                        <div class="rounded-sm border border-line p-3">
                            <?php if (empty($selectable)): ?>
                                <p class="text-sm text-muted">No tienes roles asignables.</p>
                            <?php else: ?>
                                <?php foreach ($selectable as $role): ?>
                                    <label class="form-check mb-2" for="role_<?= $role['id'] ?>">
                                        <input class="form-check-input" type="checkbox" id="role_<?= $role['id'] ?>" name="roles[]" value="<?= $role['id'] ?>">
                                        <span class="form-check-label"><?= esc($role['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <small class="form-hint">El token heredará los permisos de estos roles.</small>
                    </div>
                    <div class="form-group">
                        <label for="expires_in_days" class="form-label">Expira en (días)</label>
                        <input type="number" class="form-input" id="expires_in_days" name="expires_in_days" min="0" value="<?= old('expires_in_days', 90) ?>">
                        <small class="form-hint">0 = sin expiración.</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="icon icon-plus-circle"></i> Generar token</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2"><i class="icon icon-list-check"></i><h6 class="font-semibold">Mis tokens</h6></div>
                <a href="<?= base_url('apidocs') ?>" class="btn btn-outline btn-sm"><i class="icon icon-book"></i> Documentación</a>
            </div>
            <div class="card-body">
                <?php if (empty($tokens)): ?>
                    <p class="py-4 text-center text-muted">No has creado tokens todavía.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Empresa</th>
                                    <th>Roles</th>
                                    <th>Expira</th>
                                    <th>Último uso</th>
                                    <th class="text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tokens as $t): ?>
                                    <?php $roleIds = json_decode($t['abilities'] ?? '[]', true) ?: []; ?>
                                    <tr>
                                        <td><strong><?= esc($t['name']) ?></strong></td>
                                        <td class="text-muted"><?= esc($companiesById[$t['company_id']] ?? ('#' . ($t['company_id'] ?? '—'))) ?></td>
                                        <td class="text-muted">
                                            <?php foreach ($roleIds as $rid): ?>
                                                <span class="badge badge-light"><?= esc($rolesById[$rid] ?? ('#' . $rid)) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td class="text-muted"><?= $t['expires_at'] ? date('d/m/Y', strtotime($t['expires_at'])) : 'Nunca' ?></td>
                                        <td class="text-muted"><?= $t['last_used_at'] ? date('d/m/Y H:i', strtotime($t['last_used_at'])) : '—' ?></td>
                                        <td>
                                            <div class="flex justify-end">
                                                <?= view('partials/delete_button', [
                                                    'action'  => base_url('apitokens/revoke/' . $t['id']),
                                                    'confirm' => '¿Revocar este token? Dejará de funcionar de inmediato.',
                                                    'icon'    => 'trash',
                                                    'label'   => 'Revocar',
                                                    'text'    => 'Revocar',
                                                ]) ?>
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

<?= $this->section('scripts') ?>
<script>
    function copyToken() {
        const el = document.getElementById('newTokenValue');
        el.select(); el.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(el.value);
    }
</script>
<?= $this->endSection() ?>
