<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?php
/*
 * Portada del directorio: las ocho secciones del §11 del insumo de contenidos, en el
 * orden del §19.
 *
 * Dos decisiones que vienen de ese documento y conviene no perder:
 *
 *  1. **El buscador es lo primero**, dentro del hero y mandando por GET a
 *     `/abogados/buscar`: el resultado es un enlace que se puede compartir e indexar,
 *     no un estado del navegador.
 *  2. **Las áreas solo salen si tienen a alguien publicado** (`areasWithCounts()` ya
 *     lo garantiza). Una tarjeta que lleva a una lista vacía es peor que no ofrecerla.
 *
 * Las dos caras del mercado están, pero no compiten: arriba el visitante que busca
 * abogado, y la llamada a los abogados al final, que es donde no le quita el primer
 * pantallazo a la búsqueda.
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>

<?= $this->section('hero') ?>
<?php
/*
 * La portada que subió la plataforma, si la hay. Va como <img> de fondo y no como
 * `background-image`: una URL dentro de un `url()` dentro de un atributo pasa por dos
 * decodificaciones.
 *
 * **El degradado de la marca se queda encima.** El texto del héroe es blanco, y sobre
 * una foto cualquiera —clara u oscura— deja de leerse; con el velo navy delante la
 * imagen se ve y el titular sigue legible. Sin portada no cambia nada: el degradado ya
 * era el fondo.
 */
$cover = (new \Components\LeadFlow\Libraries\Directory\CoverService())->url();
?>
<section class="ll-hero<?= $cover ? ' ll-hero-img' : '' ?>">
  <?php if ($cover): ?>
    <img class="ll-hero-bg" src="<?= esc($cover, 'attr') ?>" alt="">
  <?php endif; ?>
  <div class="ll-wrap">
    <span class="ll-kicker"><?= esc(lang('Lfl.dirKicker')) ?></span>
    <h1><?= esc(lang('Lfl.dirHeroTitle')) ?></h1>
    <p><?= esc(lang('Lfl.dirHeroSub')) ?></p>

    <form class="ll-search" method="get" action="<?= base_url('buscar') ?>">
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.dirWhat')) ?></span>
        <input class="ll-input" type="search" name="q" autocomplete="off"
          placeholder="<?= esc(lang('Lfl.dirWhatPh'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.dirArea')) ?></span>
        <select class="ll-select" name="area">
          <option value=""><?= esc(lang('Lfl.dirAnyArea')) ?></option>
          <?php foreach ($areas as $a): ?>
            <option value="<?= (int) $a['id'] ?>"><?= esc($a['name']) ?> (<?= (int) $a['n'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.city')) ?></span>
        <select class="ll-select" name="ciudad">
          <option value=""><?= esc(lang('Lfl.dirAnyCity')) ?></option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= esc($c, 'attr') ?>"><?= esc($c) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="ll-btn" type="submit"><?= $icon('search') ?><?= esc(lang('Lfl.dirSearch')) ?></button>
    </form>

    <div class="ll-hero-cta">
      <a class="ll-btn ll-btn-quiet" href="<?= base_url('registro-legal') ?>"><?= esc(lang('Lfl.dirCtaLawyer')) ?></a>
      <?php if (($totals['profiles'] ?? 0) > 0): ?>
        <span class="ll-small" style="align-self:center;color:rgba(255,255,255,.8)">
          <?= esc(lang('Lfl.dirTotals', [(int) $totals['profiles'], (int) $totals['firms']])) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php // 2 — Qué es. Una sola frase y el mensaje central de marca del §2. ?>
<section class="ll-sec">
  <div class="ll-card ll-pad">
    <h2 style="margin:0 0 6px;font-size:20px"><?= esc(lang('Lfl.dirWhatIsTitle')) ?></h2>
    <p class="ll-muted" style="margin:0 0 10px;max-width:74ch"><?= esc(lang('Lfl.dirWhatIsBody')) ?></p>
    <p style="margin:0;font-weight:600;color:var(--ll-gold)"><?= esc(lang('Lfl.dirSlogan')) ?></p>
  </div>
</section>

<?php // 6 — Áreas legales. Van pronto porque son la puerta de quien sabe qué necesita. ?>
<?php if ($areas): ?>
  <section class="ll-sec" id="areas">
    <div class="ll-sec-head">
      <h2><?= esc(lang('Lfl.dirAreasTitle')) ?></h2>
      <a class="ll-small" href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirSeeAll')) ?></a>
    </div>
    <div class="ll-areas">
      <?php foreach ($areas as $a): ?>
        <a class="ll-area" href="<?= base_url('area/' . rawurlencode((string) $a['slug'])) ?>">
          <span class="ll-area-n"><?= esc($a['name']) ?></span>
          <span class="ll-area-c"><?= esc(lang('Lfl.dirAreaCount', [(int) $a['n']])) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if (($totals['firms'] ?? 0) > 0): ?>
  <?php // La puerta a las firmas, entre las áreas y las personas: son las tres formas de
        // empezar, de lo más abstracto a lo más concreto. Una banda y no otra rejilla. ?>
  <section class="ll-band" style="margin-bottom:34px">
    <div>
      <strong><?= esc(lang('Lfl.dirFirmsBand')) ?></strong><br>
      <span class="ll-small ll-muted"><?= esc(lang('Lfl.dirFirmsBandSub', [(int) $totals['firms']])) ?></span>
    </div>
    <a class="ll-btn ll-btn-ghost" href="<?= base_url('firmas') ?>"><?= esc(lang('Lfl.dirFirmsTitle')) ?></a>
  </section>
<?php endif; ?>

<?php // Perfiles recién publicados: la prueba de que detrás hay gente. ?>
<?php if ($latest): ?>
  <section class="ll-sec">
    <div class="ll-sec-head">
      <h2><?= esc(lang('Lfl.dirLatestTitle')) ?></h2>
      <a class="ll-small" href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirSeeAll')) ?></a>
    </div>
    <div class="ll-grid">
      <?php foreach ($latest as $p): ?>
        <?= view('Components\LeadFlow\Views\public\directory\_card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php // 4 y 7 — Para usuarios, y cómo funciona (§7 del insumo). ?>
<section class="ll-sec ll-split">
  <div class="ll-card ll-pad">
    <h2 style="margin:0 0 6px;font-size:20px"><?= esc(lang('Lfl.dirForUsersTitle')) ?></h2>
    <p class="ll-muted" style="margin:0 0 12px"><?= esc(lang('Lfl.dirForUsersBody')) ?></p>
    <ol class="ll-list ll-small">
      <?php for ($i = 1; $i <= 4; $i++): ?>
        <li><?= esc(lang('Lfl.dirHowUser' . $i)) ?></li>
      <?php endfor; ?>
    </ol>
    <p style="margin:14px 0 0"><a class="ll-btn ll-btn-sm" href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirCtaUser')) ?></a></p>
  </div>

  <?php // 5 — Confianza y verificación (§8 del insumo). ?>
  <div class="ll-card ll-pad">
    <h2 style="margin:0 0 6px;font-size:20px"><?= $icon('shield') ?> <?= esc(lang('Lfl.dirTrustTitle')) ?></h2>
    <p class="ll-muted ll-small" style="margin:0 0 10px"><?= esc(lang('Lfl.dirTrustBody')) ?></p>
    <ul class="ll-list ll-small">
      <?php for ($i = 1; $i <= 4; $i++): ?>
        <li><?= esc(lang('Lfl.dirTrust' . $i)) ?></li>
      <?php endfor; ?>
    </ul>
  </div>
</section>

<?php // 3 — Para abogados: la otra mitad del mercado, con su propio ancla. ?>
<section class="ll-sec" id="abogados">
  <div class="ll-card ll-pad">
    <h2 style="margin:0 0 6px;font-size:20px"><?= esc(lang('Lfl.dirForLawyersTitle')) ?></h2>
    <p class="ll-muted" style="margin:0 0 12px;max-width:74ch"><?= esc(lang('Lfl.dirForLawyersBody')) ?></p>
    <div class="ll-grid">
      <?php foreach (['community' => 'users', 'leads' => 'spark', 'profile' => 'briefcase'] as $k => $ic): ?>
        <div class="ll-card ll-pad">
          <p style="margin:0 0 4px;font-weight:600"><?= $icon($ic) ?> <?= esc(lang('Lfl.dirBenefit_' . $k)) ?></p>
          <p class="ll-small ll-muted" style="margin:0"><?= esc(lang('Lfl.dirBenefit_' . $k . 'Body')) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <ol class="ll-list ll-small" style="margin-top:14px">
      <?php for ($i = 1; $i <= 4; $i++): ?>
        <li><?= esc(lang('Lfl.dirHowLawyer' . $i)) ?></li>
      <?php endfor; ?>
    </ol>
    <p style="margin:14px 0 0"><a class="ll-btn ll-btn-sm" href="<?= base_url('registro-legal') ?>"><?= esc(lang('Lfl.suCta')) ?></a></p>
  </div>
</section>

<?php // 8 — CTA final. ?>
<section class="ll-band">
  <div>
    <strong><?= esc(lang('Lfl.dirFinalTitle')) ?></strong><br>
    <span class="ll-small ll-muted"><?= esc(lang('Lfl.dirFinalBody')) ?></span>
  </div>
  <a class="ll-btn" href="<?= base_url('registro-legal') ?>"><?= esc(lang('Lfl.dirFinalCta')) ?><?= $icon('arrow') ?></a>
</section>

<?php if (! $areas && ! $latest): ?>
  <?php // Instalación recién montada: se dice, en vez de dejar la portada flotando. ?>
  <div class="ll-card ll-empty" style="margin-top:22px">
    <p style="margin:0 0 6px;font-weight:600"><?= esc(lang('Lfl.dirEmpty')) ?></p>
    <p class="ll-small ll-muted" style="margin:0"><?= esc(lang('Lfl.dirEmptyHint')) ?></p>
  </div>
<?php endif; ?>
<?= $this->endSection() ?>
