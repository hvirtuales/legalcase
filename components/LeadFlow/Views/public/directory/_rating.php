<?php
/**
 * Módulo de calificación: la media grande, las estrellas y el desglose por estrella.
 *
 * Sigue la forma del módulo de reseñas de la vitrina de referencia —nota grande a la
 * izquierda, barras a la derecha—, con una diferencia de fondo: **aquí no hay
 * formulario para opinar**. En el directorio solo opina quien ya fue cliente y por un
 * enlace único que le pasó su abogado; abrir la calificación a cualquiera sería
 * deshacer la decisión que sostiene todo el módulo, porque un testimonio de un abogado
 * está regulado y uno de una tienda no.
 *
 * El total de las barras sale de `count` y **no de sumar el desglose**: si algún día
 * una opinión publicada se quedara sin nota, las barras y el conteo dirían cosas
 * distintas y la que manda es la que el visitante ve arriba.
 *
 * Sin opiniones no se incluye: la vista que lo llama comprueba antes que haya
 * valoración. Cinco estrellas vacías se leen como un cero.
 *
 * @var array $rating    ['avg' => float, 'count' => int]
 * @var array $breakdown [5 => n, 4 => n, 3 => n, 2 => n, 1 => n]
 */
$avg   = (float) ($rating['avg'] ?? 0);
$count = (int) ($rating['count'] ?? 0);
?>
<div class="ll-rate">
  <div class="ll-rate-score">
    <p class="ll-rate-big"><?= esc(number_format($avg, 1)) ?></p>
    <?= view('Components\LeadFlow\Views\public\directory\_stars', [
        'value' => $avg, 'count' => $count, 'size' => 14,
    ]) ?>
    <p class="ll-small ll-muted" style="margin:6px 0 0"><?= esc(lang('Lfl.revBasedOn', [$count])) ?></p>
  </div>

  <div class="ll-rate-bars">
    <?php for ($n = 5; $n >= 1; $n--): ?>
      <?php $c = (int) ($breakdown[$n] ?? 0); ?>
      <div class="ll-rate-row">
        <span class="ll-rate-n"><?= (int) $n ?></span>
        <span class="ll-rate-bar">
          <span class="ll-rate-fill" style="width:<?= $count > 0 ? esc(number_format(($c / $count) * 100, 2, '.', ''), 'attr') : '0' ?>%"></span>
        </span>
        <span class="ll-rate-c"><?= (int) $c ?></span>
      </div>
    <?php endfor; ?>
  </div>
</div>
