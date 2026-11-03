<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Catálogo de sectores.
 *
 * @var array $dt      resultado de `DataTable`
 * @var array $counts  [sector_id => cuántas empresas], para poder avisar al borrar
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-diagram-3"></i>
            <h5 class="font-semibold"><?= lang('Sector.sectors') ?></h5>
        </div>
        <a href="<?= base_url('sectors/create') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i> <?= lang('Sector.newSector') ?>
        </a>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Sector.catalogHint') ?></p>

        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('sectors'),
            'searchPlaceholder' => lang('Sector.searchPlaceholder'),
            'emptyText'         => lang('Sector.empty'),
            'columns'           => [
                ['key' => 'sort_order', 'label' => lang('Sector.fOrder'), 'sortable' => true],
                [
                    'key'      => 'name',
                    'label'    => lang('Sector.fName'),
                    'sortable' => true,
                    'render'   => static function ($s) {
                        // La muestra de color y el icono son lo que distingue un
                        // sector de otro de un vistazo, aquí y en el mapa.
                        $hex  = preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) ($s['color'] ?? '')) ? $s['color'] : '';
                        $html = '<div class="flex items-center gap-2">';
                        $html .= '<span class="inline-block h-3 w-3 shrink-0 rounded-full border border-line"'
                            . ($hex !== '' ? ' style="background-color:' . $hex . '"' : '') . '></span>';
                        if (!empty($s['icon'])) {
                            $html .= '<i class="icon ' . esc($s['icon'], 'attr') . ' text-muted"></i>';
                        }
                        $html .= '<strong>' . esc($s['name']) . '</strong>';
                        $html .= '</div>';
                        if (!empty($s['description'])) {
                            $html .= '<div class="text-xs text-muted">' . esc($s['description']) . '</div>';
                        }

                        return $html;
                    },
                ],
                [
                    'key'    => 'slug',
                    'label'  => lang('Sector.fSlug'),
                    'render' => static fn ($s) => '<code class="text-xs">' . esc($s['slug']) . '</code>',
                ],
                [
                    'key'    => 'id',
                    'label'  => lang('Sector.companiesCount'),
                    'render' => static function ($s) use ($counts) {
                        $n = $counts[(int) $s['id']] ?? 0;

                        return $n > 0
                            ? '<span class="badge badge-light">' . $n . '</span>'
                            : '<span class="text-muted">—</span>';
                    },
                ],
                [
                    'key'      => 'is_active',
                    'label'    => lang('Sector.fStatus'),
                    'sortable' => true,
                    'render'   => static fn ($s) => !empty($s['is_active'])
                        ? '<span class="badge badge-success">' . lang('Sector.active') . '</span>'
                        : '<span class="badge badge-secondary">' . lang('Sector.inactive') . '</span>',
                ],
            ],
            'actions'           => static function ($s) use ($counts) {
                $n = $counts[(int) $s['id']] ?? 0;
                // Borrar NO borra empresas: las desclasifica. Se dice cuántas,
                // que es lo único que el usuario no puede saber desde aquí.
                $confirm = $n > 0
                    ? lang('Sector.confirmDeleteWith', [$n])
                    : lang('Sector.confirmDelete');

                $html = '<div class="flex justify-end gap-1">';
                $html .= '<a href="' . base_url('sectors/edit/' . $s['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Sector.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                $html .= view('partials/delete_button', [
                    'action'  => base_url('sectors/delete/' . $s['id']),
                    'confirm' => $confirm,
                ]);

                return $html . '</div>';
            },
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
