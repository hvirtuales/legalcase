<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Informe del formulario.
 *
 * Todas las cifras están CALCULADAS. Donde no hay con qué calcular, se dice que
 * no lo hay: la app de la que nace el componente pintaba un 98,2 por ciento fijo
 * de finalización, que es una maqueta disfrazada de dato.
 *
 * Las barras son CSS puro, sin librería de gráficas ni CDN, como el panel de
 * Contabilidad.
 *
 * @var array $form
 * @var array $summary
 * @var array $stats
 */
$fmtTime = static function (int $s): string {
    return $s >= 60 ? lang('Frm.minutes', [intdiv($s, 60), $s % 60]) : lang('Frm.seconds', [$s]);
};
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <a href="<?= base_url('formularios') ?>" class="btn btn-secondary btn-sm">
        <i class="icon icon-arrow-left"></i> <?= lang('Frm.forms') ?>
    </a>
    <div class="flex gap-2">
        <a class="btn btn-secondary btn-sm" href="<?= base_url('formularios/respuestas/' . $form['id']) ?>">
            <i class="icon icon-envelope"></i> <?= lang('Frm.responses') ?>
        </a>
        <a class="btn btn-secondary btn-sm" href="<?= base_url('formularios/exportar/' . $form['id'] . '/csv') ?>">
            <i class="icon icon-download"></i> <?= lang('Frm.exportCsv') ?>
        </a>
    </div>
</div>

<h1 class="mb-4 text-xl font-bold"><?= lang('Frm.reportFor', [esc($form['title'])]) ?></h1>

<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card p-4">
        <div class="text-xs font-bold uppercase tracking-wide text-muted"><?= lang('Frm.totalResponses') ?></div>
        <div class="text-3xl font-extrabold"><?= (int) $summary['total'] ?></div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-bold uppercase tracking-wide text-muted"><?= lang('Frm.totalViews') ?></div>
        <div class="text-3xl font-extrabold"><?= (int) $summary['views'] ?></div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-bold uppercase tracking-wide text-muted"><?= lang('Frm.completionRate') ?></div>
        <div class="text-3xl font-extrabold">
            <?= $summary['completion'] === null ? '<span class="text-base text-muted">' . lang('Frm.noData') . '</span>' : $summary['completion'] . '%' ?>
        </div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-bold uppercase tracking-wide text-muted"><?= lang('Frm.avgTime') ?></div>
        <div class="text-3xl font-extrabold">
            <?= $summary['total'] > 0 ? esc($fmtTime((int) $summary['avgSeconds'])) : '<span class="text-base text-muted">' . lang('Frm.noData') . '</span>' ?>
        </div>
    </div>
</div>

<?php if (!empty($summary['devices'])): ?>
    <div class="card mb-6">
        <div class="card-header"><h5 class="font-semibold"><?= lang('Frm.byDevice') ?></h5></div>
        <div class="card-body space-y-2">
            <?php foreach ($summary['devices'] as $device => $pct): ?>
                <div class="flex items-center gap-3">
                    <span class="w-24 text-sm"><?= esc($device) ?></span>
                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-line">
                        <span class="block h-full rounded-full bg-primary" style="width:<?= (float) $pct ?>%"></span>
                    </span>
                    <span class="w-14 text-right text-sm font-semibold"><?= (float) $pct ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($stats as $stat): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="font-semibold"><?= esc($stat['label']) ?></h5>
        </div>
        <div class="card-body">

            <?php if ($stat['kind'] === 'options'): ?>
                <?php $total = max(1, (int) $stat['total']); ?>
                <?php if ((int) $stat['total'] === 0): ?>
                    <p class="text-sm text-muted"><?= lang('Frm.noData') ?></p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($stat['counts'] as $label => $n): ?>
                            <div class="flex items-center gap-3">
                                <span class="w-40 truncate text-sm" title="<?= esc((string) $label, 'attr') ?>"><?= esc((string) $label) ?></span>
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-line">
                                    <span class="block h-full rounded-full bg-primary" style="width:<?= round($n * 100 / $total) ?>%"></span>
                                </span>
                                <span class="w-20 text-right text-sm">
                                    <strong><?= (int) $n ?></strong>
                                    <span class="text-muted"><?= round($n * 100 / $total) ?>%</span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($stat['kind'] === 'numeric'): ?>
                <?php if ((int) $stat['stats']['count'] === 0): ?>
                    <p class="text-sm text-muted"><?= lang('Frm.noData') ?></p>
                <?php else: ?>
                    <div class="flex flex-wrap gap-6">
                        <div>
                            <div class="text-xs uppercase text-muted"><?= lang('Frm.average') ?></div>
                            <div class="text-2xl font-extrabold"><?= esc((string) $stat['stats']['avg']) ?></div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-muted"><?= lang('Frm.answers') ?></div>
                            <div class="text-2xl font-extrabold"><?= (int) $stat['stats']['count'] ?></div>
                        </div>
                        <?php if (!empty($stat['nps'])): ?>
                            <div>
                                <div class="text-xs uppercase text-muted"><?= lang('Frm.npsScore') ?></div>
                                <div class="text-2xl font-extrabold"><?= (int) $stat['nps']['score'] ?></div>
                            </div>
                            <div class="text-sm">
                                <div><span class="font-semibold text-success"><?= (int) $stat['nps']['promoters'] ?></span> <?= lang('Frm.promoters') ?></div>
                                <div><span class="font-semibold"><?= (int) $stat['nps']['passives'] ?></span> <?= lang('Frm.passives') ?></div>
                                <div><span class="font-semibold text-danger"><?= (int) $stat['nps']['detractors'] ?></span> <?= lang('Frm.detractors') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <?php if (empty($stat['texts'])): ?>
                    <p class="text-sm text-muted"><?= lang('Frm.noData') ?></p>
                <?php else: ?>
                    <div class="mb-2 text-xs uppercase text-muted"><?= lang('Frm.latestTexts') ?></div>
                    <ul class="space-y-2">
                        <?php foreach ($stat['texts'] as $t): ?>
                            <li class="rounded-lg border border-line p-2 text-sm">
                                <?= esc((string) $t['value_text']) ?>
                                <span class="ml-2 text-xs text-muted"><?= esc((string) $t['created_at']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>
<?php endforeach; ?>

<?php if (!$stats): ?>
    <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Frm.noResponses') ?></span></div>
<?php endif; ?>
<?= $this->endSection() ?>
