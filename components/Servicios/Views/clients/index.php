<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-people"></i>
            <h5 class="font-semibold"><?= lang('Quo.clients') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('servicios/clientes/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Quo.clientsNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.errNoCompany') ?></span></div>
        <?php else: ?>
            <!-- Buscador y filtros en UNA sola fila (el DataTable no pinta el
                 suyo: `showSearch => false`), para no gastar dos renglones. -->
            <form method="get" action="<?= base_url('servicios/clientes') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Quo.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Quo.businessName') . ', ' . lang('Quo.nit') . '…', 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="f-valor" class="form-label text-xs"><?= lang('Quo.priceTier') ?></label>
                    <select id="f-valor" name="valor" class="form-select">
                        <option value=""><?= lang('Quo.all') ?></option>
                        <?php foreach (\Components\Servicios\Models\ProductPriceModel::TIERS as $t): ?>
                            <option value="<?= $t ?>" <?= $tier === $t ? 'selected' : '' ?>><?= lang('Quo.tierN', [$t]) ?></option>
                        <?php endforeach; ?>
                        <option value="0" <?= $tier === 0 ? 'selected' : '' ?>><?= lang('Quo.tierBase') ?></option>
                    </select>
                </div>
                <?php if ($cities): ?>
                    <div class="form-group mb-0">
                        <label for="f-ciudad" class="form-label text-xs"><?= lang('Quo.city') ?></label>
                        <select id="f-ciudad" name="ciudad" class="form-select">
                            <option value=""><?= lang('Quo.all') ?></option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= esc($c, 'attr') ?>" <?= $city === $c ? 'selected' : '' ?>><?= esc($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if (!empty($dt['sort'])): ?>
                    <input type="hidden" name="sort" value="<?= esc($dt['sort'], 'attr') ?>">
                    <input type="hidden" name="dir" value="<?= esc($dt['dir'] ?? 'asc', 'attr') ?>">
                <?php endif; ?>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Quo.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <?php if ($tier !== null || $city !== '' || ($dt['q'] ?? '') !== ''): ?>
                            <a href="<?= base_url('servicios/clientes') ?>" class="btn btn-ghost" title="<?= esc(lang('Quo.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'showSearch'        => false,
                'baseUrl'           => base_url('servicios/clientes'),
                'extraParams'       => [
                    'valor'  => $tier === null ? '' : (string) $tier,
                    'ciudad' => $city,
                ],
                'searchPlaceholder' => lang('Quo.businessName') . ', ' . lang('Quo.nit') . '…',
                'emptyText'         => lang('Quo.noClients'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Quo.businessName'), 'sortable' => true,
                        'render' => static fn ($c) => '<strong>' . esc($c['name']) . '</strong>'
                            . ($c['contact_name'] ? '<div class="text-xs text-muted">' . esc($c['contact_name']) . '</div>' : '')],
                    ['key' => 'nit', 'label' => lang('Quo.nit'), 'sortable' => true,
                        'render' => static fn ($c) => esc($c['nit'] ?: '—')],
                    ['key' => 'price_tier', 'label' => lang('Quo.priceTier'), 'sortable' => false,
                        'render' => static fn ($c) => !empty($c['price_tier'])
                            ? '<span class="badge badge-info">' . esc(lang('Quo.tierN', [(int) $c['price_tier']])) . '</span>'
                            : '<span class="text-muted">' . esc(lang('Quo.tierBase')) . '</span>'],
                    ['key' => 'city', 'label' => lang('Quo.city'), 'sortable' => true,
                        'render' => static fn ($c) => esc($c['city'] ?: '—')],
                    ['key' => 'phone', 'label' => lang('Quo.phone'), 'sortable' => false,
                        'render' => static fn ($c) => esc($c['phone'] ?: '—')],
                    ['key' => 'email', 'label' => lang('Quo.email'), 'sortable' => false,
                        'render' => static fn ($c) => esc($c['email'] ?: '—')],
                ],
                'actions' => static function ($c) {
                    $id = (int) $c['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('servicios/clientes/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action' => base_url('servicios/clientes/eliminar/' . $id),
                            'label'  => lang('Quo.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
