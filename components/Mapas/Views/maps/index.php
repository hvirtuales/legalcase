<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-geo-alt"></i><h5 class="font-semibold"><?= lang('Map.maps') ?></h5></div>
        <?php if ($dt !== null): ?>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('mapas/categorias') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-palette"></i> <?= lang('Map.categories') ?></a>
                <?php if (($serviceEnabled ?? true) && (($left ?? null) === null || $left > 0)): ?>
                    <a href="<?= base_url('mapas/crear') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Map.newMap') ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Map.pickCompany') ?></span></div>
        <?php else: ?>
            <?php if (!($serviceEnabled ?? true)): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Map.planOff') ?></span></div>
            <?php elseif (($left ?? null) !== null): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Map.quotaLeft', [(int) $left]) ?></span></div>
            <?php endif; ?>

            <?php
                // Una copia consume cupo igual que un alta, así que el botón
                // aparece con la misma condición que «Nuevo mapa». Un closure no
                // hereda el ámbito de la vista: se resuelve aquí y se pasa por
                // `use`, o dentro valdría siempre `true` por el `??`.
                $puedeCopiar = ($serviceEnabled ?? true) && (($left ?? null) === null || $left > 0);
            ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('mapas'),
                'searchPlaceholder' => lang('Map.searchMap'),
                'emptyText'         => lang('Map.noMaps'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Map.name'), 'sortable' => true,
                        'render' => fn ($m) => '<a href="' . base_url('mapas/editor/' . $m['id']) . '" class="text-primary font-semibold">' . esc($m['name']) . '</a>'
                            . ($m['description'] ? '<div class="text-xs text-muted">' . esc($m['description']) . '</div>' : '')],
                    ['key' => 'levels', 'label' => lang('Map.levels'), 'sortable' => false,
                        'render' => fn ($m) => '<span class="badge badge-secondary">' . (int) ($counts[$m['id']]['levels'] ?? 0) . '</span>'],
                    ['key' => 'points', 'label' => lang('Map.points'), 'sortable' => false,
                        'render' => fn ($m) => '<span class="badge badge-secondary">' . (int) ($counts[$m['id']]['points'] ?? 0) . '</span>'],
                    ['key' => 'is_published', 'label' => lang('Map.status'), 'sortable' => true,
                        'render' => fn ($m) => $m['is_published']
                            ? '<span class="badge badge-success">' . lang('Map.published') . '</span>'
                            : '<span class="badge badge-secondary">' . lang('Map.draft') . '</span>'],
                ],
                'actions'           => function ($m) use ($puedeCopiar) {
                    $h = '<div class="flex justify-end gap-1">';
                    $h .= '<a href="' . base_url('mapas/editor/' . $m['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Map.openEditor'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                    if ($m['is_published'] && $m['public_token']) {
                        $h .= '<a href="' . base_url('mapa/' . $m['public_token']) . '" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="' . esc(lang('Map.openPublic'), 'attr') . '"><i class="icon icon-box-arrow-up-right"></i></a>';
                    }
                    if ($puedeCopiar) {
                        // El partial no es solo para borrar: lo que aporta es
                        // POST + CSRF + confirmación, que es justo lo que pide
                        // una acción que crea un mapa entero.
                        $h .= view('partials/delete_button', [
                            'action'  => base_url('mapas/duplicar/' . $m['id']),
                            'confirm' => lang('Map.mCopyConfirm', [$m['name']]),
                            'icon'    => 'clipboard',
                            'label'   => lang('Map.duplicate'),
                            'class'   => 'btn btn-secondary btn-sm',
                        ]);
                    }
                    $h .= view('partials/delete_button', [
                        'action' => base_url('mapas/eliminar/' . $m['id']),
                        'confirm' => lang('Map.mDeleteConfirm'),
                    ]);

                    return $h . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
