<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Listado de páginas de la empresa activa.
 *
 * @var array|null $dt
 * @var callable   $countBlocks
 */
$statusMeta = [
    'draft'     => ['label' => lang('Pag.statusDraft'), 'badge' => 'badge-secondary'],
    'published' => ['label' => lang('Pag.statusPublished'), 'badge' => 'badge-success'],
];
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-journal-text"></i>
            <h5 class="font-semibold"><?= lang('Pag.pages') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= base_url('paginas/mensajes') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-envelope"></i> <?= lang('Pag.messages') ?>
                    <?php if (!empty($unread)): ?><span class="badge badge-danger ml-1"><?= (int) $unread ?></span><?php endif; ?>
                </a>
                <a href="<?= base_url('paginas/medios') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-image"></i> <?= lang('Pag.media') ?>
                </a>
                <a href="<?= base_url('paginas/plantillas') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-list-columns-reverse"></i> <?= lang('Pag.templates') ?>
                </a>
                <?php if (!empty($showDemo)): ?>
                    <form action="<?= base_url('paginas/demo') ?>" method="post" class="inline"
                        onsubmit="return confirm('<?= esc(lang('Pag.confirmDemo'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary btn-sm" title="<?= esc(lang('Pag.demoHint'), 'attr') ?>">
                            <i class="icon icon-sparkles"></i> <?= lang('Pag.demoData') ?>
                        </button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-sm" data-new-page>
                    <i class="icon icon-plus-circle"></i> <?= lang('Pag.newPage') ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i><span><?= lang('Pag.noCompanyHint') ?></span>
            </div>
        <?php else: ?>
            <?php if (!($planEnabled ?? true)): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Pag.planDisabled') ?></span></div>
            <?php elseif (($quota ?? null) !== null && $quota <= 0): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Pag.planQuotaOver') ?></span></div>
            <?php elseif (($quota ?? null) !== null): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Pag.quotaLeft', [$quota]) ?></span></div>
            <?php endif; ?>

            <?php $states = ['' => lang('Pag.all'), 'published' => lang('Pag.statusPublished'), 'draft' => lang('Pag.statusDraft')]; ?>
            <div class="mb-4 flex flex-wrap gap-1 border-b border-line">
                <?php foreach ($states as $key => $label): ?>
                    <a href="<?= base_url('paginas') . ($key ? '?status=' . $key : '') ?>"
                        class="border-b-2 px-3 py-2 text-sm <?= (string) ($status ?? '') === (string) $key ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">
                        <?= esc($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('paginas'),
                'extraParams'       => $extraParams ?? [],
                'searchPlaceholder' => lang('Pag.searchPlaceholder'),
                'emptyText'         => lang('Pag.noneYet'),
                'columns'           => [
                    ['key' => 'title', 'label' => lang('Pag.titleCol'), 'sortable' => true,
                        'render' => static fn ($p) => '<a href="' . base_url('paginas/editor/' . (int) $p['id']) . '" class="font-semibold text-primary">'
                            . esc($p['title']) . '</a>'
                            . '<div class="text-xs text-muted">' . esc(lang('Pag.blocksCount', [$countBlocks($p)])) . '</div>'],

                    ['key' => 'slug', 'label' => lang('Pag.addressCol'), 'sortable' => true,
                        'render' => static function ($p) use ($companySlug) {
                            $path = 'pagina/' . rawurlencode((string) $companySlug) . '/' . rawurlencode((string) $p['slug']);
                            if ($p['status'] !== 'published' || $companySlug === '') {
                                return '<span class="text-xs text-muted">/' . esc($p['slug']) . '</span>';
                            }

                            return '<a href="' . base_url($path) . '" target="_blank" rel="noopener"'
                                . ' class="text-xs text-primary">/' . esc($p['slug'])
                                . ' <i class="icon icon-box-arrow-up-right"></i></a>';
                        }],

                    ['key' => 'status', 'label' => lang('Pag.statusCol'), 'sortable' => true,
                        'render' => static fn ($p) => '<span class="badge ' . ($statusMeta[$p['status']]['badge'] ?? 'badge-secondary') . '">'
                            . esc($statusMeta[$p['status']]['label'] ?? $p['status']) . '</span>'],

                    ['key' => 'views', 'label' => lang('Pag.viewsCol'), 'sortable' => true,
                        'render' => static fn ($p) => (int) $p['views']],

                    ['key' => 'created_at', 'label' => lang('Pag.createdCol'), 'sortable' => true,
                        'render' => static fn ($p) => esc(substr((string) $p['created_at'], 0, 10))],
                ],
                'actions' => static function ($p) {
                    $id   = (int) $p['id'];
                    $csrf = csrf_field();
                    $out  = '<div class="flex flex-wrap justify-end gap-1">';

                    // Publicar / despublicar
                    $next  = $p['status'] === 'published' ? 'draft' : 'published';
                    $label = $p['status'] === 'published' ? lang('Pag.unpublish') : lang('Pag.publish');
                    $icon  = $p['status'] === 'published' ? 'icon-eye-slash' : 'icon-check-circle';
                    $cls   = $p['status'] === 'published' ? 'btn-secondary' : 'btn-success';
                    $out .= '<form action="' . base_url('paginas/estado/' . $id) . '" method="post" class="inline">'
                        . $csrf . '<input type="hidden" name="status" value="' . $next . '">'
                        . '<button class="btn ' . $cls . ' btn-sm" title="' . esc($label, 'attr') . '">'
                        . '<i class="icon ' . $icon . '"></i></button></form>';

                    $out .= '<a href="' . base_url('paginas/previsualizar/' . $id) . '" target="_blank" rel="noopener"'
                        . ' class="btn btn-ghost btn-sm" title="' . esc(lang('Pag.preview'), 'attr') . '"><i class="icon icon-eye"></i></a>';

                    $out .= '<a href="' . base_url('paginas/editor/' . $id) . '" class="btn btn-primary btn-sm"'
                        . ' title="' . esc(lang('Pag.editContent'), 'attr') . '"><i class="icon icon-pencil"></i></a>';

                    // El engranaje se retiró con la pantalla de ajustes: esos
                    // campos están en la pestaña «Página» del editor, al que ya
                    // lleva el botón de al lado.

                    $out .= '<form action="' . base_url('paginas/duplicar/' . $id) . '" method="post" class="inline">'
                        . $csrf . '<button class="btn btn-ghost btn-sm" title="' . esc(lang('Pag.duplicate'), 'attr') . '">'
                        . '<i class="icon icon-clipboard"></i></button></form>';

                    $out .= view('partials/delete_button', [
                        'action'  => base_url('paginas/eliminar/' . $id),
                        'confirm' => lang('Pag.confirmDelete'),
                        'label'   => lang('Pag.delete'),
                    ]);

                    return $out . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($dt !== null): ?>
<!--
  Diálogo de «Nueva página»: empezar en blanco o partir de una plantilla.

  Elegir plantilla ES crear la página, así que la elección va aquí y no en
  una pantalla aparte: las dos opciones son POST con CSRF a su propia ruta
  (`paginas/nueva` y `paginas/plantillas/usar/{id}`), que ya existían.

  Las plantillas de arranque se retiraron; aquí solo salen las que la
  empresa haya guardado desde sus propias páginas.
-->
<div id="pagNewModal" class="fixed inset-0 z-50 hidden items-start justify-center bg-black/50 p-4 pt-16"
     role="dialog" aria-modal="true" aria-labelledby="pagNewTitle">
    <div class="w-full max-w-xl overflow-hidden rounded-lg bg-surface shadow-lg">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <h2 id="pagNewTitle" class="text-sm font-semibold"><?= lang('Pag.newPage') ?></h2>
            <button type="button" class="btn btn-ghost btn-sm" data-new-close
                    aria-label="<?= esc(lang('App.close'), 'attr') ?>"><i class="icon icon-x-lg"></i></button>
        </div>
        <div class="max-h-96 overflow-y-auto p-4">
            <form action="<?= base_url('paginas/nueva') ?>" method="post">
                <?= csrf_field() ?>
                <button class="flex w-full items-center gap-3 rounded-md border border-line p-3 text-left hover:border-primary hover:bg-primary-light">
                    <i class="icon icon-file-earmark text-2xl text-primary"></i>
                    <span>
                        <span class="block font-semibold"><?= lang('Pag.startBlank') ?></span>
                        <span class="block text-xs text-muted"><?= lang('Pag.startBlankHint') ?></span>
                    </span>
                </button>
            </form>

            <?php if (!empty($templates)): ?>
                <p class="mb-2 mt-4 text-xs font-medium text-faint"><?= lang('Pag.orFromTemplate') ?></p>
                <?php foreach ($templates as $tpl): ?>
                    <form action="<?= base_url('paginas/plantillas/usar/' . (int) $tpl['id']) ?>" method="post" class="mb-2">
                        <?= csrf_field() ?>
                        <button class="flex w-full items-center gap-3 rounded-md border border-line p-3 text-left hover:border-primary hover:bg-primary-light">
                            <i class="icon icon-list-columns-reverse text-2xl text-primary"></i>
                            <span>
                                <span class="block font-semibold"><?= esc($tpl['name']) ?></span>
                                <?php if (!empty($tpl['description'])): ?>
                                    <span class="block text-xs text-muted"><?= esc($tpl['description']) ?></span>
                                <?php endif; ?>
                            </span>
                        </button>
                    </form>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="mt-4 text-xs text-muted"><?= lang('Pag.noTemplatesYet') ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var btn = document.querySelector('[data-new-page]');
    var box = document.getElementById('pagNewModal');
    if (!btn || !box) { return; }

    function abre() { box.classList.remove('hidden'); box.classList.add('flex'); }
    function cierra() { box.classList.add('hidden'); box.classList.remove('flex'); }

    btn.addEventListener('click', abre);
    box.addEventListener('click', function (e) {
        if (e.target === box || e.target.closest('[data-new-close]')) { cierra(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { cierra(); }
    });
}());
</script>
<?php endif; ?>
<?= $this->endSection() ?>
