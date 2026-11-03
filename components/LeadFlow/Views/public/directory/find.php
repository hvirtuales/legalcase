<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * Buscador y landing por área: la misma vista.
 *
 * Son la misma pregunta con distinto punto de partida, y tenerlas separadas obligaría
 * a mantener dos veces la rejilla, los filtros y la paginación. Lo único que cambia
 * es el título y que el área llega fijada desde la URL.
 *
 * Los filtros viajan por GET y se conservan al pasar de página: una búsqueda tiene que
 * poder enviarse por WhatsApp y seguir siendo la misma búsqueda.
 *
 * @var array      $result
 * @var array      $filters
 * @var array|null $area
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$area = $area ?? null;
$rows = $result['rows'];

$qs = static function (array $extra) use ($filters) {
    $p = array_filter([
        'q'           => $filters['q'],
        'area'        => $filters['area'] ?: '',
        'jurisdiccion' => $filters['jurisdiction'] ?: '',
        'idioma'      => $filters['language'],
        'modalidad'   => $filters['mode'],
        'ciudad'      => $filters['city'],
    ] + $extra, static fn ($v) => $v !== '' && $v !== null);

    return $p ? '?' . http_build_query($p) : '';
};
$base = $area ? base_url('area/' . rawurlencode((string) $area['slug'])) : base_url('buscar');
?>
<h1 style="margin:0 0 4px"><?= esc($area ? lang('Lfl.dirAreaTitle', [$area['name']]) : lang('Lfl.dirFindTitle')) ?></h1>
<p class="ll-sub"><?= esc(lang('Lfl.dirFindSub')) ?></p>

<form class="ll-card ll-pad" method="get" action="<?= esc($base, 'attr') ?>" style="margin-bottom:22px">
  <div class="ll-search">
    <label class="ll-field">
      <span class="ll-label"><?= esc(lang('Lfl.dirWhat')) ?></span>
      <input class="ll-input" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>"
        placeholder="<?= esc(lang('Lfl.dirWhatPh'), 'attr') ?>">
    </label>

    <?php if (! $area): ?>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.dirArea')) ?></span>
        <select class="ll-select" name="area">
          <option value=""><?= esc(lang('Lfl.dirAnyArea')) ?></option>
          <?php foreach ($areas as $id => $name): ?>
            <option value="<?= (int) $id ?>" <?= $filters['area'] === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>

    <label class="ll-field">
      <span class="ll-label"><?= esc(lang('Lfl.dirJurisdiction')) ?></span>
      <select class="ll-select" name="jurisdiccion">
        <option value=""><?= esc(lang('Lfl.dirAnyJurisdiction')) ?></option>
        <?php foreach ($jurs as $id => $name): ?>
          <option value="<?= (int) $id ?>" <?= $filters['jurisdiction'] === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="ll-field">
      <span class="ll-label"><?= esc(lang('Lfl.city')) ?></span>
      <select class="ll-select" name="ciudad">
        <option value=""><?= esc(lang('Lfl.dirAnyCity')) ?></option>
        <?php foreach ($cities as $c): ?>
          <option value="<?= esc($c, 'attr') ?>" <?= $filters['city'] === $c ? 'selected' : '' ?>><?= esc($c) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <button class="ll-btn" type="submit"><?= $icon('search') ?><?= esc(lang('Lfl.dirSearch')) ?></button>
  </div>
</form>

<?php if (! $rows): ?>
  <div class="ll-card ll-empty">
    <p style="margin:0 0 6px;font-weight:600"><?= esc(lang('Lfl.dirNoResults')) ?></p>
    <p class="ll-small ll-muted" style="margin:0"><?= esc(lang('Lfl.dirNoResultsHint')) ?></p>
  </div>
<?php else: ?>
  <p class="ll-small ll-muted"><?= esc(lang('Lfl.dirResultCount', [(int) $result['total']])) ?></p>
  <div class="ll-grid">
    <?php foreach ($rows as $p): ?>
      <?= view('Components\LeadFlow\Views\public\directory\_card', ['p' => $p]) ?>
    <?php endforeach; ?>
  </div>

  <?php if ((int) $result['pages'] > 1): ?>
    <nav class="ll-pag">
      <?php for ($i = 1; $i <= (int) $result['pages']; $i++): ?>
        <?php if ($i === (int) $result['page']): ?>
          <span class="ll-btn ll-btn-sm"><?= esc((string) $i) ?></span>
        <?php else: ?>
          <a class="ll-btn ll-btn-ghost ll-btn-sm" href="<?= esc($base . $qs(['pagina' => $i]), 'attr') ?>"><?= esc((string) $i) ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
