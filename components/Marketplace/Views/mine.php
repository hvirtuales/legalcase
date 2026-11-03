<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array|null $dt */
$meta = require __DIR__ . '/_meta.php';
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-clipboard"></i>
            <h5 class="font-semibold"><?= lang('Mkt.myListings') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('marketplace/interesados') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-people"></i> <?= lang('Mkt.interested') ?>
                    <?php if (!empty($newInterest)): ?><span class="badge badge-danger ml-1"><?= (int) $newInterest ?></span><?php endif; ?>
                </a>
                <?php if (!empty($showDemo)): ?>
                    <form action="<?= base_url('marketplace/demo') ?>" method="post" class="inline"
                        onsubmit="return confirm('<?= esc(lang('Mkt.confirmDemo'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary btn-sm" title="<?= esc(lang('Mkt.demoHint'), 'attr') ?>">
                            <i class="icon icon-sparkles"></i> <?= lang('Mkt.demoData') ?>
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= base_url('marketplace/nueva') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> <?= lang('Mkt.newListing') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Mkt.noCompanyHint') ?></span></div>
        <?php else: ?>
            <?php if (!($planEnabled ?? true)): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Mkt.planDisabled') ?></span></div>
            <?php elseif (($quota ?? null) !== null && $quota <= 0): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Mkt.planQuotaOver') ?></span></div>
            <?php elseif (($quota ?? null) !== null): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Mkt.quotaLeft', [$quota]) ?></span></div>
            <?php endif; ?>

            <!-- Filtro por estado -->
            <?php
            $states = ['' => lang('Mkt.all'), 'published' => lang('Mkt.statusPublished'), 'draft' => lang('Mkt.statusDraft'),
                'paused' => lang('Mkt.statusPaused'), 'closed' => lang('Mkt.statusClosed')];
            ?>
            <div class="mb-4 flex flex-wrap gap-1 border-b border-line">
                <?php foreach ($states as $key => $label): ?>
                    <a href="<?= base_url('marketplace/mis-publicaciones') . ($key ? '?status=' . $key : '') ?>"
                        class="border-b-2 px-3 py-2 text-sm <?= (string) ($status ?? '') === (string) $key ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">
                        <?= esc($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('marketplace/mis-publicaciones'),
                'extraParams'       => $extraParams ?? [],
                'searchPlaceholder' => lang('Mkt.searchPlaceholder'),
                'emptyText'         => lang('Mkt.noneYet'),
                'columns'           => [
                    ['key' => 'title', 'label' => lang('Mkt.titleCol'), 'sortable' => true,
                        'render' => static fn ($l) => '<a href="' . base_url('marketplace/publicacion/' . (int) $l['id']) . '" class="font-semibold text-primary">' . esc($l['title']) . '</a>'
                            . ($l['summary'] ? '<div class="text-xs text-muted">' . esc($l['summary']) . '</div>' : '')],
                    ['key' => 'type', 'label' => lang('Mkt.typeCol'), 'sortable' => true,
                        'render' => static fn ($l) => '<span class="badge ' . $meta['typeBadge']($l['type']) . '">' . esc($meta['typeLabel']($l['type'])) . '</span>'],
                    ['key' => 'status', 'label' => lang('Mkt.statusCol'), 'sortable' => true,
                        'render' => static fn ($l) => '<span class="badge ' . $meta['statusBadge']($l['status']) . '">' . esc($meta['statusLabel']($l['status'])) . '</span>'],
                    ['key' => 'views', 'label' => lang('Mkt.viewsCol'), 'sortable' => true,
                        'render' => static fn ($l) => (int) $l['views']],
                    ['key' => 'created_at', 'label' => lang('Mkt.createdCol'), 'sortable' => true,
                        'render' => static fn ($l) => esc(substr((string) $l['created_at'], 0, 10))],
                ],
                'actions' => static function ($l) {
                    $id   = (int) $l['id'];
                    $csrf = csrf_field();
                    $btn  = static function (string $status, string $label, string $icon, string $cls) use ($id, $csrf) {
                        return '<form action="' . base_url('marketplace/estado/' . $id) . '" method="post" class="inline">'
                            . $csrf . '<input type="hidden" name="status" value="' . $status . '">'
                            . '<button class="btn ' . $cls . ' btn-sm" title="' . esc($label, 'attr') . '"><i class="icon ' . $icon . '"></i></button></form>';
                    };

                    $out = '<div class="flex flex-wrap justify-end gap-1">';
                    if ($l['status'] !== 'published') {
                        $out .= $btn('published', lang('Mkt.publish'), 'icon-check-circle', 'btn-success');
                    } else {
                        $out .= $btn('paused', lang('Mkt.pause'), 'icon-dash-circle', 'btn-secondary');
                    }
                    if ($l['status'] !== 'closed') {
                        $out .= $btn('closed', lang('Mkt.close'), 'icon-x-circle', 'btn-ghost');
                    }
                    $out .= '<a href="' . base_url('marketplace/editar/' . $id) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Mkt.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                    $out .= view('partials/delete_button', [
                        'action'  => base_url('marketplace/eliminar/' . $id),
                        'confirm' => lang('Mkt.confirmDelete'),
                        'label'   => lang('Mkt.delete'),
                    ]);

                    return $out . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
