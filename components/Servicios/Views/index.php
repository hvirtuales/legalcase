<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array|null $dt @var array $metrics @var array $filters */
$meta = require __DIR__ . '/_meta.php';
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-receipt"></i>
            <h5 class="font-semibold"><?= lang('Quo.quotes') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex items-center gap-2">
                <?php if (!empty($showDemo)): ?>
                    <form action="<?= base_url('servicios/cotizaciones/demo') ?>" method="post" class="inline"
                        onsubmit="return confirm('<?= esc(lang('Quo.confirmDemo'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary btn-sm" title="<?= esc(lang('Quo.demoHint'), 'attr') ?>">
                            <i class="icon icon-sparkles"></i> <?= lang('Quo.demoData') ?>
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= base_url('servicios/cotizaciones/nueva') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> <?= lang('Quo.newQuote') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.errNoCompany') ?></span></div>
        <?php else: ?>
            <?php if (!($planEnabled ?? true)): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Quo.planDisabled') ?></span></div>
            <?php elseif (($quota ?? null) !== null && $quota <= 0): ?>
                <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Quo.planQuotaOver') ?></span></div>
            <?php elseif (($quota ?? null) !== null): ?>
                <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Quo.quotaLeft', [$quota]) ?></span></div>
            <?php endif; ?>

            <p class="mb-4 text-sm text-muted"><?= lang('Quo.panelIntro') ?></p>

            <!-- Totalizadores -->
            <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <?php
                $cards = [
                    ['icon-receipt', lang('Quo.mDocuments'), (string) $metrics['documents'], 'text-primary'],
                    ['icon-check-circle', lang('Quo.mApproved'), (string) $metrics['approved'], 'text-success'],
                    ['icon-cash-stack', lang('Quo.mAmount'), $meta['money']($metrics['amount'], $currency), 'text-ink'],
                    ['icon-bar-chart', lang('Quo.mConversion'), $metrics['conversion'] . '%', 'text-warning'],
                ];
                foreach ($cards as [$icon, $label, $value, $cls]): ?>
                    <div class="rounded-lg border border-line bg-surface p-4">
                        <div class="text-xs text-muted"><i class="icon <?= $icon ?>"></i> <?= esc($label) ?></div>
                        <div class="mt-1 text-xl font-semibold <?= $cls ?>"><?= esc($value) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Buscador y filtros en UNA sola fila (el DataTable no pinta el
                 suyo: `showSearch => false`), para no gastar dos renglones. -->
            <form method="get" action="<?= base_url('servicios/cotizaciones') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Quo.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Quo.number') . ', ' . lang('Quo.client') . '…', 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs"><?= lang('Quo.filterPeriod') ?></label>
                    <select name="period" class="form-select">
                        <?php
                        $periods = ['' => lang('Quo.allPeriods'), 'mes-actual' => lang('Quo.thisMonth'),
                            'mes-anterior' => lang('Quo.lastMonth'), 'ultimos-3' => lang('Quo.last3'),
                            'ultimos-6' => lang('Quo.last6'), 'este-anio' => lang('Quo.thisYear')];
                        foreach ($periods as $k => $label): ?>
                            <option value="<?= $k ?>" <?= ($filters['period'] ?? '') === (string) $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs"><?= lang('Quo.filterSeller') ?></label>
                    <select name="seller" class="form-select">
                        <option value=""><?= lang('Quo.allSellers') ?></option>
                        <?php foreach ($sellers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) ($filters['seller'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= esc($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs"><?= lang('Quo.filterStatus') ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= lang('Quo.all') ?></option>
                        <?php foreach ($meta['statuses'] as $k => $label): ?>
                            <option value="<?= $k ?>" <?= ($filters['status'] ?? '') === (string) $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
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
                        <?php if (array_filter($filters) || ($dt['q'] ?? '') !== ''): ?>
                            <a href="<?= base_url('servicios/cotizaciones') ?>" class="btn btn-ghost" title="<?= esc(lang('Quo.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'                => $dt,
                'showSearch'        => false,
                'baseUrl'           => base_url('servicios/cotizaciones'),
                'extraParams'       => $extraParams ?? [],
                'searchPlaceholder' => lang('Quo.number') . ', ' . lang('Quo.client') . '…',
                'emptyText'         => lang('Quo.noneYet'),
                'columns'           => [
                    ['key' => 'number', 'label' => lang('Quo.number'), 'sortable' => true,
                        'render' => static fn ($q) => '<a href="' . base_url('servicios/cotizaciones/ver/' . (int) $q['id']) . '" class="font-semibold text-primary">' . esc($q['number']) . '</a>'
                            . '<div class="text-xs text-muted">' . esc($q['doc_type']) . '</div>'],
                    ['key' => 'client_name', 'label' => lang('Quo.client'), 'sortable' => true,
                        'render' => static fn ($q) => esc($q['client_name'] ?: '—')
                            . ($q['client_nit'] ? '<div class="text-xs text-muted">' . esc($q['client_nit']) . '</div>' : '')],
                    ['key' => 'seller_name', 'label' => lang('Quo.seller'), 'sortable' => false,
                        'render' => static fn ($q) => esc($q['seller_name'] ?: '—')],
                    ['key' => 'issue_date', 'label' => lang('Quo.date'), 'sortable' => true,
                        'render' => static fn ($q) => esc(substr((string) $q['issue_date'], 0, 10))],
                    ['key' => 'total', 'label' => lang('Quo.total'), 'sortable' => true,
                        'render' => static fn ($q) => '<span class="font-semibold">' . $meta['money']($q['total'], $q['currency']) . '</span>'],
                    ['key' => 'status', 'label' => lang('Quo.status'), 'sortable' => true,
                        'render' => static fn ($q) => '<span class="badge ' . $meta['statusBadge']($q['status']) . '">' . esc($meta['statusLabel']($q['status'])) . '</span>'],
                ],
                'actions' => static function ($q) {
                    $id  = (int) $q['id'];
                    $out = '<div class="flex flex-wrap justify-end gap-1">';
                    $out .= '<a href="' . base_url('servicios/cotizaciones/ver/' . $id) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Quo.view'), 'attr') . '"><i class="icon icon-eye"></i></a>';
                    $out .= '<a href="' . base_url('servicios/cotizaciones/pdf/' . $id) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Quo.downloadPdf'), 'attr') . '"><i class="icon icon-download"></i></a>';
                    $out .= '<a href="' . base_url('servicios/cotizaciones/editar/' . $id) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Quo.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                    $out .= view('partials/delete_button', [
                        'action'  => base_url('servicios/cotizaciones/eliminar/' . $id),
                        'confirm' => lang('Quo.confirmDelete'),
                        'label'   => lang('Quo.delete'),
                    ]);

                    return $out . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
