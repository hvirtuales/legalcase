<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * Alta pública de un abogado o de una firma.
 *
 * **Un solo formulario con dos caras.** Las dos altas comparten todo menos una cosa —si
 * hay que preguntar el nombre del despacho—, así que partirlas en dos pantallas obligaría
 * a mantener dos formularios que se desincronizan. El selector de arriba cambia lo único
 * que cambia.
 *
 * **Pocos campos**: seis, y ninguno que se pueda deducir. El nombre de usuario sale del
 * correo, y las áreas de práctica, las jurisdicciones y la presentación se completan ya
 * dentro, donde hay sitio para explicarlas.
 *
 * El campo del nombre de la firma se muestra y se oculta con JavaScript, pero **la regla
 * de verdad está en el servidor**: `SignupService` exige el nombre cuando el tipo es
 * «firma», llegue como llegue el POST.
 *
 * @var string $stamp
 * @var bool   $hasGd
 * @var string $kind
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$k    = in_array(old('kind', $kind), ['lawyer', 'firm'], true) ? old('kind', $kind) : 'lawyer';
?>
<h1 style="margin:0 0 4px;font-size:26px"><?= esc(lang('Lfl.suTitle')) ?></h1>
<p class="ll-sub"><?= esc(lang('Lfl.suSub')) ?></p>

<?php if (session('dir_error')): ?>
  <div class="ll-alert ll-alert-bad"><?= esc(session('dir_error')) ?></div>
<?php endif; ?>

<div class="ll-split">
  <form class="ll-card ll-pad" method="post" action="<?= base_url('registro-legal') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">

    <div class="ll-hp" aria-hidden="true">
      <label><?= esc(lang('Lfl.pubHoneypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <fieldset style="border:0;padding:0;margin:0 0 16px">
      <legend class="ll-label" style="padding:0 0 8px"><?= esc(lang('Lfl.suKind')) ?></legend>
      <?php foreach (['lawyer' => ['suKindLawyer', 'suKindLawyerHint'], 'firm' => ['suKindFirm', 'suKindFirmHint']] as $code => [$rot, $hint]): ?>
        <label class="ll-card ll-pad ll-row" style="gap:10px;align-items:flex-start;margin-bottom:8px;cursor:pointer">
          <input type="radio" name="kind" value="<?= esc($code, 'attr') ?>" data-kind
            <?= $k === $code ? 'checked' : '' ?> style="margin-top:4px">
          <span>
            <strong><?= esc(lang('Lfl.' . $rot)) ?></strong><br>
            <span class="ll-small ll-muted"><?= esc(lang('Lfl.' . $hint)) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <div class="ll-search" style="grid-template-columns:1fr 1fr">
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.firstName')) ?> <span class="ll-req">*</span></span>
        <input class="ll-input" type="text" name="first_name" maxlength="50" required value="<?= esc(old('first_name'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.lastName')) ?> <span class="ll-req">*</span></span>
        <input class="ll-input" type="text" name="last_name" maxlength="50" required value="<?= esc(old('last_name'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.email')) ?> <span class="ll-req">*</span></span>
        <input class="ll-input" type="email" name="email" maxlength="120" required value="<?= esc(old('email'), 'attr') ?>">
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.phone')) ?></span>
        <input class="ll-input" type="tel" name="phone" maxlength="30" value="<?= esc(old('phone'), 'attr') ?>">
      </label>
    </div>

    <label class="ll-field" style="margin:10px 0 0" data-firm <?= $k === 'firm' ? '' : 'hidden' ?>>
      <span class="ll-label"><?= esc(lang('Lfl.suCompanyName')) ?> <span class="ll-req">*</span></span>
      <input class="ll-input" type="text" name="company_name" maxlength="120" value="<?= esc(old('company_name'), 'attr') ?>">
      <span class="ll-small ll-muted"><?= esc(lang('Lfl.suCompanyHint')) ?></span>
    </label>

    <div class="ll-search" style="grid-template-columns:1fr 1fr;margin-top:10px">
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.suPassword')) ?> <span class="ll-req">*</span></span>
        <input class="ll-input" type="password" name="password" minlength="8" required autocomplete="new-password">
        <span class="ll-small ll-muted"><?= esc(lang('Lfl.suPasswordHint')) ?></span>
      </label>
      <label class="ll-field">
        <span class="ll-label"><?= esc(lang('Lfl.suPasswordConfirm')) ?> <span class="ll-req">*</span></span>
        <input class="ll-input" type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
      </label>
    </div>

    <?php if ($hasGd): ?>
      <div class="ll-row" style="margin:14px 0;align-items:flex-end">
        <img src="<?= base_url('registro-legal/captcha') ?>" alt="" data-captcha hidden width="150" height="46"
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
        fetch(<?= json_encode(base_url('registro-legal/captcha')) ?>, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (d.img) { img.src = d.img; img.hidden = false; } })
          .catch(function () {});
      })();
      </script>
    <?php endif; ?>

    <p class="ll-small ll-muted" style="margin:0 0 14px"><?= esc(lang('Lfl.suTerms')) ?></p>

    <button class="ll-btn" type="submit"><?= $icon('spark') ?><?= esc(lang('Lfl.suSubmit')) ?></button>

    <script>
    // El nombre del despacho solo se pregunta a una firma. La regla de verdad está en el
    // servidor; esto es para no pedir lo que no hace falta.
    (function () {
      var campo = document.querySelector('[data-firm]');
      if (!campo) { return; }
      document.querySelectorAll('[data-kind]').forEach(function (r) {
        r.addEventListener('change', function () { campo.hidden = r.value !== 'firm' || !r.checked; });
      });
    })();
    </script>
  </form>

  <aside>
    <div class="ll-card ll-pad">
      <p style="margin:0 0 8px;font-weight:600"><?= esc(lang('Lfl.suWhatYouGet')) ?></p>
      <ul class="ll-list ll-small">
        <?php for ($i = 1; $i <= 4; $i++): ?>
          <li><?= esc(lang('Lfl.suBenefit' . $i)) ?></li>
        <?php endfor; ?>
      </ul>
      <p class="ll-small ll-muted" style="margin:12px 0 0"><?= esc(lang('Lfl.suDraftNote')) ?></p>
    </div>
    <p class="ll-small" style="margin:14px 0 0">
      <?= esc(lang('Lfl.suHaveAccount')) ?> <a href="<?= base_url('login') ?>"><?= esc(lang('Lfl.suLogin')) ?></a>
    </p>
  </aside>
</div>
<?= $this->endSection() ?>
