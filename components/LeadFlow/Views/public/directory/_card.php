<?php
/**
 * La tarjeta de un perfil del directorio.
 *
 * Está en un partial porque la pintan **la portada, el buscador y las landings por
 * área**: con el marcado copiado en cada una, basta añadir un dato para que salga en
 * una y falte en las otras dos.
 *
 * Qué enseña, en este orden: quién es y si está verificado, a qué se dedica, sus
 * áreas, dónde e idiomas, y la acción. **La acción es «Solicitar orientación»**, que
 * es lo que convierte la tarjeta en algo accionable — el equivalente a la próxima
 * hora libre en un directorio de citas.
 *
 * @var array $p perfil ya decorado por `PublicDirectory`
 */
$icon    = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$url     = base_url('abogado/' . rawurlencode((string) $p['slug']));
$initials = \Components\LeadFlow\Models\ProfileModel::initials($p['display_name']);

$photo = '';
if (! empty($p['photo'])) {
    $photo = preg_match('~^https?://~i', (string) $p['photo'])
        ? (string) $p['photo']
        : (function_exists('media_url') ? media_url((string) $p['photo']) : base_url(ltrim((string) $p['photo'], '/')));
}
?>
<article class="ll-card ll-pro" style="position:relative">
  <?php if (! empty($p['is_featured'])): ?>
    <span class="ll-featured"><?= esc(lang('Lfl.dirFeatured')) ?></span>
  <?php endif; ?>

  <div class="ll-pro-top">
    <span class="ll-avatar">
      <?php if ($photo !== ''): ?>
        <img src="<?= esc($photo, 'attr') ?>" alt="" loading="lazy" width="56" height="56">
      <?php else: ?>
        <?= esc($initials) ?>
      <?php endif; ?>
    </span>
    <div style="min-width:0">
      <h2 class="ll-pro-name"><a href="<?= esc($url, 'attr') ?>"><?= esc($p['display_name']) ?></a></h2>
      <?php if (! empty($p['headline'])): ?>
        <p class="ll-small ll-muted" style="margin:2px 0 0"><?= esc($p['headline']) ?></p>
      <?php endif; ?>
      <?php // La insignia solo si está verificado: un hueco no dice nada, una insignia falsa sí. ?>
      <?php if (! empty($p['is_verified'])): ?>
        <span class="ll-verified" style="margin-top:6px"><?= $icon('shield') ?><?= esc(lang('Lfl.dirVerified')) ?></span>
      <?php endif; ?>
      <?php // La valoración, solo si tiene opiniones publicadas. ?>
      <?php if (! empty($p['rating'])): ?>
        <div style="margin-top:6px">
          <?= view('Components\LeadFlow\Views\public\directory\_stars', [
              'value' => $p['rating']['avg'], 'count' => $p['rating']['count'], 'size' => 14,
          ]) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (! empty($p['areas'])): ?>
    <div class="ll-tags">
      <?php foreach (array_slice($p['areas'], 0, 3) as $a): ?>
        <span class="ll-tag"><?= esc($a['name']) ?></span>
      <?php endforeach; ?>
      <?php if (count($p['areas']) > 3): ?>
        <span class="ll-tag ll-muted">+<?= count($p['areas']) - 3 ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (! empty($p['city']) || ! empty($p['jurisdictions'])): ?>
    <p class="ll-small ll-muted ll-row" style="margin:0;gap:5px">
      <?= $icon('pin') ?>
      <span><?= esc(implode(' · ', array_filter([
          $p['city'] ?: null,
          $p['jurisdictions'] ? implode(', ', array_slice($p['jurisdictions'], 0, 2)) : null,
      ]))) ?></span>
    </p>
  <?php endif; ?>

  <?php if (! empty($p['languages_list'])): ?>
    <p class="ll-small ll-muted ll-row" style="margin:0;gap:5px">
      <?= $icon('globe') ?><span><?= esc(implode(', ', $p['languages_list'])) ?></span>
    </p>
  <?php endif; ?>

  <div class="ll-pro-foot">
    <a class="ll-btn ll-btn-sm" href="<?= esc($url . '/consulta', 'attr') ?>"><?= esc(lang('Lfl.dirAskCta')) ?></a>
    <a class="ll-small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Lfl.dirSeeProfile')) ?></a>
  </div>
</article>
