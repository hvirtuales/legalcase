<?php

/*
 * Historial completo del expediente.
 *
 * Es la línea de tiempo DEL LEAD, y eso es deliberado: el caso es la continuación de
 * la captación, así que «entró por el formulario → se agendó consulta → se aprobó →
 * se abrió EXP-000012 → se radicó» se lee de corrido. Partirlo en dos daría dos
 * medios historiales y la pregunta de dónde quedó el resto.
 *
 * Se reutiliza el mismo parcial que la ficha del lead, sin copiarlo.
 *
 * @var array $timeline
 */
?>
<div class="card">
    <div class="card-header">
        <div class="flex items-center gap-2">
            <i class="icon icon-clock-history"></i>
            <h6 class="font-semibold"><?= lang('Lfl.mtrTabTimeline') ?></h6>
        </div>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.timelineHint') ?></p>
        <?= view('Components\LeadFlow\Views\leads\tabs\_timeline_list', ['timeline' => $timeline]) ?>
    </div>
</div>
