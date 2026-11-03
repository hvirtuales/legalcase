<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * El perfil público de un abogado o una firma.
 *
 * Enseña lo que el §8 del insumo pide para generar confianza —nombre, jurisdicción,
 * licencia con su enlace de validación, áreas, idiomas, modalidad y experiencia— y
 * **el aviso legal está a la vista, no en la letra pequeña del pie**: es la página
 * donde alguien decide escribirle a un abogado.
 *
 * La acción principal aparece dos veces, arriba y al final de la columna lateral: en
 * el teléfono la primera queda fuera de pantalla en cuanto se lee la biografía.
 *
 * @var array $profile
 */
$p    = $profile;
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$url  = base_url('abogado/' . rawurlencode((string) $p['slug']));

$initials = \Components\LeadFlow\Models\ProfileModel::initials($p['display_name']);
$photo    = '';
if (! empty($p['photo'])) {
    $photo = preg_match('~^https?://~i', (string) $p['photo'])
        ? (string) $p['photo']
        : (function_exists('media_url') ? media_url((string) $p['photo']) : base_url(ltrim((string) $p['photo'], '/')));
}
$modes = [];
foreach ($p['modes_list'] as $m) {
    $modes[] = lang('Lfl.dirMode_' . $m);
}
?>
<div class="ll-split">
  <div>
    <div class="ll-card ll-pad">
      <div class="ll-pro-top">
        <span class="ll-avatar" style="width:72px;height:72px;font-size:24px">
          <?php if ($photo !== ''): ?>
            <img src="<?= esc($photo, 'attr') ?>" alt="" width="72" height="72">
          <?php else: ?>
            <?= esc($initials) ?>
          <?php endif; ?>
        </span>
        <div style="min-width:0">
          <h1 style="margin:0 0 3px;font-size:24px"><?= esc($p['display_name']) ?></h1>
          <?php if (! empty($p['headline'])): ?>
            <p class="ll-muted" style="margin:0"><?= esc($p['headline']) ?></p>
          <?php endif; ?>
          <div class="ll-row" style="margin-top:8px">
            <?php if (! empty($p['is_verified'])): ?>
              <span class="ll-verified"><?= $icon('shield') ?><?= esc(lang('Lfl.dirVerified')) ?></span>
            <?php endif; ?>
            <?php if (! empty($p['experience_years'])): ?>
              <span class="ll-tag"><?= esc(lang('Lfl.dirYears', [(int) $p['experience_years']])) ?></span>
            <?php endif; ?>
            <?php if (! empty($p['company']['name'])): ?>
              <span class="ll-tag"><?= $icon('briefcase') ?> <?= esc($p['company']['name']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (! empty($p['bio'])): ?>
        <h2 style="margin:18px 0 6px;font-size:18px"><?= esc(lang('Lfl.dirAbout')) ?></h2>
        <p style="margin:0;white-space:pre-line"><?= esc($p['bio']) ?></p>
      <?php endif; ?>

      <?php if (! empty($p['areas'])): ?>
        <h2 style="margin:18px 0 6px;font-size:18px"><?= esc(lang('Lfl.dirAreasOf')) ?></h2>
        <div class="ll-tags">
          <?php foreach ($p['areas'] as $a): ?>
            <a class="ll-tag" href="<?= base_url('area/' . rawurlencode((string) $a['slug'])) ?>"><?= esc($a['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h2 style="margin:18px 0 6px;font-size:18px"><?= esc(lang('Lfl.dirCredentials')) ?></h2>
      <dl style="margin:0;display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <?php
        $facts = [
            'dirJurisdiction' => $p['jurisdictions'] ? implode(', ', $p['jurisdictions']) : null,
            'dirLicense'      => $p['bar_number'] ? trim($p['bar_number'] . ' ' . ($p['bar_state'] ? '(' . $p['bar_state'] . ')' : '')) : null,
            'dirLanguages'    => $p['languages_list'] ? implode(', ', $p['languages_list']) : null,
            'dirModes'        => $modes ? implode(', ', $modes) : null,
            'city'            => $p['city'] ?: null,
            'dirVerifiedOn'   => $p['verified_at'] ? date('d/m/Y', strtotime((string) $p['verified_at'])) : null,
        ];
        ?>
        <?php foreach ($facts as $key => $value): ?>
          <?php if ($value !== null && $value !== ''): ?>
            <div>
              <dt class="ll-small ll-muted"><?= esc(lang('Lfl.' . $key)) ?></dt>
              <dd style="margin:0;font-weight:600"><?= esc($value) ?></dd>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </dl>

      <?php if (! empty($p['bar_url'])): ?>
        <p class="ll-small" style="margin:12px 0 0">
          <a href="<?= esc($p['bar_url'], 'attr') ?>" target="_blank" rel="noopener nofollow">
            <?= esc(lang('Lfl.dirBarLink')) ?>
          </a>
        </p>
      <?php endif; ?>
    </div>

    <?php
    /*
     * Opiniones publicadas.
     *
     * **Solo las que la plataforma aprobó**, con su descargo propio y la respuesta de la
     * firma si la hubo. Sin opiniones no se pinta la sección: una lista vacía bajo el
     * título «Opiniones» se lee peor que no tenerla.
     */
    $reviews = (new \Components\LeadFlow\Models\ReviewModel())->published((int) $p['id']);
    ?>
    <?php if (! empty($p['rating']) && $reviews): ?>
      <section class="ll-card ll-pad" style="margin-top:18px">
        <h2 style="font-size:20px"><?= esc(lang('Lfl.revSectionTitle')) ?></h2>
        <?php // El mismo módulo que la ficha de la firma: una sola forma de enseñar una
              // calificación en todo el directorio. ?>
        <?= view('Components\LeadFlow\Views\public\directory\_rating', [
            'rating'    => $p['rating'],
            'breakdown' => (new \Components\LeadFlow\Models\ReviewModel())->breakdownOf((int) $p['id']),
        ]) ?>

        <?php foreach ($reviews as $rev): ?>
          <article style="border-top:1px solid var(--ll-line);padding:12px 0">
            <div class="ll-row" style="justify-content:space-between">
              <strong><?= esc($rev['author_name'] ?: lang('Lfl.revAnonymous')) ?></strong>
              <?= view('Components\LeadFlow\Views\public\directory\_stars', [
                  'value' => (float) $rev['score'], 'size' => 13,
              ]) ?>
            </div>
            <?php if (! empty($rev['comment'])): ?>
              <p style="margin:6px 0 0;white-space:pre-line"><?= esc($rev['comment']) ?></p>
            <?php endif; ?>
            <?php if (! empty($rev['reply'])): ?>
              <div style="margin:8px 0 0;padding:10px;border-left:3px solid var(--ll-gold);background:var(--ll-bg)">
                <p class="ll-small" style="margin:0 0 2px;font-weight:600"><?= esc(lang('Lfl.revFirmReply')) ?></p>
                <p class="ll-small" style="margin:0;white-space:pre-line"><?= esc($rev['reply']) ?></p>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>

        <?php // El descargo de las opiniones va aquí y no en el pie: es donde se leen. ?>
        <p class="ll-legal" style="margin-top:12px"><?= esc(lang('Lfl.revDisclaimer')) ?></p>
      </section>
    <?php endif; ?>

    <?php // El aviso del §17, donde se toma la decisión. ?>
    <p class="ll-legal"><?= esc(lang('Lfl.dirDisclaimer')) ?></p>
    <?php
    /*
     * El canal de denuncia, con el perfil señalado: desde aquí se reporta a ESTA
     * persona, no a todo el despacho. Cuelga de la firma porque es a quien la
     * plataforma puede pedirle explicaciones.
     */
    $firmaSlug = trim((string) ($p['company']['slug'] ?? ''));
    ?>
    <?php if ($firmaSlug !== ''): ?>
      <p class="ll-small ll-muted" style="margin:8px 0 0">
        <a href="<?= esc(base_url('reportar/' . rawurlencode($firmaSlug)) . '?abogado=' . rawurlencode((string) $p['slug']), 'attr') ?>" rel="nofollow">
          <?= esc(lang('Lfl.repReportProfile')) ?>
        </a>
      </p>
    <?php endif; ?>
  </div>

  <aside>
    <div class="ll-card ll-pad">
      <p style="margin:0 0 4px;font-weight:600"><?= esc(lang('Lfl.dirAskTitle')) ?></p>
      <p class="ll-small ll-muted" style="margin:0 0 12px"><?= esc(lang('Lfl.dirAskSub')) ?></p>
      <a class="ll-btn ll-btn-block" href="<?= esc($url . '/consulta', 'attr') ?>"><?= esc(lang('Lfl.dirAskCta')) ?></a>
      <?php // La tarjeta es para presentarse; el perfil, para que alguien escriba. Se
            // ofrece aquí porque es desde donde se comparte. ?>
      <p class="ll-small ll-row" style="margin:12px 0 0;gap:6px">
        <?= $icon('card') ?><a href="<?= esc($url . '/tarjeta', 'attr') ?>"><?= esc(lang('Lfl.dcCardLink')) ?></a>
      </p>

      <?php // Los contactos directos solo si el profesional los publicó. ?>
      <?php if (! empty($p['website'])): ?>
        <p class="ll-small ll-row" style="margin:14px 0 0;gap:6px">
          <?= $icon('globe') ?>
          <a href="<?= esc($p['website'], 'attr') ?>" target="_blank" rel="noopener nofollow"><?= esc(lang('Lfl.dirWebsite')) ?></a>
        </p>
      <?php endif; ?>
    </div>

    <p class="ll-small" style="margin:14px 0 0">
      <a href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirBackToSearch')) ?></a>
    </p>
  </aside>
</div>
<?= $this->endSection() ?>
