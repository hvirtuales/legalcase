<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * «Solicitar orientación»: el formulario que convierte una visita en un lead de la
 * firma. Es el equivalente a la reserva de cita en un directorio de citas, pero aquí
 * lo que se agenda no es una hora: es el primer contacto.
 *
 * **Pocos campos.** Nombre, apellido, una forma de contacto, el área y qué necesita.
 * Todo lo demás lo completa la firma dentro; pedirlo aquí solo hace que menos gente
 * termine. El área llega preseleccionada si el perfil tiene una sola.
 *
 * Defensas, las mismas que el formulario de la firma: trampa fuera de pantalla, sello
 * de tiempo firmado, captcha propio y consentimiento explícito. El servidor las
 * vuelve a comprobar todas: esto es solo la parte que se ve.
 *
 * @var array  $profile
 * @var string $stamp
 * @var bool   $hasGd
 */
$p    = $profile;
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$url  = base_url('abogado/' . rawurlencode((string) $p['slug']));
$one  = count($p['areas']) === 1 ? $p['areas'][0] : null;
?>
<h1 style="margin:0 0 4px;font-size:24px"><?= esc(lang('Lfl.dirInquiryTitle', [$p['display_name']])) ?></h1>
<p class="ll-sub"><?= esc(lang('Lfl.dirInquirySub')) ?></p>

<?php if (session('dir_error')): ?>
  <div class="ll-alert ll-alert-bad"><?= esc(session('dir_error')) ?></div>
<?php endif; ?>

<div class="ll-split">
  <form class="ll-card ll-pad" method="post" action="<?= esc($url . '/consulta', 'attr') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">

    <div class="ll-hp" aria-hidden="true">
      <label><?= esc(lang('Lfl.pubHoneypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

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
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.city')) ?></span>
        <input class="ll-input" type="text" name="city" maxlength="80" value="<?= esc(old('city'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.dirArea')) ?></span>
        <select class="ll-select" name="area_id">
          <option value=""><?= esc(lang('Lfl.choose')) ?></option>
          <?php foreach ($p['areas'] as $a): ?>
            <option value="<?= (int) ($a['id'] ?? 0) ?>"><?= esc($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <p class="ll-small ll-muted" style="margin:6px 0 14px"><?= esc(lang('Lfl.dirNeedContact')) ?></p>

    <label class="ll-field" style="margin-bottom:14px">
      <span class="ll-label"><?= esc(lang('Lfl.dirCase')) ?></span>
      <textarea class="ll-textarea" name="message" maxlength="2000" placeholder="<?= esc(lang('Lfl.dirCasePh'), 'attr') ?>"><?= esc(old('message')) ?></textarea>
    </label>

    <?php if ($hasGd): ?>
      <div class="ll-row" style="margin-bottom:14px;align-items:flex-end">
        <img src="<?= base_url('abogado/' . rawurlencode((string) $p['slug']) . '/captcha') ?>" alt=""
          data-captcha hidden width="150" height="46"
          style="border:1px solid var(--ll-line);border-radius:8px;background:#fff">
        <label class="ll-field" style="max-width:200px">
          <span class="ll-label"><?= esc(lang('Lfl.pubCaptcha')) ?> <span class="ll-req">*</span></span>
          <input class="ll-input" type="text" name="captcha" inputmode="numeric" autocomplete="off" required>
        </label>
      </div>
      <script>
      // La imagen se pide por JSON para poder renovarla sin recargar; el servidor la
      // devuelve en base64 junto con el código que espera en la sesión.
      (function () {
        var img = document.querySelector('[data-captcha]');
        if (!img) { return; }
        fetch(<?= json_encode(base_url('abogado/' . rawurlencode((string) $p['slug']) . '/captcha')) ?>, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (d.img) { img.src = d.img; img.hidden = false; } })
          .catch(function () {});
      })();
      </script>
    <?php endif; ?>

    <label class="ll-row" style="gap:9px;align-items:flex-start;margin-bottom:16px">
      <input type="checkbox" name="consent" value="1" required style="margin-top:4px">
      <span class="ll-small"><?= esc(lang('Lfl.dirConsent')) ?></span>
    </label>

    <button class="ll-btn" type="submit"><?= $icon('mail') ?><?= esc(lang('Lfl.dirSend')) ?></button>
  </form>

  <aside>
    <div class="ll-card ll-pad">
      <p style="margin:0 0 4px;font-weight:600"><?= esc($p['display_name']) ?></p>
      <?php if (! empty($p['headline'])): ?>
        <p class="ll-small ll-muted" style="margin:0 0 10px"><?= esc($p['headline']) ?></p>
      <?php endif; ?>
      <?php if (! empty($p['areas'])): ?>
        <div class="ll-tags">
          <?php foreach (array_slice($p['areas'], 0, 4) as $a): ?>
            <span class="ll-tag"><?= esc($a['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="ll-small" style="margin:12px 0 0"><a href="<?= esc($url, 'attr') ?>"><?= esc(lang('Lfl.dirSeeProfile')) ?></a></p>
    </div>
    <p class="ll-legal"><?= esc(lang('Lfl.dirDisclaimer')) ?></p>
  </aside>
</div>
<?= $this->endSection() ?>
