<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;

/*
 * Reportes (§27): embudo, primera respuesta —la métrica que el documento pide
 * medir en especial—, conversión por fuente, campaña, servicio, asesor y abogado,
 * perdidos con su motivo, y la atribución de campañas del §26.
 * Barras en CSS puro: sin librerías ni CDN.
 */
?>
<?php if (! $companyId): ?>
    <?= view('Components\LeadFlow\Views\partials\_nocompany') ?>
<?php elseif ($report === null): ?>
    <?= view('Components\LeadFlow\Views\partials\_noaccess') ?>
<?php else: ?>
    <?php $k = $report['kpi']; ?>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h4 class="text-xl font-semibold"><?= lang('Lfl.repTitle') ?></h4>
            <p class="text-sm text-muted"><?= lang('Lfl.repSubtitle') ?></p>
        </div>
        <form method="get" action="<?= base_url('leadflow/reportes') ?>" class="flex flex-wrap items-end gap-2">
            <div class="form-group mb-0">
                <label for="r-desde" class="form-label text-xs"><?= lang('Lfl.from') ?></label>
                <input type="date" id="r-desde" name="desde" value="<?= esc($from, 'attr') ?>" class="form-input">
            </div>
            <div class="form-group mb-0">
                <label for="r-hasta" class="form-label text-xs"><?= lang('Lfl.to') ?></label>
                <input type="date" id="r-hasta" name="hasta" value="<?= esc($to, 'attr') ?>" class="form-input">
            </div>
            <button type="submit" class="btn btn-secondary"><i class="icon icon-funnel"></i> <?= lang('Lfl.filter') ?></button>
        </form>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        <?php
        $cards = [
            [lang('Lfl.kpiGenerated'), (int) $k['generated']],
            [lang('Lfl.kpiQualified'), (int) $k['qualified']],
            [lang('Lfl.kpiConsultations'), (int) $k['consultations'] . ' / ' . (int) $k['consultationsDone']],
            [lang('Lfl.kpiProposals'), (int) $k['proposals']],
            [lang('Lfl.kpiWon'), (int) $k['wonPeriod']],
            [lang('Lfl.kpiConversion'), $k['conversion'] . ' %'],
            [lang('Lfl.kpiPotential'), Meta::money($k['potential'], $currency)],
            [lang('Lfl.kpiWonValue'), Meta::money($k['wonValue'], $currency)],
        ];
        foreach ($cards as [$label, $value]): ?>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs text-muted"><?= esc($label) ?></div>
                    <div class="mt-1 text-lg font-semibold"><?= esc((string) $value) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><i class="icon icon-funnel"></i><h5 class="font-semibold"><?= lang('Lfl.repFunnel') ?></h5></div>
            <div class="card-body">
                <?php $base = max(1, (int) $report['funnel']['generated']); ?>
                <?php foreach (['generated' => 'kpiGenerated', 'qualified' => 'kpiQualified', 'consultation' => 'repWithConsultation',
                    'proposal' => 'kpiProposals', 'won' => 'kpiWonCohort', 'converted' => 'stConverted'] as $key => $label): ?>
                    <?php $n = (int) $report['funnel'][$key]; ?>
                    <div class="mb-2">
                        <div class="flex items-center justify-between text-sm">
                            <span><?= lang('Lfl.' . $label) ?></span>
                            <span class="font-semibold"><?= (int) $n ?> <span class="text-xs text-muted">(<?= (int) round($n * 100 / $base) ?> %)</span></span>
                        </div>
                        <div class="mt-1 h-1.5 w-full rounded bg-surface-muted">
                            <div class="h-1.5 rounded bg-primary" style="width: <?= (int) round($n * 100 / $base) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="icon icon-clock"></i><h5 class="font-semibold"><?= lang('Lfl.repFirstResponse') ?></h5></div>
            <div class="card-body">
                <p class="mb-3 text-xs text-muted"><?= lang('Lfl.repFirstResponseHint') ?></p>
                <?php $tot = max(1, array_sum($report['response'])); ?>
                <?php foreach (['lt5' => 'rt5', 'lt30' => 'rt30', 'lt60' => 'rt60', 'lt240' => 'rt240', 'gt240' => 'rtMore', 'none' => 'rtNone'] as $key => $label): ?>
                    <?php $n = (int) $report['response'][$key]; ?>
                    <div class="mb-2">
                        <div class="flex items-center justify-between text-sm">
                            <span><?= lang('Lfl.' . $label) ?></span>
                            <span class="font-semibold"><?= (int) $n ?></span>
                        </div>
                        <div class="mt-1 h-1.5 w-full rounded bg-surface-muted">
                            <div class="h-1.5 rounded <?= $key === 'lt5' ? 'bg-success' : ($key === 'none' || $key === 'gt240' ? 'bg-danger' : 'bg-primary') ?>" style="width: <?= (int) round($n * 100 / $tot) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php
    $dims = ['source' => 'sources', 'campaign' => 'campaigns', 'service' => 'services', 'advisor' => 'roleAdvisor', 'lawyer' => 'roleLawyer'];
    ?>
    <div class="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <?php foreach ($dims as $dim => $label): ?>
            <div class="card">
                <div class="card-header"><h5 class="font-semibold"><?= esc(lang('Lfl.repBy', [lang('Lfl.' . $label)])) ?></h5></div>
                <div class="card-body">
                    <?php if (! $report['by'][$dim]): ?>
                        <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?= lang('Lfl.name') ?></th>
                                        <th class="text-right"><?= lang('Lfl.navLeads') ?></th>
                                        <th class="text-right"><?= lang('Lfl.kpiQualified') ?></th>
                                        <th class="text-right"><?= lang('Lfl.kpiWonCohort') ?></th>
                                        <th class="text-right"><?= lang('Lfl.kpiConversion') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report['by'][$dim] as $row): ?>
                                        <tr>
                                            <td><?= esc($row['name']) ?></td>
                                            <td class="text-right"><?= (int) $row['leads'] ?></td>
                                            <td class="text-right"><?= (int) $row['qualified'] ?></td>
                                            <td class="text-right"><?= (int) $row['won'] ?></td>
                                            <td class="text-right"><?= $row['leads'] ? round($row['won'] * 100 / $row['leads'], 1) : 0 ?> %</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="card">
            <div class="card-header"><i class="icon icon-x-circle"></i><h5 class="font-semibold"><?= lang('Lfl.repLost') ?></h5></div>
            <div class="card-body">
                <?php if (! $report['lost']): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
                <?php else: ?>
                    <?php $lt = max(1, array_sum($report['lost'])); ?>
                    <?php foreach ($report['lost'] as $reason => $n): ?>
                        <div class="mb-2">
                            <div class="flex items-center justify-between text-sm">
                                <span><?= esc(Meta::label(Meta::LOST_REASONS, (string) $reason)) ?></span>
                                <span class="font-semibold"><?= (int) $n ?></span>
                            </div>
                            <div class="mt-1 h-1.5 w-full rounded bg-surface-muted">
                                <div class="h-1.5 rounded bg-danger" style="width: <?= (int) round($n * 100 / $lt) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-bar-chart"></i><h5 class="font-semibold"><?= lang('Lfl.repAttribution') ?></h5></div>
        <div class="card-body">
            <p class="mb-3 text-xs text-muted"><?= lang('Lfl.repAttributionHint') ?></p>
            <?php if (! $report['campaigns']): ?>
                <p class="text-sm text-muted"><?= lang('Lfl.nothingHere') ?></p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Lfl.campaign') ?></th>
                                <th class="text-right"><?= lang('Lfl.navLeads') ?></th>
                                <th class="text-right"><?= lang('Lfl.kpiQualified') ?></th>
                                <th class="text-right"><?= lang('Lfl.repWithConsultation') ?></th>
                                <th class="text-right"><?= lang('Lfl.kpiWonCohort') ?></th>
                                <th class="text-right"><?= lang('Lfl.repRevenue') ?></th>
                                <th class="text-right"><?= lang('Lfl.budget') ?></th>
                                <th class="text-right"><?= lang('Lfl.repCpl') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['campaigns'] as $c): ?>
                                <tr>
                                    <td><?= esc($c['name']) ?></td>
                                    <td class="text-right"><?= (int) $c['leads'] ?></td>
                                    <td class="text-right"><?= (int) $c['qualified'] ?></td>
                                    <td class="text-right"><?= (int) $c['consultation'] ?></td>
                                    <td class="text-right"><?= (int) $c['won'] ?></td>
                                    <td class="text-right"><?= esc(Meta::money($c['revenue'], $currency)) ?></td>
                                    <td class="text-right"><?= esc(Meta::money($c['budget'], $currency)) ?></td>
                                    <td class="text-right"><?= esc($c['budget'] !== null && $c['leads'] ? Meta::money((float) $c['budget'] / $c['leads'], $currency) : '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
