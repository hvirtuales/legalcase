<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Bandeja de mensajes recibidos por el bloque «formulario de contacto».
 *
 * @var array|null $dt
 * @var array      $pages  id => página de origen (mapa, sin consulta por fila)
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-envelope"></i>
            <h5 class="font-semibold"><?= lang('Pag.messages') ?></h5>
            <?php if (!empty($unread)): ?>
                <span class="badge badge-danger"><?= lang('Pag.unreadCount', [(int) $unread]) ?></span>
            <?php endif; ?>
        </div>
        <a href="<?= base_url('paginas') ?>" class="btn btn-ghost btn-sm">
            <i class="icon icon-arrow-left"></i> <?= lang('Pag.back') ?>
        </a>
    </div>

    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Pag.noCompanyHint') ?></span></div>
        <?php else: ?>
            <div class="mb-4 flex flex-wrap gap-1 border-b border-line">
                <?php
                $tabs = ['' => lang('Pag.all'), 'nuevos' => lang('Pag.onlyUnread')];
                $cur  = !empty($unreadOnly) ? 'nuevos' : '';
                ?>
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?= base_url('paginas/mensajes') . ($key ? '?estado=' . $key : '') ?>"
                        class="border-b-2 px-3 py-2 text-sm <?= $cur === $key ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">
                        <?= esc($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('paginas/mensajes'),
                'extraParams'       => $extraParams ?? [],
                'searchPlaceholder' => lang('Pag.searchMessages'),
                'emptyText'         => lang('Pag.noMessages'),
                'columns'           => [
                    ['key' => 'created_at', 'label' => lang('Pag.receivedCol'), 'sortable' => true,
                        'render' => static fn ($m) => '<a href="' . base_url('paginas/mensajes/' . (int) $m['id']) . '" class="'
                            . ((int) $m['is_read'] === 0 ? 'font-semibold text-primary' : 'text-ink') . '">'
                            . esc(substr((string) $m['created_at'], 0, 16)) . '</a>'
                            . ((int) $m['is_read'] === 0 ? ' <span class="badge badge-danger">' . esc(lang('Pag.new')) . '</span>' : '')],

                    ['key' => 'form_title', 'label' => lang('Pag.formCol'), 'sortable' => true,
                        'render' => static fn ($m) => esc($m['form_title'] ?: lang('Pag.formNoTitle'))],

                    ['key' => 'page_id', 'label' => lang('Pag.pageCol'), 'sortable' => false,
                        'render' => static function ($m) use ($pages) {
                            $p = $pages[(int) $m['page_id']] ?? null;

                            return $p
                                ? '<a href="' . base_url('paginas/editor/' . (int) $p['id']) . '" class="text-primary">' . esc($p['title']) . '</a>'
                                : '<span class="text-muted">—</span>';
                        }],

                    ['key' => 'data', 'label' => lang('Pag.summaryCol'), 'sortable' => false,
                        'render' => static function ($m) {
                            $data = is_array($m['data'] ?? null) ? $m['data'] : [];
                            $bits = [];

                            foreach (array_slice($data, 0, 2, true) as $k => $v) {
                                if ($v !== '') {
                                    $bits[] = esc($k) . ': ' . esc(mb_substr((string) $v, 0, 40));
                                }
                            }

                            return $bits
                                ? '<span class="text-xs text-muted">' . implode(' · ', $bits) . '</span>'
                                : '<span class="text-muted">—</span>';
                        }],
                ],
                'actions' => static function ($m) use ($pages) {
                    $id  = (int) $m['id'];
                    $out = '<div class="flex flex-wrap justify-end gap-1">';
                    $out .= '<a href="' . base_url('paginas/mensajes/' . $id) . '" class="btn btn-secondary btn-sm"'
                        . ' title="' . esc(lang('Pag.open'), 'attr') . '"><i class="icon icon-eye"></i></a>';

                    // Editar la página de la que vino el mensaje. Estaba solo en
                    // el nombre de la columna «Página», que no se lee como una
                    // acción; aquí va donde el usuario busca las acciones.
                    $p = $pages[(int) $m['page_id']] ?? null;
                    if ($p) {
                        $out .= '<a href="' . base_url('paginas/editor/' . (int) $p['id']) . '" class="btn btn-primary btn-sm"'
                            . ' title="' . esc(lang('Pag.editPage'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                    }
                    $out .= view('partials/delete_button', [
                        'action'  => base_url('paginas/mensajes/eliminar/' . $id),
                        'confirm' => lang('Pag.confirmDeleteMessage'),
                        'label'   => lang('Pag.delete'),
                    ]);

                    return $out . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
