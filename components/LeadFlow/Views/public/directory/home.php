<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?php
/*
 * Portada pública con marca LegalCase, sobre la estructura de la versión para
 * abogados de leadslawyers.com/landing: hero con tarjeta visual y tres cifras,
 * secciones con titular grande, banda navy con tarjetas de vidrio y un panel final
 * con la acción en dorado.
 *
 * **Le habla primero al abogado.** El llamado principal es postularse
 * (`/registro-legal`); el buscador del ciudadano tiene su sección (`#directorio`) y
 * sigue mandando por GET a `/buscar`, así que el resultado es un enlace indexable.
 *
 * **Lo que se promete es lo que el sistema hace:** la insignia la concede la
 * plataforma DESPUÉS de publicar (`Profiles::verify()`, `lfl.verify`), el perfil nace
 * en borrador, y la firma es «electrónica simple con evidencia», no certificada.
 *
 * **LegalCase es otro componente** (§0.4): su pilar, sus capacidades, la banda del
 * portal y sus estándares solo salen si su clase existe —el Autoload registra solo
 * los activos—. Sin LegalCase la portada sigue en pie con el directorio y la captación.
 */
$icon   = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$hasLgc = class_exists('Components\LegalCase\Controllers\ClientPortal');

$standards = $hasLgc ? ['verified', 'terms', 'audit', 'data'] : ['verified', 'trace'];
$stats     = $hasLgc ? ['verified', 'terms', 'client'] : ['verified', 'leads', 'profile'];
$features  = ['matters' => 'briefcase', 'terms' => 'calendar', 'hearings' => 'users', 'docs' => 'file',
    'sign' => 'pen', 'engagement' => 'list', 'conflict' => 'alert', 'audit' => 'history'];

/*
 * Fotografías de la portada (Assets/img, servidas por `Asset::file`), en 1200 y 640 px
 * con `srcset`: el móvil no descarga la grande. Todas decorativas (`alt=""`): el texto
 * de al lado ya dice lo que cuentan, y un lector de pantalla no gana nada con
 * «abogada sentada en un escritorio».
 *
 * **Contraste:** el texto que va SOBRE una foto lleva siempre un velo navy de al menos
 * 86 % de opacidad (en la hoja), que deja el blanco por encima de 4,5:1 aun sobre la
 * zona más clara de la imagen.
 */
$photo = static function (string $name, string $sizes, string $class, bool $lazy = true): string {
    $u = static fn (int $w): string => base_url('lfl-recursos/' . $name . '-' . $w . '.webp');

    return '<img class="' . $class . '" src="' . $u(1200) . '" srcset="' . $u(640) . ' 640w, ' . $u(1200) . ' 1200w" sizes="'
        . $sizes . '" width="1200" height="900" alt="" decoding="async"' . ($lazy ? ' loading="lazy"' : ' fetchpriority="high"') . '>';
};
?>

<?= $this->section('hero') ?>
<?php
/*
 * La portada que sube la plataforma es PANORÁMICA (se sube para una banda ancha): va
 * de fondo de la banda del directorio, que tiene esa forma. La tarjeta del hero es
 * vertical y lleva la foto propia del componente.
 */
$cover = (new \Components\LeadFlow\Libraries\Directory\CoverService())->url();
?>
<section class="ll-hero">
  <div class="ll-wrap ll-hero-grid">
    <div>
      <span class="ll-kicker"><?= esc(lang('Lfl.dirKicker')) ?></span>
      <h1><?= esc(lang('Lfl.dirHeroTitle')) ?></h1>
      <p class="ll-lede"><?= esc(lang($hasLgc ? 'Lfl.dirHeroSub' : 'Lfl.dirHeroSubDir')) ?></p>
      <div class="ll-actions">
        <a class="ll-btn" href="<?= base_url('registro-legal') ?>"><?= esc(lang('Lfl.suCta')) ?><?= $icon('arrow') ?></a>
        <a class="ll-btn ll-btn-ghost" href="#plataforma"><?= esc(lang('Lfl.dirCtaPlatform')) ?></a>
      </div>
      <p class="ll-hero-note">
        <?= esc(lang('Lfl.dirCtaSeeker')) ?>
        <a href="#directorio"><?= esc(lang('Lfl.dirCtaSeekerLink')) ?></a>
        <?php if (($totals['profiles'] ?? 0) > 0): ?>
          · <?= esc(lang('Lfl.dirTotals', [(int) $totals['profiles'], (int) $totals['firms']])) ?>
        <?php endif; ?>
      </p>
      <div class="ll-stats">
        <?php foreach ($stats as $k): ?>
          <div class="ll-stat">
            <strong><?= esc(lang('Lfl.dirStat_' . $k)) ?></strong>
            <span><?= esc(lang('Lfl.dirStat_' . $k . 'Body')) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <aside class="ll-visual" aria-labelledby="ll-std-t">
      <?= $photo('hero', '(min-width: 980px) 480px, 100vw', 'll-visual-img', false) ?>
      <span class="ll-visual-brand"><?= $icon('scale') ?><?= esc(lang('Lfl.dirBrand')) ?></span>
      <div class="ll-visual-panel">
        <strong id="ll-std-t"><?= esc(lang('Lfl.dirCredTitle')) ?></strong>
        <ul>
          <?php foreach ($standards as $k): ?>
            <li><?= $icon($k === 'verified' ? 'shield' : 'check') ?><span><?= esc(lang('Lfl.dirCred_' . $k)) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php // Directorio: la puerta del ciudadano. Banda con foto y el buscador encima. ?>
<section class="ll-section" id="directorio">
  <div class="ll-photo-band ll-photo ll-photo-top">
    <?php if ($cover): ?>
      <img class="ll-photo-bg" src="<?= esc($cover, 'attr') ?>" alt="" loading="lazy" decoding="async">
    <?php else: ?>
      <?= $photo('directorio', '100vw', 'll-photo-bg') ?>
    <?php endif; ?>
    <div class="ll-intro">
      <h2><?= esc(lang('Lfl.dirForUsersTitle')) ?></h2>
      <p><?= esc(lang('Lfl.dirForUsersBody')) ?></p>
    </div>

  <div class="ll-card ll-pad">
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
  </div>
  </div>

  <?php // Las áreas solo salen si tienen a alguien publicado (`areasWithCounts()`). ?>
  <?php if ($areas): ?>
    <div class="ll-sec" id="areas">
      <div class="ll-sec-head">
        <h3><?= esc(lang('Lfl.dirAreasTitle')) ?></h3>
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
    </div>
  <?php endif; ?>

  <?php if ($latest): ?>
    <div class="ll-sec">
      <div class="ll-sec-head">
        <h3><?= esc(lang('Lfl.dirLatestTitle')) ?></h3>
        <a class="ll-small" href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirSeeAll')) ?></a>
      </div>
      <div class="ll-grid">
        <?php foreach ($latest as $p): ?>
          <?= view('Components\LeadFlow\Views\public\directory\_card', ['p' => $p]) ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (! $areas && ! $latest): ?>
    <?php // Instalación recién montada: se dice, en vez de dejar la sección flotando. ?>
    <div class="ll-card ll-empty">
      <p style="margin:0 0 6px;font-weight:700"><?= esc(lang('Lfl.dirEmpty')) ?></p>
      <p class="ll-small ll-muted" style="margin:0"><?= esc(lang('Lfl.dirEmptyHint')) ?></p>
    </div>
  <?php endif; ?>
</section>

<?php // La plataforma: las piezas que recibe el despacho. ?>
<section class="ll-section" id="plataforma">
  <div class="ll-intro">
    <h2><?= esc(lang('Lfl.dirHomePlatTitle')) ?></h2>
    <p><?= esc(lang('Lfl.dirPlatformBody')) ?></p>
  </div>
  <div class="ll-cards">
    <?php foreach (array_filter(['dir' => 'presencia', 'leads' => 'captacion', 'lgc' => $hasLgc ? 'gestion' : null]) as $k => $img): ?>
      <article class="ll-feature ll-feature-photo">
        <?= $photo($img, '(min-width: 980px) 380px, (min-width: 760px) 50vw, 100vw', 'll-feature-media') ?>
        <h3><?= esc(lang('Lfl.dirPillar_' . $k)) ?></h3>
        <p><?= esc(lang('Lfl.dirPillar_' . $k . 'Body')) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($hasLgc): ?>
  <?php // Gestión de asuntos: lo que hace LegalCase, en ocho tarjetas. ?>
  <section class="ll-section" id="gestion">
    <div class="ll-intro">
      <h2><?= esc(lang('Lfl.dirPracticeTitle')) ?></h2>
      <p><?= esc(lang('Lfl.dirPracticeBody')) ?></p>
    </div>
    <div class="ll-cards ll-cards-4">
      <?php foreach ($features as $k => $ic): ?>
        <article class="ll-feature">
          <span class="ll-ic"><?= $icon($ic) ?></span>
          <h3><?= esc(lang('Lfl.dirFeat_' . $k)) ?></h3>
          <p><?= esc(lang('Lfl.dirFeat_' . $k . 'Body')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <?php // LegalCase Connect: banda navy sobre la foto de una videollamada con la clienta. ?>
  <section class="ll-section" id="portal">
    <div class="ll-dark ll-dark-grid ll-photo ll-photo-left">
      <?= $photo('portal', '100vw', 'll-photo-bg') ?>
      <div>
        <span class="ll-kicker"><?= esc(lang('Lfl.dirSecConnect')) ?></span>
        <h2><?= esc(lang('Lfl.dirConnectTitle')) ?></h2>
        <p><?= esc(lang('Lfl.dirConnectBody')) ?></p>
        <p class="ll-small"><?= esc(lang('Lfl.dirConnectNote')) ?></p>
        <a class="ll-btn ll-btn-quiet" href="<?= base_url('legalcase-portal') ?>"><?= esc(lang('Lfl.dirNavClients')) ?><?= $icon('arrow') ?></a>
      </div>
      <div class="ll-glass-grid">
        <?php for ($c = 1; $c <= 4; $c++): ?>
          <article class="ll-glass">
            <strong><?= esc(lang('Lfl.dirConnectT' . $c)) ?></strong>
            <p><?= esc(lang('Lfl.dirConnect' . $c)) ?></p>
          </article>
        <?php endfor; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php // Verificación: el proceso tal como funciona. ?>
<section class="ll-section" id="verificacion">
  <div class="ll-media-split">
    <div class="ll-intro">
      <h2><?= esc(lang('Lfl.dirHomeVerifTitle')) ?></h2>
      <p><?= esc(lang('Lfl.dirVerifyBody')) ?></p>
      <p class="ll-note"><?= $icon('shield') ?> <?= esc(lang('Lfl.dirVerifyNote')) ?></p>
    </div>
    <figure class="ll-media"><?= $photo('verificacion', '(min-width: 980px) 520px, 100vw', '') ?></figure>
  </div>
  <div class="ll-cards ll-cards-4">
    <?php for ($s = 1; $s <= 4; $s++): ?>
      <article class="ll-feature">
        <span class="ll-num"><?= (int) $s ?></span>
        <h3><?= esc(lang('Lfl.dirStep' . $s)) ?></h3>
        <p><?= esc(lang('Lfl.dirStep' . $s . 'Body')) ?></p>
      </article>
    <?php endfor; ?>
  </div>
</section>

<?php // Llamado final: el texto sobre la foto a la izquierda y la acción en dorado a la derecha. ?>
<section class="ll-section">
  <div class="ll-cta">
    <div class="ll-cta-photo ll-photo ll-photo-bottom">
      <?= $photo('cierre', '(min-width: 980px) 560px, 100vw', 'll-photo-bg') ?>
      <span class="ll-kicker"><?= esc(lang('Lfl.dirCtaKicker')) ?></span>
      <h2><?= esc(lang('Lfl.dirFinalTitle')) ?></h2>
      <p><?= esc(lang('Lfl.dirFinalBody')) ?></p>
    </div>
    <div class="ll-action">
      <?php foreach (['draft' => 'file', 'verify' => 'shield', ($hasLgc ? 'data' : 'trace') => 'lock'] as $k => $ic): ?>
        <div class="ll-trust">
          <?= $icon($ic) ?>
          <div>
            <strong><?= esc(lang('Lfl.dirTrust_' . $k)) ?></strong>
            <span><?= esc(lang('Lfl.dirTrust_' . $k . 'Body')) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      <a class="ll-btn ll-btn-gold ll-btn-block" href="<?= base_url('registro-legal') ?>"><?= esc(lang('Lfl.suCta')) ?><?= $icon('arrow') ?></a>
      <p class="ll-fine"><?= esc(lang('Lfl.dirCtaFine')) ?></p>
      <a class="ll-btn ll-btn-ghost ll-btn-block" href="<?= base_url('login') ?>"><?= esc(lang('Lfl.dirNavLogin')) ?></a>
    </div>
  </div>
</section>
<?= $this->endSection() ?>
