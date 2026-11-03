<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$badge = [
    'not_installed' => '<span class="badge badge-secondary">No instalado</span>',
    'active'        => '<span class="badge badge-success">Activo</span>',
    'inactive'      => '<span class="badge badge-warning">Inactivo</span>',
];
$act = static function (string $route, string $folder, string $label, string $cls = 'btn-secondary', string $icon = '', ?string $confirm = null): string {
    $on = $confirm ? ' onsubmit="return confirm(\'' . esc($confirm, 'js') . '\')"' : '';

    return '<form action="' . base_url('components/' . $route) . '" method="post" class="inline"' . $on . '>'
        . csrf_field() . '<input type="hidden" name="folder" value="' . esc($folder, 'attr') . '">'
        . '<button class="btn ' . $cls . ' btn-sm">' . ($icon ? '<i class="icon ' . $icon . '"></i> ' : '') . esc($label) . '</button></form>';
};
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-puzzle"></i>
        <h5 class="font-semibold">Componentes</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-4 text-sm"><i class="icon icon-info-circle"></i>
            <span>Los componentes son módulos que se agregan soltando su carpeta en <code>components/</code>. Aquí gestionas su ciclo de vida (instalar migraciones y permisos, activar/desactivar, actualizar, desinstalar). Ver el <a href="#" class="text-primary">contrato de desarrollo</a>.</span></div>

        <?php if (empty($components)): ?>
            <p class="py-6 text-center text-muted">No hay componentes en <code>components/</code>.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Componente</th><th>Versión</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($components as $c): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('components/ver/' . $c['_folder']) ?>" class="font-semibold text-primary"><?= esc($c['name'] ?? $c['_folder']) ?></a>
                                    <span class="badge badge-light ml-1 font-mono"><?= esc($c['code'] ?? $c['_folder']) ?></span>
                                    <div class="text-xs text-muted"><?= esc($c['description'] ?? '') ?></div>
                                </td>
                                <td class="font-mono text-sm">
                                    <?= esc($c['version'] ?? '?') ?>
                                    <?php if ($c['installed'] && $c['installed_version'] && $c['installed_version'] !== ($c['version'] ?? null)): ?>
                                        <div class="text-xs text-muted">instalada: <?= esc($c['installed_version']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $badge[$c['status']] ?? esc($c['status']) ?></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <?php if (!$c['installed']): ?>
                                            <?= $act('instalar', $c['_folder'], 'Instalar', 'btn-primary', 'icon-download') ?>
                                        <?php else: ?>
                                            <?php if ($c['updatable']): ?>
                                                <?= $act('actualizar', $c['_folder'], 'Actualizar', 'btn-primary', 'icon-arrow-repeat') ?>
                                            <?php endif; ?>
                                            <?php if ($c['active']): ?>
                                                <?= $act('desactivar', $c['_folder'], 'Desactivar', 'btn-secondary', 'icon-x-circle') ?>
                                            <?php else: ?>
                                                <?= $act('activar', $c['_folder'], 'Activar', 'btn-success', 'icon-check-circle') ?>
                                            <?php endif; ?>
                                            <?= $act('instalar', $c['_folder'], 'Reinstalar', 'btn-secondary', 'icon-arrow-counterclockwise', 'Se volverán a desplegar las migraciones y los permisos del componente (no borra datos). ¿Continuar?') ?>
                                            <?= $act('desinstalar', $c['_folder'], 'Desinstalar', 'btn-danger', 'icon-x-circle', '¿Desinstalar conservando los datos? Para eliminar también las tablas, usa el detalle del componente.') ?>
                                        <?php endif; ?>
                                        <a href="<?= base_url('components/ver/' . $c['_folder']) ?>" class="btn btn-ghost btn-sm" title="Detalle"><i class="icon icon-eye"></i></a>
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
