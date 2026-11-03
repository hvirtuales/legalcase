<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * «Buscar orientación legal»: el formulario de quien NO sabe a quién acudir.
 *
 * Es el otro camino, y conviene que se note la diferencia: en el perfil de un abogado se
 * le escribe **a él**; aquí se describe el caso y lo recogen hasta tres despachos del
 * área. Eso se dice arriba y se repite en el consentimiento, porque es exactamente lo
 * que la persona está autorizando.
 *
 * **El área es el único campo que decide quién verá el caso**, así que es obligatoria y
 * va primero. Todo lo demás —jurisdicción, ciudad, idioma— acota, no filtra.
 *
 * @var array  $areas
 * @var array  $jurs
 * @var string $stamp
 * @var bool   $hasGd
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>
<h1 style="margin:0 0 4px;font-size:26px"><?= esc(lang('Lfl.oppTitle')) ?></h1>
<p class="ll-sub"><?= esc(lang('Lfl.oppSub')) ?></p>

<?php if (session('dir_error')): ?>
  <div class="ll-alert ll-alert-bad"><?= esc(session('dir_error')) ?></div>
<?php endif; ?>

<div class="ll-split">
  <form class="ll-card ll-pad" method="post" action="<?= base_url('orientacion') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">

    <div class="ll-hp" aria-hidden="true">
      <label><?= esc(lang('Lfl.pubHoneypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <div class="ll-search" style="grid-template-columns:1fr 1fr">
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.dirArea')) ?> <span class="ll-req">*</span></span>
        <select class="ll-select" name="area_id" required>
          <option value=""><?= esc(lang('Lfl.choose')) ?></option>
          <?php foreach ($areas as $id => $name): ?>
            <option value="<?= (int) $id ?>" <?= (int) old('area_id') === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="ll-small ll-muted"><?= esc(lang('Lfl.oppAreaHint')) ?></span>
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.dirJurisdiction')) ?></span>
        <select class="ll-select" name="jurisdiction_id">
          <option value=""><?= esc(lang('Lfl.oppAnyJurisdiction')) ?></option>
          <?php foreach ($jurs as $id => $name): ?>
            <option value="<?= (int) $id ?>" <?= (int) old('jurisdiction_id') === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.city')) ?></span>
        <input class="ll-input" type="text" name="city" maxlength="80" value="<?= esc(old('city'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.dirLanguages')) ?></span>
        <input class="ll-input" type="text" name="language" maxlength="20" value="<?= esc(old('language'), 'attr') ?>"
          placeholder="<?= esc(lang('Lfl.oppLanguagePh'), 'attr') ?>">
      </label>
    </div>

    <label class="ll-field" style="margin:12px 0">
      <span class="ll-label"><?= esc(lang('Lfl.oppCase')) ?></span>
      <textarea class="ll-textarea" name="summary" maxlength="2000" placeholder="<?= esc(lang('Lfl.oppCasePh'), 'attr') ?>"><?= esc(old('summary')) ?></textarea>
    </label>

    <div class="ll-search" style="grid-template-columns:1fr 1fr">
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.firstName')) ?> <span class="ll-req">*</span></span>
        <input class="ll-input" type="text" name="first_name" maxlength="80" required value="<?= esc(old('first_name'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.lastName')) ?> <span class="ll-req">*</span></span>
        <input class="ll-input" type="text" name="last_name" maxlength="80" required value="<?= esc(old('last_name'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.email')) ?></span>
        <input class="ll-input" type="email" name="email" maxlength="150" value="<?= esc(old('email'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.phone')) ?></span>
        <input class="ll-input" type="tel" name="phone" maxlength="30" value="<?= esc(old('phone'), 'attr') ?>">
      </label>
    </div>
    <p class="ll-small ll-muted" style="margin:6px 0 14px"><?= esc(lang('Lfl.dirNeedContact')) ?></p>

    <?php if ($hasGd): ?>
      <div class="ll-row" style="margin-bottom:14px;align-items:flex-end">
        <img src="<?= base_url('orientacion/captcha') ?>" alt="" data-captcha hidden width="150" height="46"
          style="border:1px solid var(--ll-line);border-radius:8px;background:#fff">
        <label class="ll-field" style="max-width:200px">
          <span class="ll-label"><?= esc(lang('Lfl.pubCaptcha')) ?> <span class="ll-req">*</span></span>
          <input class="ll-input" type="text" name="captcha" inputmode="numeric" autocomplete="off" required>
        </label>
      </div>
      <script>
      (function () {
        var img = document.querySelector('[data-captcha]');
        if (!img) { return; }
        fetch(<?= json_encode(base_url('orientacion/captcha')) ?>, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (d.img) { img.src = d.img; img.hidden = false; } })
          .catch(function () {});
      })();
      </script>
    <?php endif; ?>

    <?php // El consentimiento dice **cuántos** pueden escribirle: es lo que de verdad
          // autoriza, y callarlo sería una sorpresa desagradable. ?>
    <label class="ll-row" style="gap:9px;align-items:flex-start;margin-bottom:16px">
      <input type="checkbox" name="consent" value="1" required style="margin-top:4px">
      <span class="ll-small"><?= esc(lang('Lfl.oppConsent', [\Components\LeadFlow\Models\OpportunityModel::MAX_CLAIMS])) ?></span>
    </label>

    <button class="ll-btn" type="submit"><?= $icon('spark') ?><?= esc(lang('Lfl.oppSubmit')) ?></button>
  </form>

  <aside>
    <div class="ll-card ll-pad">
      <p style="margin:0 0 8px;font-weight:600"><?= esc(lang('Lfl.oppHowTitle')) ?></p>
      <ol class="ll-list ll-small">
        <?php for ($i = 1; $i <= 4; $i++): ?>
          <li><?= esc(lang('Lfl.oppHow' . $i)) ?></li>
        <?php endfor; ?>
      </ol>
    </div>
    <div class="ll-card ll-pad" style="margin-top:14px">
      <p style="margin:0 0 4px;font-weight:600"><?= esc(lang('Lfl.oppOrPickTitle')) ?></p>
      <p class="ll-small ll-muted" style="margin:0 0 10px"><?= esc(lang('Lfl.oppOrPickBody')) ?></p>
      <a class="ll-btn ll-btn-ghost ll-btn-sm" href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirFindTitle')) ?></a>
    </div>
    <p class="ll-legal"><?= esc(lang('Lfl.dirDisclaimer')) ?></p>
  </aside>
</div>
<?= $this->endSection() ?>
