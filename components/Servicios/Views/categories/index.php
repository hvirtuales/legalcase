<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Categorías del catálogo.
 *
 * @var array|null $dt     @var array $counts productos por categoría
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-list"></i>
            <h5 class="font-semibold"><?= lang('Quo.categories') ?></h5>
        </div>
        <div class="flex gap-2">
            <a href="<?= base_url('servicios/productos') ?>" class="btn btn-secondary btn-sm">
                <i class="icon icon-box-seam"></i> <?= lang('Quo.products') ?>
            </a>
            <?php if ($dt !== null): ?>
                <a href="<?= base_url('servicios/categorias/nuevo') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> <?= lang('Quo.categoriesNew') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.errNoCompany') ?></span></div>
        <?php else: ?>
            <p class="mb-3 text-sm text-muted"><?= lang('Quo.categoriesIntro') ?></p>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('servicios/categorias'),
                'searchPlaceholder' => lang('Quo.name') . '…',
                'emptyText'         => lang('Quo.noCategories'),
                'columns'           => [
                    ['key' => 'sort_order', 'label' => lang('Quo.sortOrder'), 'sortable' => true,
                        'render' => static fn ($c) => '<span class="text-muted">' . (int) $c['sort_order'] . '</span>'],
                    ['key' => 'name', 'label' => lang('Quo.name'), 'sortable' => true,
                        'render' => static fn ($c) => '<strong>' . esc($c['name']) . '</strong>'
                            . ($c['description'] ? '<div class="text-xs text-muted">' . esc($c['description']) . '</div>' : '')],
                    ['key' => 'products', 'label' => lang('Quo.products'), 'sortable' => false,
                        'render' => static function ($c) use ($counts) {
                            $n = (int) ($counts[$c['id']] ?? 0);

                            return $n === 0
                                ? '<span class="text-muted">—</span>'
                                : '<a class="badge badge-info" href="' . base_url('servicios/productos?categoria=' . (int) $c['id']) . '">'
                                    . $n . '</a>';
                        }],
                    ['key' => 'is_active', 'label' => lang('Quo.isActive'), 'sortable' => false,
                        'render' => static fn ($c) => $c['is_active']
                            ? '<span class="badge badge-success">' . esc(lang('Quo.active')) . '</span>'
                            : '<span class="badge badge-secondary">' . esc(lang('Quo.inactive')) . '</span>'],
                ],
                'actions' => static function ($c) use ($counts) {
                    $id = (int) $c['id'];
                    $n  = (int) ($counts[$id] ?? 0);

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('servicios/categorias/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action'  => base_url('servicios/categorias/eliminar/' . $id),
                            'label'   => lang('Quo.delete'),
                            // Se avisa qué pasa con los productos que la usan.
                            'confirm' => $n > 0 ? lang('Quo.confirmDeleteCategoryUsed', [$n]) : lang('Quo.confirmDeleteCategory'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
