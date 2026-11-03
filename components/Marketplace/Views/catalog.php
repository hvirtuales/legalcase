<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array $dt @var array $filters @var array $categories @var array $popularTags @var array $counts */
$meta = require __DIR__ . '/_meta.php';
$rows = $dt['rows'] ?? [];
$cur  = $filters['type'] ?? ($filters['side'] ?? '');

// Pestañas: primero el corte oferta/demanda, luego el tipo concreto.
$tabs = [
    ''            => [lang('Mkt.all'), $counts['all']],
    'offer'       => [lang('Mkt.offer'), $counts['product'] + $counts['service']],
    'demand'      => [lang('Mkt.demand'), $counts['need'] + $counts['opportunity']],
    'product'     => [lang('Mkt.products'), $counts['product']],
    'service'     => [lang('Mkt.services'), $counts['service']],
    'need'        => [lang('Mkt.needs'), $counts['need']],
    'opportunity' => [lang('Mkt.opportunities'), $counts['opportunity']],
];
$tabUrl = static function (string $key) use ($filters, $dt) {
    $p = array_filter([
        'side'     => in_array($key, ['offer', 'demand'], true) ? $key : null,
        'type'     => $key !== '' && !in_array($key, ['offer', 'demand'], true) ? $key : null,
        'category' => $filters['category'] ?? null,
        'tag'      => $filters['tag'] ?? null,
        'q'        => ($dt['q'] ?? '') !== '' ? $dt['q'] : null,
    ]);

    return base_url('marketplace') . ($p ? '?' . http_build_query($p) : '');
};
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-box-seam"></i>
            <h5 class="font-semibold"><?= lang('Mkt.catalog') ?></h5>
        </div>
        <a href="<?= base_url('marketplace/nueva') ?>" class="btn btn-primary btn-sm">
            <i class="icon icon-plus-circle"></i> <?= lang('Mkt.newListing') ?>
        </a>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Mkt.catalogIntro') ?></p>

        <!-- Pestañas por tipo -->
        <div class="mb-4 flex flex-wrap gap-1 border-b border-line">
            <?php foreach ($tabs as $key => [$label, $count]): ?>
                <a href="<?= $tabUrl((string) $key) ?>"
                    class="border-b-2 px-3 py-2 text-sm <?= (string) $cur === (string) $key ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">
                    <?= esc($label) ?> <span class="text-xs">(<?= (int) $count ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Buscador + categoría -->
        <form method="get" action="<?= base_url('marketplace') ?>" class="mb-4 flex flex-wrap items-end gap-2">
            <?php foreach (['side', 'type', 'tag'] as $keep): ?>
                <?php if (!empty($filters[$keep])): ?>
                    <input type="hidden" name="<?= $keep ?>" value="<?= esc($filters[$keep], 'attr') ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="form-group mb-0">
                <label class="form-label text-xs"><?= lang('Mkt.search') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="icon icon-search"></i></span>
                    <input type="text" name="q" value="<?= esc($dt['q'] ?? '') ?>" class="form-input"
                        placeholder="<?= esc(lang('Mkt.searchPlaceholder'), 'attr') ?>">
                </div>
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs"><?= lang('Mkt.category') ?></label>
                <select name="category" class="form-select">
                    <option value=""><?= lang('Mkt.allCategories') ?></option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($filters['category'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= esc($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-secondary btn-sm mb-1"><i class="icon icon-funnel"></i> <?= lang('Mkt.filters') ?></button>
            <?php if ($filters || ($dt['q'] ?? '') !== ''): ?>
                <a href="<?= base_url('marketplace') ?>" class="btn btn-ghost btn-sm mb-1"><?= lang('Mkt.clear') ?></a>
            <?php endif; ?>
        </form>

        <!-- Etiquetas más usadas -->
        <?php if ($popularTags): ?>
            <div class="mb-4">
                <span class="mr-1 text-xs font-semibold text-muted"><?= lang('Mkt.popularTags') ?>:</span>
                <?php foreach ($popularTags as $t): ?>
                    <a href="<?= base_url('marketplace?tag=' . urlencode($t['slug'])) ?>"
                        class="badge <?= ($filters['tag'] ?? '') === $t['slug'] ? 'badge-primary' : 'badge-light hover:bg-line-strong' ?>">
                        # <?= esc($t['name']) ?> <span class="opacity-70"><?= (int) $t['total'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
            <p class="py-10 text-center text-muted">
                <?= ($dt['q'] ?? '') !== '' || $filters ? lang('Mkt.noResults') : lang('Mkt.emptyCatalog') ?>
            </p>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($rows as $l): ?>
                    <?= view('Components\Marketplace\Views\_card', [
                        'l'    => $l,
                        'tags' => $tags[(int) $l['id']] ?? [],
                        'meta' => $meta,
                    ]) ?>
                <?php endforeach; ?>
            </div>

            <?php
            $pager = $dt['pager'] ?? null;
            if ($pager && $pager->getPageCount() > 1):
                $page  = $pager->getCurrentPage();
                $last  = $pager->getPageCount();
                $pgUrl = static function (int $p) use ($filters, $dt) {
                    $params = array_filter(array_merge($filters, ['q' => $dt['q'] ?? '', 'page' => $p]));

                    return base_url('marketplace') . '?' . http_build_query($params);
                };
                ?>
                <nav class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-muted"><?= lang('Mkt.pageOf', [$page, $last, $pager->getTotal()]) ?></span>
                    <div class="flex items-center gap-1">
                        <a href="<?= $pgUrl(max(1, $page - 1)) ?>"
                            class="btn btn-outline btn-sm <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"><i class="icon icon-chevron-left"></i></a>
                        <?php for ($p = max(1, $page - 2); $p <= min($last, $page + 2); $p++): ?>
                            <a href="<?= $pgUrl($p) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a href="<?= $pgUrl(min($last, $page + 1)) ?>"
                            class="btn btn-outline btn-sm <?= $page >= $last ? 'pointer-events-none opacity-50' : '' ?>"><i class="icon icon-chevron-right"></i></a>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
