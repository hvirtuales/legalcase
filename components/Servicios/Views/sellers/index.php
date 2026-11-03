<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-person-badge"></i>
            <h5 class="font-semibold"><?= lang('Quo.sellers') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('servicios/vendedores/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Quo.sellersNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.errNoCompany') ?></span></div>
        <?php else: ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('servicios/vendedores'),
                'searchPlaceholder' => lang('Quo.name') . ', ' . lang('Quo.email') . '…',
                'emptyText'         => lang('Quo.noSellers'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Quo.name'), 'sortable' => true,
                        'render' => static function ($s) {
                            $avatar = !empty($s['photo'])
                                ? '<img src="' . media_url($s['photo']) . '" alt="" class="size-8 shrink-0 rounded-full border border-line object-cover">'
                                : '<span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-muted"><i class="icon icon-person"></i></span>';

                            return '<div class="flex items-center gap-2">' . $avatar
                                . '<span><strong>' . esc($s['name']) . '</strong>'
                                . ($s['position'] ? '<div class="text-xs text-muted">' . esc($s['position']) . '</div>' : '')
                                . '</span></div>';
                        }],
                    ['key' => 'signature', 'label' => lang('Quo.sellerSignature'), 'sortable' => false,
                        'render' => static fn ($s) => !empty($s['signature'])
                            ? '<img src="' . media_url($s['signature']) . '" alt="" class="max-h-8 rounded bg-canvas p-0.5">'
                            : '<span class="text-muted">—</span>'],
                    ['key' => 'email', 'label' => lang('Quo.email'), 'sortable' => true,
                        'render' => static fn ($s) => esc($s['email'] ?: '—')],
                    ['key' => 'phone', 'label' => lang('Quo.phone'), 'sortable' => false,
                        'render' => static fn ($s) => esc($s['phone'] ?: '—')],
                    ['key' => 'status', 'label' => lang('Quo.sellerStatus'), 'sortable' => true,
                        'render' => static fn ($s) => $s['status'] === 'activo'
                            ? '<span class="badge badge-success">' . esc(lang('Quo.active')) . '</span>'
                            : '<span class="badge badge-secondary">' . esc(lang('Quo.inactive')) . '</span>'],
                ],
                'actions' => static function ($s) {
                    $id = (int) $s['id'];
                    // La tarjeta pública solo se ofrece si el vendedor está activo:
                    // el enlace de un inactivo devuelve 404 a propósito.
                    $card = '';
                    if ($s['status'] === 'activo') {
                        $url  = (new \Components\Servicios\Libraries\SellerCardService())->publicUrl($s);
                        $card = '<a href="' . esc($url, 'attr') . '" target="_blank" rel="noopener"'
                            . ' class="btn btn-secondary btn-sm" title="' . esc(lang('Quo.cardOpen'), 'attr') . '">'
                            . '<i class="icon icon-person-badge"></i></a>';
                    }

                    return '<div class="flex justify-end gap-1">'
                        . $card
                        . '<a href="' . base_url('servicios/vendedores/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action' => base_url('servicios/vendedores/eliminar/' . $id),
                            'label'  => lang('Quo.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
