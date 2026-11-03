<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $meta = require __DIR__ . '/../_meta.php'; ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-box-seam"></i>
            <h5 class="font-semibold"><?= lang('Quo.products') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex gap-2">
                <a href="<?= base_url('servicios/categorias') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-list"></i> <?= lang('Quo.categories') ?>
                </a>
                <a href="<?= base_url('servicios/productos/importar') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-upload"></i> <?= lang('Quo.import') ?>
                </a>
                <a href="<?= base_url('servicios/productos/nuevo') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> <?= lang('Quo.productsNew') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.errNoCompany') ?></span></div>
        <?php else: ?>
            <!-- Buscador y filtros en UNA sola fila (el DataTable no pinta el
                 suyo: `showSearch => false`), para no gastar dos renglones. -->
            <form method="get" action="<?= base_url('servicios/productos') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Quo.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Quo.code') . ', ' . lang('Quo.name') . '…', 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="f-categoria" class="form-label text-xs"><?= lang('Quo.filterCategory') ?></label>
                    <select id="f-categoria" name="categoria" class="form-select">
                        <option value=""><?= lang('Quo.all') ?></option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?= (int) $id ?>" <?= (string) $categoryId === (string) $id ? 'selected' : '' ?>>
                                <?= esc($name) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="0" <?= $categoryId === 0 ? 'selected' : '' ?>><?= lang('Quo.uncategorized') ?></option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="f-estado" class="form-label text-xs"><?= lang('Quo.status') ?></label>
                    <select id="f-estado" name="estado" class="form-select">
                        <option value=""><?= lang('Quo.all') ?></option>
                        <option value="1" <?= $active === 1 ? 'selected' : '' ?>><?= lang('Quo.active') ?></option>
                        <option value="0" <?= $active === 0 ? 'selected' : '' ?>><?= lang('Quo.inactive') ?></option>
                    </select>
                </div>
                <?php if (!empty($dt['sort'])): ?>
                    <input type="hidden" name="sort" value="<?= esc($dt['sort'], 'attr') ?>">
                    <input type="hidden" name="dir" value="<?= esc($dt['dir'] ?? 'asc', 'attr') ?>">
                <?php endif; ?>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Quo.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <?php if ($categoryId !== null || $active !== null || ($dt['q'] ?? '') !== ''): ?>
                            <a href="<?= base_url('servicios/productos') ?>" class="btn btn-ghost" title="<?= esc(lang('Quo.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'showSearch'        => false,
                'baseUrl'           => base_url('servicios/productos'),
                'extraParams'       => [
                    'categoria' => $categoryId === null ? '' : (string) $categoryId,
                    'estado'    => $active === null ? '' : (string) $active,
                ],
                'searchPlaceholder' => lang('Quo.code') . ', ' . lang('Quo.name') . '…',
                'emptyText'         => lang('Quo.noProducts'),
                'columns'           => [
                    ['key' => 'code', 'label' => lang('Quo.code'), 'sortable' => true,
                        'render' => static fn ($p) => '<span class="font-mono text-sm">' . esc($p['code']) . '</span>'],
                    ['key' => 'name', 'label' => lang('Quo.name'), 'sortable' => true,
                        'render' => static fn ($p) => '<strong>' . esc($p['name']) . '</strong>'
                            . ($p['description'] ? '<div class="text-xs text-muted">' . esc(mb_strimwidth((string) $p['description'], 0, 80, '…')) . '</div>' : '')],
                    ['key' => 'category_id', 'label' => lang('Quo.category'), 'sortable' => false,
                        'render' => static fn ($p) => !empty($p['category_id']) && isset($categories[$p['category_id']])
                            ? '<span class="badge badge-secondary">' . esc($categories[$p['category_id']]) . '</span>'
                            : '<span class="text-muted">—</span>'],
                    ['key' => 'unit_price', 'label' => lang('Quo.unitPrice'), 'sortable' => true,
                        'render' => static fn ($p) => $meta['money']($p['unit_price'])],
                    ['key' => 'discount_value', 'label' => lang('Quo.discountValue'), 'sortable' => false,
                        'render' => static fn ($p) => (float) $p['discount_value'] > 0
                            ? esc(rtrim(rtrim(number_format((float) $p['discount_value'], 2, ',', '.'), '0'), ',') . ' ' . $p['discount_type'])
                            : '—'],
                    ['key' => 'tax_percent', 'label' => lang('Quo.taxPercent'), 'sortable' => true,
                        'render' => static fn ($p) => rtrim(rtrim(number_format((float) $p['tax_percent'], 2, ',', '.'), '0'), ',') . '%'],
                    ['key' => 'is_active', 'label' => lang('Quo.status'), 'sortable' => false,
                        'render' => static fn ($p) => $p['is_active']
                            ? '<span class="badge badge-success">' . esc(lang('Quo.active')) . '</span>'
                            : '<span class="badge badge-secondary">' . esc(lang('Quo.inactive')) . '</span>'],
                ],
                'actions' => static function ($p) {
                    $id = (int) $p['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('servicios/productos/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action' => base_url('servicios/productos/eliminar/' . $id),
                            'label'  => lang('Quo.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
