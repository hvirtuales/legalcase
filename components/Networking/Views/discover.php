<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/** @var array $dt @var array $relations @var \Components\Networking\Libraries\NetworkService $net */
$rows = $dt['rows'] ?? [];
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-search"></i>
        <h5 class="font-semibold"><?= lang('Net.discoverPeople') ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Networking\Views\_tabs', ['counts' => $counts, 'current' => 'descubrir']) ?>

        <?php if (!($planEnabled ?? true)): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Net.planDisabled') ?></span></div>
        <?php elseif (($quota ?? null) !== null && $quota <= 0): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Net.planQuotaOver') ?></span></div>
        <?php elseif (($quota ?? null) !== null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Net.planQuotaLeft', [$quota]) ?></span></div>
        <?php endif; ?>

        <?php if (empty($myProfile['share_profile'])): ?>
            <div class="alert alert-info mb-4">
                <i class="icon icon-info-circle"></i>
                <span><?= lang('Net.notSharing') ?>
                    <a href="<?= base_url('networking/mi-perfil') ?>" class="font-semibold text-primary"><?= lang('Net.myProfile') ?></a>
                </span>
            </div>
        <?php endif; ?>

        <!-- Buscador -->
        <form method="get" action="<?= base_url('networking/descubrir') ?>" class="mb-4 flex flex-wrap items-end gap-2">
            <div class="form-group mb-0">
                <label class="form-label text-xs"><?= lang('Net.search') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="icon icon-search"></i></span>
                    <input type="text" name="q" value="<?= esc($dt['q'] ?? '') ?>" class="form-input"
                        placeholder="<?= esc(lang('Net.searchPlaceholder'), 'attr') ?>">
                </div>
            </div>
            <button class="btn btn-secondary btn-sm mb-1"><?= lang('Net.search') ?></button>
            <?php if (($dt['q'] ?? '') !== ''): ?>
                <a href="<?= base_url('networking/descubrir') ?>" class="btn btn-ghost btn-sm mb-1"><?= lang('Net.clear') ?></a>
            <?php endif; ?>
        </form>

        <?php if (empty($rows)): ?>
            <p class="py-8 text-center text-muted">
                <?= ($dt['q'] ?? '') !== '' ? lang('Net.noMatches') : lang('Net.nobodySharing') ?>
            </p>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($rows as $p): ?>
                    <?php
                    $uid = (int) $p['user_id'];
                    $rel = $relations[$uid] ?? null;
                    ?>
                    <?= view('Components\Networking\Views\_card', [
                        'card'  => $cards[$uid] ?? $net->card($uid, $p),
                        'state' => $rel['status'] ?? null,
                        'rel'   => $rel,
                    ]) ?>
                <?php endforeach; ?>
            </div>

            <?php
            // Paginación con el mismo estilo que partials/datatable (aquí las filas
            // se pintan como tarjetas, no como tabla).
            $pager = $dt['pager'] ?? null;
            if ($pager && $pager->getPageCount() > 1):
                $cur   = $pager->getCurrentPage();
                $last  = $pager->getPageCount();
                $pgUrl = static function (int $page) use ($dt) {
                    $params = array_filter(['q' => $dt['q'] ?? '', 'page' => $page]);

                    return base_url('networking/descubrir') . '?' . http_build_query($params);
                };
                ?>
                <nav class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-muted"><?= lang('Net.pageOf', [$cur, $last, $pager->getTotal()]) ?></span>
                    <div class="flex items-center gap-1">
                        <a href="<?= $pgUrl(max(1, $cur - 1)) ?>"
                            class="btn btn-outline btn-sm <?= $cur <= 1 ? 'pointer-events-none opacity-50' : '' ?>"><i class="icon icon-chevron-left"></i></a>
                        <?php for ($p = max(1, $cur - 2); $p <= min($last, $cur + 2); $p++): ?>
                            <a href="<?= $pgUrl($p) ?>" class="btn btn-sm <?= $p === $cur ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a href="<?= $pgUrl(min($last, $cur + 1)) ?>"
                            class="btn btn-outline btn-sm <?= $cur >= $last ? 'pointer-events-none opacity-50' : '' ?>"><i class="icon icon-chevron-right"></i></a>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
