<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Agenda en calendario: la misma agenda que la lista, por día, semana o mes.
 *
 * Tres tipos con su color —plazo (ámbar; rojo si es crítico o está por validar),
 * audiencia (tinta) y tarea (azul)—; lo ya cumplido se atenúa y se tacha. Los días no
 * hábiles se sombrean y, si son festivo, vacancia o día del despacho, dicen cuál.
 *
 * En móvil el mes no cabe con títulos: cada día enseña puntos por tipo y el número lleva
 * a la vista de día. La semana se apila en una columna.
 *
 * @var string $scale 'dia'|'semana'|'mes' @var string $date @var string $from @var string $to
 * @var string $prev @var string $next @var array $days [Y-m-d => ?motivo no hábil]
 * @var array $events [Y-m-d => list<evento>] @var bool $mine @var array $numbers @var array $users
 */
use Components\LegalCase\Libraries\Meta;

$today = date('Y-m-d');
$base  = base_url('legalcase/agenda/calendario');
$url   = static function (string $scale, string $date) use ($base, $mine): string {
    return $base . '?' . http_build_query(['escala' => $scale, 'fecha' => $date] + ($mine ? ['mios' => '1'] : []));
};
$link  = fn (int $mid) => view('Components\LegalCase\Views\_matter_link', ['numbers' => $numbers, 'mid' => $mid]);
$wd    = static fn (string $ymd, bool $long = false): string => lang('Lgc.' . ($long ? 'agcWdLong_' : 'agcWd_') . date('N', strtotime($ymd)));
$mon   = static fn (string $ymd): string => lang('Lgc.agcMonth_' . (int) date('n', strtotime($ymd)));
$dnum  = static fn (string $ymd): int => (int) date('j', strtotime($ymd));

// Lo que se dice de un día no hábil: el fin de semana se ve en el sombreado y no se rotula.
$weekend   = lang('Lgc.calWeekend');
$dayReason = static fn (string $ymd): ?string => ($r = $days[$ymd] ?? null) !== null && $r !== $weekend ? $r : null;

// Color y texto por tipo de evento.
$tone = static function (array $e): string {
    // Audiencia en `ink` y no en `primary`: el primario lo fija el tema en runtime y puede
    // ser casi negro, que en modo oscuro desaparece. `ink` siempre contrasta con el fondo.
    if ($e['type'] === 'hearing') {
        return 'bg-ink/10 text-ink';
    }
    if ($e['type'] === 'task') {
        return 'bg-info/10 text-info';
    }

    return $e['alert'] ? 'bg-danger/10 text-danger' : 'bg-warning/15 text-ink';
};
$dot   = static fn (array $e): string => $e['type'] === 'hearing' ? 'bg-ink' :($e['type'] === 'task' ? 'bg-info' : ($e['alert'] ? 'bg-danger' : 'bg-warning'));
$label = static fn (array $e): string => (string) $e['row']['title'];
$href  = static fn (array $e): string => base_url('legalcase/asuntos/ficha/' . (int) $e['row']['matter_id'] . '?tab=plazos');
$hour  = static fn (array $e): string => substr($e['at'], 11, 5);

if ($scale === 'mes') {
    $heading = lang('Lgc.agcTitleMonth', [$mon($date), date('Y', strtotime($date))]);
} elseif ($scale === 'semana') {
    // «2 – 8 de noviembre» si la semana cae en un mes; «26 de octubre – 1 de noviembre» si lo cruza.
    $heading = substr($from, 0, 7) === substr($to, 0, 7)
        ? lang('Lgc.agcTitleWeekSame', [$dnum($from), $dnum($to), $mon($to), date('Y', strtotime($to))])
        : lang('Lgc.agcTitleWeek', [$dnum($from), $mon($from), $dnum($to), $mon($to), date('Y', strtotime($to))]);
} else {
    $heading = lang('Lgc.agcTitleDay', [$wd($date, true), $dnum($date), $mon($date), date('Y', strtotime($date))]);
}
$count = array_sum(array_map('count', $events));

/** Un evento en lista (semana y día): hora, tipo, asunto, título y detalle. */
$item = function (array $e, bool $full) use ($tone, $label, $href, $hour, $link, $users): string {
    $r      = $e['row'];
    $who    = $users[(int) ($r['assigned_to'] ?? 0)] ?? null;
    $status = match ($e['type']) {
        'deadline' => Meta::badge('deadline', $r['status']),
        'hearing'  => $r['status'] !== 'scheduled' ? '<span class="badge badge-light">' . lang('Lgc.hstatus_' . $r['status']) . '</span>' : '',
        default    => $e['done'] ? '<span class="badge badge-success">' . lang('Lgc.agcDone') . '</span>' : '',
    };
    ob_start(); ?>
    <li class="rounded-sm px-3 py-2 <?= $tone($e) ?> <?= $e['done'] ? 'opacity-60' : '' ?>">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="font-mono font-semibold"><?= esc($hour($e)) ?></span>
            <span class="font-semibold uppercase tracking-wide"><?= lang('Lgc.agcType_' . $e['type']) ?></span>
            <?= $link((int) $r['matter_id']) ?>
            <?php if ($e['type'] === 'deadline' && (int) $r['is_critical']): ?><span class="badge badge-danger"><?= lang('Lgc.critical') ?></span><?php endif; ?>
            <?= $status ?>
        </div>
        <a href="<?= $href($e) ?>" class="mt-0.5 block text-sm font-semibold text-ink <?= $e['done'] ? 'line-through' : '' ?>"><?= esc($label($e)) ?></a>
        <?php if ($full): ?>
            <div class="text-xs text-muted">
                <?php if ($e['type'] === 'hearing'): ?>
                    <?= lang('Lgc.hmode_' . $r['mode']) ?><?= $r['location'] ? ' · ' . esc($r['location']) : '' ?>
                <?php else: ?>
                    <?= esc($who ?? '—') ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<?= view('Components\LegalCase\Views\_agenda_tabs', ['view' => 'calendar', 'mine' => $mine, 'keep' => ['escala' => $scale, 'fecha' => $date]]) ?>

<div class="card">
    <div class="card-header flex-wrap justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="btn-group" role="group" aria-label="<?= esc(lang('Lgc.agcNav'), 'attr') ?>">
                <a class="btn btn-outline btn-sm" href="<?= $url($scale, $prev) ?>" title="<?= esc(lang('Lgc.agcPrev'), 'attr') ?>" aria-label="<?= esc(lang('Lgc.agcPrev'), 'attr') ?>"><i class="icon icon-chevron-left"></i></a>
                <a class="btn btn-outline btn-sm" href="<?= $url($scale, $today) ?>"><?= lang('Lgc.agcToday') ?></a>
                <a class="btn btn-outline btn-sm" href="<?= $url($scale, $next) ?>" title="<?= esc(lang('Lgc.agcNext'), 'attr') ?>" aria-label="<?= esc(lang('Lgc.agcNext'), 'attr') ?>"><i class="icon icon-chevron-right"></i></a>
            </div>
            <h5 class="font-semibold first-letter:uppercase"><?= esc($heading) ?></h5>
        </div>
        <div class="btn-group" role="group" aria-label="<?= esc(lang('Lgc.agcScale'), 'attr') ?>">
            <?php foreach (['dia' => 'agcDay', 'semana' => 'agcWeek', 'mes' => 'agcMonth'] as $s => $k): ?>
                <a class="btn btn-sm <?= $scale === $s ? 'btn-primary' : 'btn-outline' ?>" href="<?= $url($s, $date) ?>" <?= $scale === $s ? 'aria-current="page"' : '' ?>><?= lang('Lgc.' . $k) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card-body">
        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-warning"></span><?= lang('Lgc.agcType_deadline') ?></span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-danger"></span><?= lang('Lgc.agcLegendAlert') ?></span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-ink"></span><?= lang('Lgc.agcType_hearing') ?></span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-info"></span><?= lang('Lgc.agcType_task') ?></span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm border border-line bg-surface-muted"></span><?= lang('Lgc.agcNonWorking') ?></span>
        </div>

        <?php if ($scale === 'mes'): ?>
            <?php $month = substr($date, 0, 7); ?>
            <div class="overflow-hidden rounded-sm border border-line">
                <div class="grid grid-cols-7 border-b border-line bg-surface-muted text-center text-xs font-semibold text-muted">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <div class="py-2"><?= lang('Lgc.agcWd_' . $i) ?></div>
                    <?php endfor; ?>
                </div>
                <div class="grid grid-cols-7">
                    <?php foreach ($days as $ymd => $reason): ?>
                        <?php
                        $list   = $events[$ymd] ?? [];
                        $out    = substr($ymd, 0, 7) !== $month;
                        $shade  = $reason !== null;
                        $named  = $dayReason($ymd);
                        ?>
                        <div class="min-h-16 border-b border-r border-line p-1 sm:min-h-28 sm:p-1.5 <?= $shade ? 'bg-surface-muted' : 'bg-surface' ?> <?= $out ? 'opacity-50' : '' ?>">
                            <div class="flex items-start justify-between gap-1">
                                <a href="<?= $url('dia', $ymd) ?>" class="inline-grid h-6 w-6 place-items-center rounded-full text-xs font-semibold <?= $ymd === $today ? 'bg-ink text-surface' : 'text-ink hover:bg-line' ?>"
                                   aria-label="<?= esc($wd($ymd, true) . ' ' . $dnum($ymd) . ' ' . $mon($ymd) . ($list ? ' · ' . count($list) : ''), 'attr') ?>"><?= $dnum($ymd) ?></a>
                                <?php if ($named): ?>
                                    <span class="hidden truncate text-[10px] leading-6 text-muted sm:block" title="<?= esc($named, 'attr') ?>"><?= esc($named) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($list): ?>
                                <?php // Móvil: un punto por evento (máx. 4). ?>
                                <div class="mt-1 flex flex-wrap gap-0.5 sm:hidden">
                                    <?php foreach (array_slice($list, 0, 4) as $e): ?><span class="h-1.5 w-1.5 rounded-full <?= $dot($e) ?>"></span><?php endforeach; ?>
                                </div>
                                <ul class="mt-1 hidden space-y-0.5 sm:block">
                                    <?php foreach (array_slice($list, 0, 3) as $e): ?>
                                        <li>
                                            <a href="<?= $href($e) ?>" title="<?= esc($hour($e) . ' · ' . $label($e), 'attr') ?>"
                                               class="block truncate rounded-sm px-1.5 py-0.5 text-[11px] leading-tight <?= $tone($e) ?> <?= $e['done'] ? 'line-through opacity-60' : '' ?>"><span class="font-mono"><?= esc($hour($e)) ?></span> <?= esc($label($e)) ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (count($list) > 3): ?>
                                        <li><a href="<?= $url('dia', $ymd) ?>" class="block px-1.5 text-[11px] font-semibold text-primary"><?= lang('Lgc.agcMore', [count($list) - 3]) ?></a></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($scale === 'semana'): ?>
            <div class="grid gap-3 md:grid-cols-7">
                <?php foreach ($days as $ymd => $reason): ?>
                    <?php $list = $events[$ymd] ?? []; $named = $dayReason($ymd); ?>
                    <section class="min-w-0 rounded-sm border border-line <?= $reason !== null ? 'bg-surface-muted' : 'bg-surface' ?>">
                        <a href="<?= $url('dia', $ymd) ?>" class="flex items-baseline justify-between gap-2 border-b border-line px-2.5 py-2 hover:bg-line/40">
                            <span class="text-xs font-semibold uppercase text-muted"><?= $wd($ymd) ?></span>
                            <span class="inline-grid h-7 w-7 place-items-center rounded-full text-sm font-semibold <?= $ymd === $today ? 'bg-ink text-surface' : 'text-ink' ?>"><?= $dnum($ymd) ?></span>
                        </a>
                        <?php if ($named): ?><p class="px-2.5 pt-1.5 text-[11px] text-muted"><?= esc($named) ?></p><?php endif; ?>
                        <?php if ($list): ?>
                            <ul class="space-y-1.5 p-2"><?php foreach ($list as $e) { echo $item($e, false); } ?></ul>
                        <?php else: ?>
                            <p class="p-2.5 text-xs text-muted md:hidden"><?= lang('Lgc.agcNoEvents') ?></p>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <?php $list = $events[$date] ?? []; $reason = $days[$date] ?? null; ?>
            <?php if ($reason !== null): ?>
                <div class="alert alert-warning mb-4"><i class="icon icon-exclamation-circle"></i><span><?= lang('Lgc.agcNonWorkingDay', [esc($reason)]) ?></span></div>
            <?php endif; ?>
            <?php if ($list): ?>
                <ul class="space-y-2"><?php foreach ($list as $e) { echo $item($e, true); } ?></ul>
            <?php else: ?>
                <p class="py-6 text-center text-sm text-muted"><?= lang('Lgc.agcNoEvents') ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($scale !== 'dia' && $count === 0): ?>
            <p class="mt-4 text-center text-sm text-muted"><?= lang('Lgc.agcNoEventsRange') ?></p>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
