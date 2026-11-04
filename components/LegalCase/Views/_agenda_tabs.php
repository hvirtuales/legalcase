<?php
/**
 * Cabecera común de la agenda: pestañas Lista / Calendario y el filtro «Solo míos».
 *
 * El filtro viaja entre las dos vistas (y conserva escala y fecha del calendario), para
 * que cambiar de forma de ver no cambie lo que se ve.
 *
 * @var string $view   'list' | 'calendar'
 * @var bool   $mine
 * @var array  $keep   parámetros del calendario que se conservan (escala, fecha)
 */
$keep  = $keep ?? [];
$qs    = static fn (array $p): string => $p ? '?' . http_build_query($p) : '';
$mineP = $mine ? ['mios' => '1'] : [];
$list  = base_url('legalcase/agenda') . $qs($mineP);
$cal   = base_url('legalcase/agenda/calendario') . $qs($keep + $mineP);
$here  = $view === 'list' ? base_url('legalcase/agenda') : base_url('legalcase/agenda/calendario');
$swap  = $here . $qs($view === 'list' ? ($mine ? [] : ['mios' => '1']) : $keep + ($mine ? [] : ['mios' => '1']));
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <?php /* Sin `overflow-x-auto`: dos pestañas nunca desbordan, y el borde de la activa hacía
             aparecer una barra de desplazamiento al lado de «Calendario». */ ?>
    <nav class="tabs" aria-label="<?= esc(lang('Lgc.navAgenda'), 'attr') ?>">
        <a href="<?= $list ?>" class="tab-link whitespace-nowrap <?= $view === 'list' ? 'active' : '' ?>"><i class="icon icon-list"></i> <?= lang('Lgc.agcList') ?></a>
        <a href="<?= $cal ?>" class="tab-link whitespace-nowrap <?= $view === 'calendar' ? 'active' : '' ?>"><i class="icon icon-calendar"></i> <?= lang('Lgc.agcCalendar') ?></a>
    </nav>
    <a href="<?= $swap ?>" class="btn <?= $mine ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><i class="icon icon-person"></i> <?= lang('Lgc.onlyMine') ?></a>
</div>
