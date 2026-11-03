<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array|null $dt @var array $counts productos, vendedores y pedidos por catálogo */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-book"></i>
            <h5 class="font-semibold"><?= lang('Quo.catalogs') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('servicios/catalogo-digital/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Quo.catalogsNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.errNoCompany') ?></span></div>
        <?php else: ?>
            <p class="mb-3 text-sm text-muted"><?= lang('Quo.catalogsIntro') ?></p>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('servicios/catalogo-digital'),
                'searchPlaceholder' => lang('Quo.name') . '…',
                'emptyText'         => lang('Quo.noCatalogs'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Quo.name'), 'sortable' => true,
                        'render' => static fn ($c) => '<strong>' . esc($c['name']) . '</strong>'
                            . ($c['headline'] ? '<div class="text-xs text-muted">' . esc(mb_strimwidth((string) $c['headline'], 0, 70, '…')) . '</div>' : '')],
                    ['key' => 'price_tier', 'label' => lang('Quo.priceTier'), 'sortable' => false,
                        'render' => static fn ($c) => !empty($c['price_tier'])
                            ? '<span class="badge badge-secondary">' . esc(lang('Quo.tierN', [(int) $c['price_tier']])) . '</span>'
                            : '<span class="text-muted">' . esc(lang('Quo.tierBase')) . '</span>'],
                    ['key' => 'products', 'label' => lang('Quo.products'), 'sortable' => false,
                        'render' => static fn ($c) => (int) ($counts['products'][$c['id']] ?? 0)],
                    ['key' => 'sellers', 'label' => lang('Quo.sellers'), 'sortable' => false,
                        'render' => static fn ($c) => (int) ($counts['sellers'][$c['id']] ?? 0)],
                    ['key' => 'orders', 'label' => lang('Quo.orders'), 'sortable' => false,
                        'render' => static function ($c) use ($counts) {
                            $n = (int) ($counts['orders'][$c['id']] ?? 0);

                            return $n ? '<span class="badge badge-info">' . $n . '</span>' : '<span class="text-muted">—</span>';
                        }],
                    ['key' => 'is_published', 'label' => lang('Quo.status'), 'sortable' => true,
                        'render' => static fn ($c) => $c['is_published']
                            ? '<span class="badge badge-success">' . esc(lang('Quo.published')) . '</span>'
                            : '<span class="badge badge-secondary">' . esc(lang('Quo.draft')) . '</span>'],
                ],
                'actions' => static function ($c) {
                    $id = (int) $c['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('servicios/catalogo-digital/enlaces/' . $id) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Quo.catalogLinks'), 'attr') . '"><i class="icon icon-link"></i></a>'
                        . '<a href="' . base_url('servicios/catalogo-digital/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action'  => base_url('servicios/catalogo-digital/eliminar/' . $id),
                            'label'   => lang('Quo.delete'),
                            'confirm' => lang('Quo.confirmDeleteCatalog'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
