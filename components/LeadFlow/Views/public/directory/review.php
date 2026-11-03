<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * La opinión del cliente.
 *
 * **Se pregunta por la ATENCIÓN, no por el resultado del caso**: claridad, respuesta y
 * trato. Es la decisión que sostiene todo lo demás —una media de resultados sería una
 * promesa de resultados— y por eso se dice en la propia pantalla, para que quien escribe
 * sepa qué se le está preguntando.
 *
 * Y se avisa de dos cosas antes de escribir: que **lo revisa la plataforma** antes de
 * publicarse, y que puede **elegir cómo aparece firmada**. En un asunto legal hay gente
 * que no quiere su nombre publicado, y «Cliente verificado» es una opción de verdad.
 *
 * @var array      $invite
 * @var array|null $profile
 * @var array      $scores
 * @var string     $stamp
 * @var bool       $hasGd
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>
<h1 style="margin:0 0 4px;font-size:26px"><?= esc(lang('Lfl.revTitle')) ?></h1>
<p class="ll-sub">
  <?= esc($profile
      ? lang('Lfl.revSubWith', [$profile['display_name']])
      : lang('Lfl.revSub')) ?>
</p>

<?php if (session('dir_error')): ?>
  <div class="ll-alert ll-alert-bad"><?= esc(session('dir_error')) ?></div>
<?php endif; ?>

<div class="ll-split">
  <form class="ll-card ll-pad" method="post" action="<?= esc(base_url('opinion/' . rawurlencode((string) $invite['token'])), 'attr') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">

    <div class="ll-hp" aria-hidden="true">
      <label><?= esc(lang('Lfl.pubHoneypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <p class="ll-small ll-muted" style="margin:0 0 14px"><?= esc(lang('Lfl.revAboutService')) ?></p>

    <?php // Las tres notas, con el 5 a la derecha y sin ninguna marcada por defecto: una
          // opción preseleccionada sesga la respuesta. ?>
    <?php foreach ($scores as $field => $labelKey): ?>
      <fieldset style="border:0;padding:0;margin:0 0 14px">
        <legend class="ll-label" style="padding:0 0 6px">
          <?= esc(lang('Lfl.' . $labelKey)) ?> <span class="ll-req">*</span>
        </legend>
        <p class="ll-small ll-muted" style="margin:0 0 6px"><?= esc(lang('Lfl.' . $labelKey . 'Hint')) ?></p>
        <div class="ll-row" style="gap:14px">
          <?php for ($n = 1; $n <= 5; $n++): ?>
            <label class="ll-row" style="gap:5px">
              <input type="radio" name="<?= esc($field, 'attr') ?>" value="<?= (int) $n ?>" required
                <?= (int) old($field) === $n ? 'checked' : '' ?>>
              <span><?= (int) $n ?></span>
            </label>
          <?php endfor; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>

    <label class="ll-field" style="margin:0 0 14px">
      <span class="ll-label"><?= esc(lang('Lfl.revComment')) ?></span>
      <textarea class="ll-textarea" name="comment" maxlength="2000" placeholder="<?= esc(lang('Lfl.revCommentPh'), 'attr') ?>"><?= esc(old('comment')) ?></textarea>
      <span class="ll-small ll-muted"><?= esc(lang('Lfl.revCommentHint')) ?></span>
    </label>

    <fieldset style="border:0;padding:0;margin:0 0 14px">
      <legend class="ll-label" style="padding:0 0 6px"><?= esc(lang('Lfl.revAuthor')) ?></legend>
      <?php foreach (['first' => 'revAuthorFirst', 'full' => 'revAuthorFull', 'anon' => 'revAuthorAnon'] as $mode => $rot): ?>
        <label class="ll-row" style="gap:8px;margin-bottom:4px">
          <input type="radio" name="author_mode" value="<?= esc($mode, 'attr') ?>"
            <?= old('author_mode', 'first') === $mode ? 'checked' : '' ?>>
          <span class="ll-small"><?= esc(lang('Lfl.' . $rot)) ?></span>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <?php if ($hasGd): ?>
      <div class="ll-row" style="margin-bottom:14px;align-items:flex-end">
        <img src="<?= esc(base_url('opinion/' . rawurlencode((string) $invite['token']) . '/captcha'), 'attr') ?>" alt=""
          data-captcha hidden width="150" height="46"
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
        fetch(<?= json_encode(base_url('opinion/' . rawurlencode((string) $invite['token']) . '/captcha')) ?>,
          { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (d.img) { img.src = d.img; img.hidden = false; } })
          .catch(function () {});
      })();
      </script>
    <?php endif; ?>

    <label class="ll-row" style="gap:9px;align-items:flex-start;margin-bottom:16px">
      <input type="checkbox" name="consent" value="1" required style="margin-top:4px">
      <span class="ll-small"><?= esc(lang('Lfl.revConsent')) ?></span>
    </label>

    <button class="ll-btn" type="submit"><?= $icon('check') ?><?= esc(lang('Lfl.revSubmit')) ?></button>
  </form>

  <aside>
    <div class="ll-card ll-pad">
      <p style="margin:0 0 8px;font-weight:600"><?= esc(lang('Lfl.revHowTitle')) ?></p>
      <ul class="ll-list ll-small">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <li><?= esc(lang('Lfl.revHow' . $i)) ?></li>
        <?php endfor; ?>
      </ul>
    </div>
    <p class="ll-legal"><?= esc(lang('Lfl.revDisclaimer')) ?></p>
  </aside>
</div>
<?= $this->endSection() ?>
