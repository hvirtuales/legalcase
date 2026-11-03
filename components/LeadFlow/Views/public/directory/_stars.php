<?php
/**
 * Estrellas de una valoración.
 *
 * **Sin opiniones no se pinta nada**: la vista que la incluye comprueba antes que haya
 * valoración. Cinco estrellas vacías se leen como un cero, y quien acaba de empezar no
 * merece esa lectura — es la misma regla que aplica la tarjeta digital de Salud.
 *
 * Media con un decimal y el número de opiniones al lado: una media sin el conteo no se
 * puede interpretar (4,8 de dos opiniones no es 4,8 de ochenta).
 *
 * @var float    $value
 * @var int      $count
 * @var int|null $size
 */
$size  = $size ?? 15;
$count = $count ?? 0;
$value = max(0, min(5, (float) $value));
?>
<span class="ll-row" style="gap:5px">
  <span style="display:inline-flex;gap:1px;color:var(--ll-gold)">
    <?php for ($i = 1; $i <= 5; $i++): ?>
      <?php // La estrella se rellena si la media la alcanza; media estrella se redondea
            // al entero más cercano, que es lo que la gente lee a ese tamaño. ?>
      <svg viewBox="0 0 24 24" style="width:<?= (int) $size ?>px;height:<?= (int) $size ?>px"
        fill="<?= round($value) >= $i ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.6"
        aria-hidden="true"><path d="m12 4 2.4 5 5.6.8-4 3.9 1 5.5-5-2.7-5 2.7 1-5.5-4-3.9 5.6-.8L12 4Z"/></svg>
    <?php endfor; ?>
  </span>
  <strong style="font-size:14px"><?= esc(number_format($value, 1)) ?></strong>
  <?php // Sin conteo no se pinta el conteo: junto a UNA opinión, un «1 opinión» al
        // lado de sus estrellas no informa de nada, solo repite el contexto. ?>
  <?php if ((int) $count > 0): ?>
    <span class="ll-small ll-muted"><?= esc(lang('Lfl.revCount', [(int) $count])) ?></span>
  <?php endif; ?>
</span>
