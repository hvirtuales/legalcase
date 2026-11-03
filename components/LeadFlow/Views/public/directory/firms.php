<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * El listado de firmas.
 *
 * Es la tercera forma de empezar a buscar, después del área —la más abstracta— y del
 * profesional —la más concreta—: quien ya conoce el despacho no quiere filtrar por
 * especialidad, quiere entrar. Cada ficha resume **en qué áreas tiene gente publicada y
 * a cuánta**, que es lo que permite decidir si merece la pena entrar.
 *
 * @var array $result
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$rows = $result['rows'];

$img = static function (?string $path): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    return preg_match('~^https?://~i', $path)
        ? $path
        : (function_exists('media_url') ? media_url($path) : base_url(ltrim($path, '/')));
};
?>
<h1 style="margin:0 0 4px"><?= esc(lang('Lfl.dirFirmsTitle')) ?></h1>
<p class="ll-sub"><?= esc(lang('Lfl.dirFirmsSub')) ?></p>

<?php if (! $rows): ?>
  <div class="ll-card ll-empty">
    <p style="margin:0 0 6px;font-weight:600"><?= esc(lang('Lfl.dirNoFirms')) ?></p>
    <p class="ll-small ll-muted" style="margin:0"><?= esc(lang('Lfl.dirNoFirmsHint')) ?></p>
  </div>
<?php else: ?>
  <div class="ll-grid">
    <?php foreach ($rows as $c): ?>
      <?php $logo = $img($c['logo'] ?? null); ?>
      <article class="ll-card ll-pro">
        <div class="ll-pro-top">
          <span class="ll-avatar">
            <?php if ($logo !== ''): ?>
              <img src="<?= esc($logo, 'attr') ?>" alt="" loading="lazy" width="56" height="56" style="object-fit:contain;background:#fff">
            <?php else: ?>
              <?= $icon('briefcase') ?>
            <?php endif; ?>
          </span>
          <div style="min-width:0">
            <h2 class="ll-pro-name">
              <a href="<?= base_url('firma/' . rawurlencode((string) $c['slug'])) ?>"><?= esc($c['name']) ?></a>
            </h2>
            <p class="ll-small ll-muted" style="margin:2px 0 0">
              <?= esc(lang('Lfl.dirFirmProfiles', [(int) $c['profiles']])) ?>
              <?= $c['city'] ? ' · ' . esc($c['city']) : '' ?>
            </p>
          </div>
        </div>

        <?php // La calificación de la firma, si tiene opiniones publicadas. Sin ellas
              // no se pinta nada: cinco estrellas vacías se leen como un cero. ?>
        <?php if (! empty($c['rating'])): ?>
          <div style="margin:6px 0 2px">
            <?= view('Components\LeadFlow\Views\public\directory\_stars', [
                'value' => $c['rating']['avg'], 'count' => $c['rating']['count'], 'size' => 13,
            ]) ?>
          </div>
        <?php endif; ?>

        <?php if (! empty($c['areas'])): ?>
          <div class="ll-tags">
            <?php foreach (array_slice($c['areas'], 0, 3) as $a): ?>
              <span class="ll-tag"><?= esc($a) ?></span>
            <?php endforeach; ?>
            <?php if (count($c['areas']) > 3): ?>
              <span class="ll-tag ll-muted">+<?= count($c['areas']) - 3 ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="ll-pro-foot">
          <a class="ll-btn ll-btn-sm" href="<?= base_url('firma/' . rawurlencode((string) $c['slug'])) ?>">
            <?= esc(lang('Lfl.dirSeeFirm')) ?>
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ((int) $result['pages'] > 1): ?>
    <nav class="ll-pag">
      <?php for ($i = 1; $i <= (int) $result['pages']; $i++): ?>
        <?php if ($i === (int) $result['page']): ?>
          <span class="ll-btn ll-btn-sm"><?= esc((string) $i) ?></span>
        <?php else: ?>
          <a class="ll-btn ll-btn-ghost ll-btn-sm" href="<?= esc(base_url('firmas') . '?pagina=' . $i, 'attr') ?>"><?= esc((string) $i) ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
