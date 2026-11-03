<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Listado de formularios de la empresa activa.
 *
 * El alta es un DIALOGO y no un enlace: ahí se elige la PRESENTACION (ficha
 * centrada o pantalla completa), que es la decisión que más cambia el resultado
 * y la que peor sienta descubrir cuando el formulario ya está montado.
 *
 * @var array|null $dt
 */
$statusMeta = [
    'draft'     => ['label' => lang('Frm.statusDraft'), 'badge' => 'badge-secondary'],
    'published' => ['label' => lang('Frm.statusPublished'), 'badge' => 'badge-success'],
    'closed'    => ['label' => lang('Frm.statusClosed'), 'badge' => 'badge-danger'],
];
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-list-check"></i>
            <h5 class="font-semibold"><?= lang('Frm.forms') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= base_url('formularios/respuestas') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-envelope"></i> <?= lang('Frm.responses') ?>
                </a>
                <a href="<?= base_url('formularios/plantillas') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-list-columns-reverse"></i> <?= lang('Frm.templates') ?>
                </a>
                <?php if (!empty($showDemo)): ?>
                    <form action="<?= base_url('formularios/demo') ?>" method="post" class="inline"
                        onsubmit="return confirm('<?= esc(lang('Frm.confirmDemo'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary btn-sm" title="<?= esc(lang('Frm.demoHint'), 'attr') ?>">
                            <i class="icon icon-sparkles"></i> <?= lang('Frm.demoData') ?>
                        </button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-sm" data-frm-new>
                    <i class="icon icon-plus-circle"></i> <?= lang('Frm.newForm') ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i><span><?= lang('Frm.noCompanyHint') ?></span>
            </div>
        <?php else: ?>
            <?php if (!($planEnabled ?? true)): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Frm.planDisabled') ?></span></div>
            <?php elseif (($quota ?? null) !== null && $quota <= 0): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Frm.planQuotaOver') ?></span></div>
            <?php elseif (($quota ?? null) !== null): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Frm.quotaLeft', [$quota]) ?></span></div>
            <?php endif; ?>

            <?php $states = ['' => lang('Frm.all'), 'published' => lang('Frm.statusPublished'), 'draft' => lang('Frm.statusDraft'), 'closed' => lang('Frm.statusClosed')]; ?>
            <div class="mb-4 flex flex-wrap gap-1 border-b border-line">
                <?php foreach ($states as $key => $label): ?>
                    <a href="<?= base_url('formularios') . ($key ? '?status=' . $key : '') ?>"
                        class="border-b-2 px-3 py-2 text-sm <?= (string) ($status ?? '') === (string) $key ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">
                        <?= esc($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('formularios'),
                'extraParams'       => $extraParams ?? [],
                'searchPlaceholder' => lang('Frm.searchPlaceholder'),
                'emptyText'         => lang('Frm.noneYet'),
                'columns'           => [
                    [
                        'key' => 'title', 'label' => lang('Frm.colTitle'), 'sortable' => true,
                        'render' => static function ($row) {
                            $sub = (($row['settings']['display'] ?? 'card') === 'fullscreen')
                                ? lang('Frm.displayFull') : lang('Frm.displayCard');

                            return '<a class="font-semibold text-primary hover:underline" href="'
                                . base_url('formularios/editor/' . $row['id']) . '">' . esc($row['title']) . '</a>'
                                . '<div class="text-xs text-muted">' . esc($sub) . '</div>';
                        },
                    ],
                    [
                        'key' => 'status', 'label' => lang('Frm.colStatus'), 'sortable' => true,
                        'render' => static function ($row) use ($statusMeta) {
                            $m = $statusMeta[$row['status']] ?? $statusMeta['draft'];

                            return '<span class="badge ' . $m['badge'] . '">' . esc($m['label']) . '</span>';
                        },
                    ],
                    [
                        'key' => 'responses', 'label' => lang('Frm.colResponses'),
                        'render' => static function ($row) use ($counts) {
                            $n = $counts[(int) $row['id']] ?? 0;

                            return $n > 0
                                ? '<a class="hover:underline" href="' . base_url('formularios/respuestas/' . $row['id']) . '">' . $n . '</a>'
                                : '<span class="text-muted">0</span>';
                        },
                    ],
                    ['key' => 'views', 'label' => lang('Frm.colViews'), 'sortable' => true],
                    ['key' => 'created_at', 'label' => lang('Frm.colCreated'), 'sortable' => true],
                ],
                'actions' => static function ($row) {
                    $id   = (int) $row['id'];
                    $html = '<div class="flex justify-end gap-1">';

                    $html .= '<a class="btn btn-ghost btn-sm" title="' . esc(lang('Frm.edit'), 'attr') . '" href="'
                        . base_url('formularios/editor/' . $id) . '"><i class="icon icon-pencil"></i></a>';

                    $html .= '<a class="btn btn-ghost btn-sm" title="' . esc(lang('Frm.report'), 'attr') . '" href="'
                        . base_url('formularios/informe/' . $id) . '"><i class="icon icon-bar-chart"></i></a>';

                    $html .= '<a class="btn btn-ghost btn-sm" title="' . esc(lang('Frm.share'), 'attr') . '" href="'
                        . base_url('formularios/compartir/' . $id) . '"><i class="icon icon-share"></i></a>';

                    // Duplicar y cerrar son mutaciones: van por POST con CSRF.
                    $html .= '<form method="post" class="inline" action="' . base_url('formularios/duplicar/' . $id) . '">'
                        . csrf_field() . '<button class="btn btn-ghost btn-sm" title="' . esc(lang('Frm.duplicate'), 'attr')
                        . '"><i class="icon icon-arrow-repeat"></i></button></form>';

                    if ((string) $row['status'] === 'published') {
                        $html .= '<form method="post" class="inline" action="' . base_url('formularios/estado/' . $id) . '"'
                            . ' onsubmit="return confirm(\'' . esc(lang('Frm.confirmClose'), 'js') . '\')">'
                            . csrf_field() . '<input type="hidden" name="status" value="closed">'
                            . '<button class="btn btn-ghost btn-sm" title="' . esc(lang('Frm.close'), 'attr')
                            . '"><i class="icon icon-x-lg"></i></button></form>';
                    }

                    $html .= view('partials/delete_button', [
                        'action'  => base_url('formularios/eliminar/' . $id),
                        'confirm' => lang('Frm.confirmDelete'),
                        'class'   => 'btn btn-ghost btn-sm text-danger',
                        'label'   => lang('Frm.delete'),
                    ]);

                    return $html . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($dt !== null): ?>
<!--
  Diálogo de alta. La presentación se elige AQUI: es lo que decide cómo se ve el
  formulario para quien lo responde, y montar veinte campos para descubrir
  después que se quería a pantalla completa es trabajo tirado.
-->
<dialog id="frmNew" class="card m-auto w-full max-w-lg rounded-xl border border-line p-0 backdrop:bg-black/40">
  <form method="post" action="<?= base_url('formularios/nuevo') ?>">
    <?= csrf_field() ?>
    <div class="card-header justify-between">
      <h5 class="font-semibold"><?= lang('Frm.newForm') ?></h5>
      <button type="button" class="btn btn-ghost btn-sm" data-frm-close><i class="icon icon-x-lg"></i></button>
    </div>

    <div class="card-body space-y-4">
      <div>
        <label class="form-label" for="frmTitle"><?= lang('Frm.fTitle') ?></label>
        <input class="form-input" id="frmTitle" name="title" maxlength="180"
               placeholder="<?= esc(lang('Frm.untitled'), 'attr') ?>" autofocus>
      </div>

      <fieldset class="form-section">
        <legend class="font-semibold"><?= lang('Frm.chooseDisplay') ?></legend>
        <p class="mb-3 text-xs text-muted"><?= lang('Frm.displayCanChange') ?></p>

        <div class="grid gap-3 sm:grid-cols-2">
          <label class="flex cursor-pointer gap-3 rounded-xl border border-line p-3 hover:border-primary">
            <input type="radio" name="display" value="card" checked class="mt-1">
            <span>
              <span class="block font-semibold"><?= lang('Frm.displayCard') ?></span>
              <span class="block text-xs text-muted"><?= lang('Frm.displayCardHint') ?></span>
            </span>
          </label>
          <label class="flex cursor-pointer gap-3 rounded-xl border border-line p-3 hover:border-primary">
            <input type="radio" name="display" value="fullscreen" class="mt-1">
            <span>
              <span class="block font-semibold"><?= lang('Frm.displayFull') ?></span>
              <span class="block text-xs text-muted"><?= lang('Frm.displayFullHint') ?></span>
            </span>
          </label>
        </div>
      </fieldset>

      <?php if (!empty($templates)): ?>
        <p class="text-xs text-muted">
          <?= lang('Frm.tplSaveFrom') ?>:
          <a class="text-primary hover:underline" href="<?= base_url('formularios/plantillas') ?>"><?= lang('Frm.templates') ?></a>
        </p>
      <?php endif; ?>
    </div>

    <div class="card-footer flex justify-end gap-2">
      <button type="button" class="btn btn-secondary" data-frm-close><?= lang('Frm.close') ?></button>
      <button type="submit" class="btn btn-primary"><?= lang('Frm.newForm') ?></button>
    </div>
  </form>
</dialog>

<script>
(function () {
  var dlg = document.getElementById('frmNew');
  if (!dlg) { return; }
  document.querySelectorAll('[data-frm-new]').forEach(function (b) {
    b.addEventListener('click', function () { dlg.showModal(); });
  });
  dlg.querySelectorAll('[data-frm-close]').forEach(function (b) {
    b.addEventListener('click', function () { dlg.close(); });
  });
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
