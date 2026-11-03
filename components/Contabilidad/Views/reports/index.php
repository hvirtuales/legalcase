<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php if (empty($company)): ?>
    <div class="card"><div class="card-body">
        <div class="alert alert-info"><i class="icon icon-info-circle"></i>
            <span>Selecciona una <strong>empresa activa</strong> en el encabezado para ver su panel de contabilidad.</span></div>
    </div></div>
<?php else: ?>
    <?php
    $fmt   = static fn ($n) => number_format((float) $n, 2);
    $max   = 0.0;
    foreach ($monthly as $m) {
        $max = max($max, (float) $m['ingresos'], (float) $m['egresos']);
    }
    $h = static fn ($v) => $max > 0 ? max(2, round((float) $v / $max * 100)) : 0;
    ?>

    <!-- Filtro de rango -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= base_url('contabilidad/panel') ?>" class="flex flex-wrap items-end gap-2">
                <div class="flex items-center gap-2">
                    <i class="icon icon-bar-chart text-2xl text-primary"></i>
                    <div>
                        <div class="text-sm font-semibold"><?= esc($company['name']) ?></div>
                        <div class="text-xs text-muted">Panel de contabilidad</div>
                    </div>
                </div>
                <div class="ml-auto flex flex-wrap items-end gap-2">
                    <div class="form-group mb-0"><label class="form-label text-xs">Desde</label><input type="date" name="from" value="<?= esc($from, 'attr') ?>" class="form-input"></div>
                    <div class="form-group mb-0"><label class="form-label text-xs">Hasta</label><input type="date" name="to" value="<?= esc($to, 'attr') ?>" class="form-input"></div>
                    <div class="form-group mb-0"><label class="form-label text-xs invisible select-none" aria-hidden="true">Aplicar</label><button class="btn btn-secondary">Aplicar</button></div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
        <?php
        $cards = [
            ['Ingresos', $summary['ingresos'], 'text-success', 'icon-arrow-down-circle'],
            ['Egresos', $summary['egresos'], 'text-danger', 'icon-arrow-up-circle'],
            ['Balance', $summary['balance'], $summary['balance'] >= 0 ? 'text-primary' : 'text-danger', 'icon-cash-stack'],
            ['IVA', $summary['iva'], 'text-ink', 'icon-percent'],
            ['Retención', $summary['retencion'], 'text-ink', 'icon-percent'],
        ];
        foreach ($cards as [$lbl, $val, $cls, $ico]): ?>
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wide text-muted"><?= $lbl ?></span>
                        <i class="icon <?= $ico ?> <?= $cls ?>"></i>
                    </div>
                    <div class="mt-1 font-mono text-xl font-bold <?= $cls ?>"><?= $fmt($val) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
        <!-- Gráfico mensual (CSS) -->
        <div class="lg:col-span-3">
            <div class="card h-full">
                <div class="card-header justify-between">
                    <div class="flex items-center gap-2"><i class="icon icon-bar-chart"></i><h6 class="font-semibold">Ingresos vs. Egresos por mes</h6></div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1"><span class="inline-block size-3 rounded-sm bg-success"></span> Ingresos</span>
                        <span class="flex items-center gap-1"><span class="inline-block size-3 rounded-sm bg-danger"></span> Egresos</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($max <= 0): ?>
                        <p class="py-10 text-center text-muted">Sin movimientos en el rango seleccionado.</p>
                    <?php else: ?>
                        <div class="flex items-end justify-around gap-2 border-b border-line" style="height:200px">
                            <?php foreach ($monthly as $m): ?>
                                <div class="flex h-full flex-1 flex-col justify-end">
                                    <div class="flex items-end justify-center gap-1" style="height:100%">
                                        <div class="w-3 rounded-t bg-success" style="height:<?= $h($m['ingresos']) ?>%" title="Ingresos <?= $fmt($m['ingresos']) ?>"></div>
                                        <div class="w-3 rounded-t bg-danger" style="height:<?= $h($m['egresos']) ?>%" title="Egresos <?= $fmt($m['egresos']) ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex justify-around gap-2 pt-1 text-center text-xs text-muted">
                            <?php foreach ($monthly as $m): ?><div class="flex-1"><?= esc(substr($m['month'], 2)) ?></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Desglose + cuentas de cobro -->
        <div class="space-y-4 lg:col-span-2">
            <div class="card">
                <div class="card-header"><i class="icon icon-receipt"></i><h6 class="font-semibold">Por tipo</h6></div>
                <div class="card-body">
                    <?php if (empty($byType)): ?>
                        <p class="text-sm text-muted">Sin comprobantes.</p>
                    <?php else: ?>
                        <table class="table">
                            <tbody>
                                <?php foreach ($byType as $r): ?>
                                    <tr>
                                        <td><?= esc($r['type_name']) ?> <span class="text-xs text-muted">(<?= (int) $r['n'] ?>)</span></td>
                                        <td class="text-right font-mono"><?= $fmt($r['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="icon icon-cash-stack"></i><h6 class="font-semibold">Cuentas de cobro</h6></div>
                <div class="card-body">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-surface-muted px-2 py-3"><div class="text-xl font-bold text-warning"><?= (int) $requests['pending'] ?></div><div class="mt-1 text-xs text-muted">Pendientes</div></div>
                        <div class="rounded-lg bg-surface-muted px-2 py-3"><div class="text-xl font-bold text-success"><?= (int) $requests['approved'] ?></div><div class="mt-1 text-xs text-muted">Aprobadas</div></div>
                        <div class="rounded-lg bg-surface-muted px-2 py-3"><div class="text-xl font-bold text-danger"><?= (int) $requests['rejected'] ?></div><div class="mt-1 text-xs text-muted">Rechazadas</div></div>
                    </div>
                    <?php if ($requests['pending'] > 0): ?>
                        <div class="mt-3 rounded-lg bg-surface-muted p-2 text-center text-sm">Pendiente por pagar: <strong class="font-mono"><?= $fmt($requests['pending_amount']) ?></strong></div>
                    <?php endif; ?>
                    <a href="<?= base_url('contabilidad/cuentas-cobro') ?>" class="btn btn-secondary btn-sm mt-3 w-full">Ver cuentas de cobro</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
