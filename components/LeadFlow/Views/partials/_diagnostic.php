<?php
/*
 * Resultado de «Validar la conexión» con Docketwise.
 *
 * Es el diagnóstico de un momento concreto —viene por flash, no de la base de
 * datos—, así que se pinta arriba del todo y desaparece al recargar. Cada paso
 * dice cuál de las causas posibles es la que falla, en vez de un «no funciona»
 * genérico: la app OAuth de la plataforma, la autorización de la firma, el token
 * o la API respondiendo mal.
 *
 * `$diag` = ['ok' => bool, 'ms' => int, 'checks' => [['label','status','detail']]].
 * Los detalles pueden traer el mensaje literal de Docketwise: se escapan siempre.
 *
 * @var array|null $diag
 */
if (empty($diag['checks'])) {
    return;
}

$styles = [
    'ok'    => ['icon-check-circle', 'text-success'],
    'warn'  => ['icon-exclamation-circle', 'text-warning'],
    'error' => ['icon-x-circle', 'text-danger'],
    'skip'  => ['icon-info-circle', 'text-muted'],
];
?>
<div class="card mb-4">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-shield-check"></i>
            <h5 class="font-semibold"><?= lang('Lfl.diagTitle') ?></h5>
        </div>
        <span class="badge <?= $diag['ok'] ? 'badge-success' : 'badge-danger' ?>">
            <?= lang($diag['ok'] ? 'Lfl.diagPassed' : 'Lfl.diagFailed') ?>
        </span>
    </div>
    <div class="card-body">
        <ul class="divide-y divide-line">
            <?php foreach ($diag['checks'] as $check): ?>
                <?php [$icon, $color] = $styles[$check['status']] ?? $styles['skip']; ?>
                <li class="flex items-start gap-3 py-2 first:pt-0 last:pb-0">
                    <i class="icon <?= esc($icon, 'attr') ?> mt-0.5 shrink-0 <?= esc($color, 'attr') ?>"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-medium"><?= esc($check['label']) ?></p>
                        <p class="break-words text-xs text-muted"><?= esc($check['detail']) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-3 text-xs text-muted"><?= esc(lang('Lfl.diagMs', [(int) $diag['ms']])) ?></p>
    </div>
</div>
