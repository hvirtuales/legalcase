<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <a href="<?= base_url('mapas') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Map.back') ?></a>
            <h5 class="font-semibold"><?= lang('Map.categories') ?></h5>
        </div>
        <?php if ($rows !== null): ?>
            <a href="<?= base_url('mapas/categorias/crear') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Map.newCategory') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($rows === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Map.pickCompany') ?></span></div>
        <?php elseif (!$rows): ?>
            <p class="text-sm text-muted"><?= lang('Map.noCategories') ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Map.category') ?></th>
                            <th><?= lang('Map.points') ?></th>
                            <th><?= lang('Map.status') ?></th>
                            <th class="text-right"><?= lang('Map.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <span class="inline-flex items-center gap-2">
                                        <!-- La muestra del color es el dato, no un adorno: es exactamente
                                             lo que se va a ver en el plano. El hex va CRUDO tras validarlo,
                                             porque esc(…, 'css') produce un color inválido. -->
                                        <i style="width:.85rem;height:.85rem;border-radius:50%;display:inline-block;background:<?= \Components\Mapas\Libraries\MapService::color($r['color']) ?>"></i>
                                        <strong><?= esc($r['name']) ?></strong>
                                    </span>
                                </td>
                                <td><span class="badge badge-secondary"><?= (int) ($counts[$r['id']] ?? 0) ?></span></td>
                                <td>
                                    <?php if ($r['is_active']): ?>
                                        <span class="badge badge-success"><?= lang('Map.active') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= lang('Map.inactive') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="<?= base_url('mapas/categorias/editar/' . $r['id']) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>
                                        <?= view('partials/delete_button', [
                                            'action'  => base_url('mapas/categorias/eliminar/' . $r['id']),
                                            // Se avisa de cuántos puntos se quedan sin clasificar:
                                            // borrar una categoría NO borra sus puntos.
                                            'confirm' => lang('Map.mCategoryConfirm', [(int) ($counts[$r['id']] ?? 0)]),
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
<?= $this->endSection() ?>
