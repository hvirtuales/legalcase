<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Bandeja de respuestas: todas las de la empresa, o las de un formulario.
 *
 * @var array|null $dt
 * @var array|null $form
 */
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-envelope"></i>
            <h5 class="font-semibold">
                <?= $form ? esc($form['title']) : lang('Frm.responses') ?>
            </h5>
        </div>
        <?php
        // La exportación sigue al filtro: si hay un formulario elegido se baja
        // ese, y si no, todas las respuestas de la empresa.
        $expBase = $form
            ? base_url('formularios/exportar/' . $form['id'])
            : base_url('formularios/exportar/todo');
        ?>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($form): ?>
                <a href="<?= base_url('formularios/informe/' . $form['id']) ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-bar-chart"></i> <?= lang('Frm.report') ?>
                </a>
            <?php endif; ?>
            <?php if (($dt ?? null) !== null): ?>
                <a href="<?= $expBase ?>/csv" class="btn btn-secondary btn-sm">
                    <i class="icon icon-download"></i> <?= lang('Frm.exportCsv') ?>
                </a>
                <a href="<?= $expBase ?>/json" class="btn btn-secondary btn-sm">
                    <i class="icon icon-download"></i> <?= lang('Frm.exportJson') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i><span><?= lang('Frm.noCompanyHint') ?></span>
            </div>
        <?php else: ?>
            <?php
            // El filtro se ofrece en la bandeja general (con o sin formulario
            // elegido). En la ruta propia de un formulario no: allí ya se sabe
            // cuál es, y el enlace de vuelta es el del listado.
            $enBandeja = empty($form) || !empty($filtered);
            ?>
            <?php if ($enBandeja && !empty($forms)): ?>
                <!--
                  Filtro por formulario. Va por GET y arrastra `q`, `sort` y `dir`
                  en campos ocultos: cambiar de formulario no puede tirar por la
                  borda la búsqueda ni el orden que ya se habían puesto.
                -->
                <form method="get" action="<?= base_url('formularios/respuestas') ?>"
                      class="mb-4 flex flex-wrap items-center gap-2">
                    <label class="text-sm text-muted" for="frmFilter"><?= lang('Frm.filterByForm') ?></label>
                    <select class="form-input max-w-xs" id="frmFilter" name="form" onchange="this.form.submit()">
                        <option value=""><?= lang('Frm.allForms') ?></option>
                        <?php foreach ($forms as $f): ?>
                            <option value="<?= (int) $f['id'] ?>"
                                <?= (int) ($form['id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>>
                                <?= esc($f['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php foreach (['q', 'sort', 'dir'] as $keep): ?>
                        <?php if (($dt[$keep] ?? '') !== ''): ?>
                            <input type="hidden" name="<?= $keep ?>" value="<?= esc($dt[$keep], 'attr') ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <noscript><button class="btn btn-secondary btn-sm"><?= lang('Frm.apply') ?></button></noscript>
                    <?php if (!empty($form)): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= base_url('formularios/respuestas') ?>">
                            <i class="icon icon-x-lg"></i> <?= lang('Frm.clearFilter') ?>
                        </a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if (empty($form)): ?>
                <p class="mb-3 text-xs text-muted"><?= lang('Frm.exportAllHint') ?></p>
            <?php endif; ?>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => $baseUrl,
                'extraParams'       => $extraParams ?? [],
                'searchPlaceholder' => lang('Frm.searchResponses'),
                'emptyText'         => lang('Frm.noResponses'),
                'columns'           => [
                    [
                        'key' => 'reference', 'label' => lang('Frm.colReference'), 'sortable' => true,
                        'render' => static function ($row) {
                            $dot = (int) $row['is_read'] === 0
                                ? '<span class="mr-1 inline-block h-2 w-2 rounded-full bg-primary" title="' . esc(lang('Frm.unread'), 'attr') . '"></span>'
                                : '';

                            return $dot . '<a class="font-mono font-semibold text-primary hover:underline" href="'
                                . base_url('formularios/respuesta/' . $row['id']) . '">' . esc($row['reference']) . '</a>';
                        },
                    ],
                    // La columna del formulario solo tiene sentido cuando se
                    // están viendo varios: acotada a uno, repite el mismo texto
                    // en todas las filas y le quita sitio a lo demás.
                    ...(empty($form) ? [[
                        'key' => 'form_id', 'label' => lang('Frm.colForm'),
                        'render' => static fn ($row) => esc($titles[(int) $row['form_id']] ?? ''),
                    ]] : []),
                    ['key' => 'created_at', 'label' => lang('Frm.colDate'), 'sortable' => true],
                    ['key' => 'device', 'label' => lang('Frm.colDevice'), 'sortable' => true],
                    [
                        'key' => 'duration_seconds', 'label' => lang('Frm.colDuration'),
                        'render' => static function ($row) {
                            $s = (int) $row['duration_seconds'];

                            return $s >= 60 ? lang('Frm.minutes', [intdiv($s, 60), $s % 60]) : lang('Frm.seconds', [$s]);
                        },
                    ],
                ],
                'actions' => static function ($row) {
                    $html = '<div class="flex justify-end gap-1">'
                        . '<a class="btn btn-ghost btn-sm" href="' . base_url('formularios/respuesta/' . $row['id']) . '">'
                        . '<i class="icon icon-eye"></i></a>';

                    $html .= view('partials/delete_button', [
                        'action'  => base_url('formularios/respuesta/eliminar/' . $row['id']),
                        'confirm' => lang('Frm.confirmDeleteResponse'),
                        'class'   => 'btn btn-ghost btn-sm text-danger',
                        'label'   => lang('Frm.delete'),
                    ]);

                    return $html . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
