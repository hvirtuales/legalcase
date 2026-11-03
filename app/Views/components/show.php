<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="mb-4">
    <a href="<?= base_url('components') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-chevron-left"></i> Volver</a>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2"><i class="icon <?= esc($m['icon'] ?? 'icon-puzzle') ?>"></i><h5 class="font-semibold"><?= esc($m['name'] ?? '') ?></h5></div>
                <span class="badge badge-light font-mono"><?= esc($m['code'] ?? '') ?></span>
            </div>
            <div class="card-body">
                <p class="mb-3 text-sm text-muted"><?= esc($m['description'] ?? '') ?></p>
                <table class="table">
                    <tbody class="text-sm">
                        <tr><td class="text-muted">Versión</td><td class="font-mono"><?= esc($m['version'] ?? '?') ?></td></tr>
                        <tr><td class="text-muted">Autor</td><td><?= esc($m['author'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">Núcleo requerido</td><td class="font-mono"><?= esc($m['requires_core'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">Prefijo de tablas</td><td class="font-mono"><?= esc($m['table_prefix'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">Dependencias</td><td><?= $m['depends'] ? esc(implode(', ', $m['depends'])) : '—' ?></td></tr>
                        <tr><td class="text-muted">Rutas</td><td class="font-mono"><?= !empty($m['routes']) ? esc(implode(', ', $m['routes'])) : '—' ?></td></tr>
                        <tr><td class="text-muted">Namespace</td><td class="font-mono"><?= esc($m['_namespace']) ?></td></tr>
                    </tbody>
                </table>

                <?php if (!empty($m['permissions'])): ?>
                    <h6 class="mb-1 mt-3 font-semibold text-muted">Permisos</h6>
                    <ul class="list-inside list-disc text-sm">
                        <?php foreach ($m['permissions'] as $name => $desc): ?><li><span class="font-mono"><?= esc($name) ?></span> — <?= esc($desc) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($m['menus'])): ?>
                    <h6 class="mb-1 mt-3 font-semibold text-muted">Menús</h6>
                    <ul class="list-inside list-disc text-sm">
                        <?php foreach ($m['menus'] as $mi): ?><li><?= esc($mi['label'] ?? '') ?> → <span class="font-mono"><?= esc($mi['route'] ?? '') ?></span></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><i class="icon icon-gear"></i><h6 class="font-semibold">Estado</h6></div>
            <div class="card-body text-sm">
                <?php $st = $status ?? ['status' => 'not_installed', 'installed' => false, 'active' => false]; ?>
                <p class="mb-2">
                    <?php if ($st['status'] === 'active'): ?><span class="badge badge-success">Activo</span>
                    <?php elseif ($st['status'] === 'inactive'): ?><span class="badge badge-warning">Inactivo</span>
                    <?php else: ?><span class="badge badge-secondary">No instalado</span><?php endif; ?>
                </p>
                <?php if (!empty($st['installed_at'])): ?><p class="text-xs text-muted">Instalado: <?= esc($st['installed_at']) ?></p><?php endif; ?>

                <?php if ($st['installed']): ?>
                    <div class="mt-4 border-t border-line pt-3">
                        <h6 class="mb-2 font-semibold text-muted">Reinstalar</h6>
                        <p class="mb-2 text-xs text-muted">Vuelve a desplegar las migraciones y los permisos del componente. Útil si la base de datos se recreó y sus tablas ya no existen. No borra datos.</p>
                        <form action="<?= base_url('components/instalar') ?>" method="post" onsubmit="return confirm('Se volverán a desplegar las migraciones y los permisos del componente. ¿Continuar?')">
                            <?= csrf_field() ?><input type="hidden" name="folder" value="<?= esc($m['_folder'], 'attr') ?>">
                            <button class="btn btn-secondary btn-sm w-full"><i class="icon icon-arrow-counterclockwise"></i> Reinstalar (redesplegar migraciones)</button>
                        </form>
                    </div>

                    <div class="mt-4 border-t border-line pt-3">
                        <h6 class="mb-2 font-semibold text-danger">Desinstalar</h6>
                        <form action="<?= base_url('components/desinstalar') ?>" method="post" class="mb-2" onsubmit="return confirm('¿Desinstalar conservando los datos?')">
                            <?= csrf_field() ?><input type="hidden" name="folder" value="<?= esc($m['_folder'], 'attr') ?>">
                            <button class="btn btn-secondary btn-sm w-full"><i class="icon icon-x-circle"></i> Desinstalar (conservar datos)</button>
                        </form>
                        <form action="<?= base_url('components/desinstalar') ?>" method="post" class="mb-2" onsubmit="return confirm('¡ATENCIÓN! Se ELIMINARÁN las tablas y TODOS los datos del componente. Esta acción no se puede deshacer. ¿Continuar?')">
                            <?= csrf_field() ?><input type="hidden" name="folder" value="<?= esc($m['_folder'], 'attr') ?>"><input type="hidden" name="drop_data" value="1">
                            <button class="btn btn-danger btn-sm w-full"><i class="icon icon-trash"></i> Desinstalar y eliminar datos</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="mt-4 border-t border-line pt-3">
                    <h6 class="mb-2 font-semibold text-danger">Eliminar por completo</h6>
                    <p class="mb-2 text-xs text-muted">Borra los datos <strong>y la carpeta</strong> <code>components/<?= esc($m['_folder']) ?></code> del servidor. No queda huella: el componente desaparece del panel y habría que volver a subir sus archivos para recuperarlo.</p>
                    <form action="<?= base_url('components/desinstalar') ?>" method="post" onsubmit="return confirm('¡ATENCIÓN! Se eliminarán las tablas, TODOS los datos Y los archivos del componente (components/<?= esc($m['_folder'], 'js') ?>). Esta acción no se puede deshacer. ¿Continuar?')">
                        <?= csrf_field() ?><input type="hidden" name="folder" value="<?= esc($m['_folder'], 'attr') ?>"><input type="hidden" name="remove_files" value="1">
                        <button class="btn btn-danger btn-sm w-full"><i class="icon icon-trash"></i> Eliminar datos y archivos</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
