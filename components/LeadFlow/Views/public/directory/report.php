<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * El canal de denuncia.
 *
 * **Lo primero que dice es qué NO es.** Quien llega aquí suele venir enfadado, y la
 * diferencia entre «no me gustó cómo me atendieron» y «esta persona no es abogada»
 * decide si el aviso sirve de algo. Un canal que no lo explica se llena de quejas de
 * servicio que la plataforma no puede resolver, y las denuncias de verdad se pierden
 * entre ellas.
 *
 * **El contacto es opcional, a propósito.** Obligarlo dejaría fuera a quien teme
 * represalias, que es justo quien más necesita el canal.
 *
 * Y se dice claramente que **esto no se publica**: ni aquí ni en la ficha de nadie.
 *
 * @var array      $company
 * @var array|null $profile  el abogado señalado, si se entró desde su ficha
 * @var string     $stamp
 * @var bool       $hasGd
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
$url  = base_url('reportar/' . rawurlencode((string) $company['slug']))
    . ($profile ? '?abogado=' . rawurlencode((string) $profile['slug']) : '');

// Las categorías salen del modelo: una sola definición, como el resto del componente.
$categorias = \Components\LeadFlow\Models\ComplaintModel::CATEGORIES;
$minimo     = \Components\LeadFlow\Models\ComplaintModel::MIN_REASON;
?>
<h1 style="margin:0 0 4px;font-size:24px"><?= esc(lang('Lfl.repTitle')) ?></h1>
<p class="ll-sub">
  <?= esc($profile
      ? lang('Lfl.repSubProfile', [$profile['display_name'], $company['name']])
      : lang('Lfl.repSubFirm', [$company['name']])) ?>
</p>

<?php if (session('dir_error')): ?>
  <div class="ll-alert ll-alert-bad"><?= esc(session('dir_error')) ?></div>
<?php endif; ?>

<div class="ll-split">
  <form class="ll-card ll-pad" method="post" action="<?= esc($url, 'attr') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">

    <div class="ll-hp" aria-hidden="true">
      <label><?= esc(lang('Lfl.pubHoneypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <label class="ll-field" style="margin-bottom:14px">
      <span class="ll-label"><?= esc(lang('Lfl.repCategory')) ?> <span class="ll-req">*</span></span>
      <select class="ll-select" name="category" required>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= esc($c, 'attr') ?>" <?= old('category') === $c ? 'selected' : '' ?>>
            <?= esc(lang('Lfl.repCat_' . $c)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="ll-field" style="margin-bottom:6px">
      <span class="ll-label"><?= esc(lang('Lfl.repReason')) ?> <span class="ll-req">*</span></span>
      <textarea class="ll-textarea" name="reason" minlength="<?= (int) $minimo ?>" maxlength="2000" required
        placeholder="<?= esc(lang('Lfl.repReasonPh'), 'attr') ?>"><?= esc(old('reason')) ?></textarea>
    </label>
    <p class="ll-small ll-muted" style="margin:0 0 14px"><?= esc(lang('Lfl.repReasonHint', [$minimo])) ?></p>

    <label class="ll-field" style="margin-bottom:6px">
      <span class="ll-label"><?= esc(lang('Lfl.repContact')) ?></span>
      <input class="ll-input" type="text" name="contact" maxlength="150" value="<?= esc(old('contact'), 'attr') ?>">
    </label>
    <p class="ll-small ll-muted" style="margin:0 0 14px"><?= esc(lang('Lfl.repContactHint')) ?></p>

    <?php if ($hasGd): ?>
      <div class="ll-row" style="margin-bottom:14px;align-items:flex-end">
        <img src="<?= esc(base_url('reportar/' . rawurlencode((string) $company['slug']) . '/captcha'), 'attr') ?>"
          alt="" data-captcha hidden width="150" height="46"
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
        fetch(<?= json_encode(base_url('reportar/' . rawurlencode((string) $company['slug']) . '/captcha')) ?>,
          { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (d.img) { img.src = d.img; img.hidden = false; } })
          .catch(function () {});
      })();
      </script>
    <?php endif; ?>

    <?php // La declaración de veracidad no es un trámite: convierte el envío en una
          // afirmación de la que alguien responde. ?>
    <label class="ll-row" style="gap:9px;align-items:flex-start;margin-bottom:16px">
      <input type="checkbox" name="truthful" value="1" required style="margin-top:4px">
      <span class="ll-small"><?= esc(lang('Lfl.repTruthful')) ?></span>
    </label>

    <button class="ll-btn" type="submit"><?= $icon('send') ?><?= esc(lang('Lfl.repSubmit')) ?></button>
  </form>

  <aside>
    <div class="ll-card ll-pad">
      <p style="margin:0 0 8px;font-weight:600"><?= esc(lang('Lfl.repWhatTitle')) ?></p>
      <ul class="ll-list ll-small">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <li><?= esc(lang('Lfl.repWhat' . $i)) ?></li>
        <?php endfor; ?>
      </ul>

      <p style="margin:14px 0 8px;font-weight:600"><?= esc(lang('Lfl.repNotTitle')) ?></p>
      <ul class="ll-list ll-small ll-muted">
        <?php for ($i = 1; $i <= 2; $i++): ?>
          <li><?= esc(lang('Lfl.repNot' . $i)) ?></li>
        <?php endfor; ?>
      </ul>
      <p class="ll-small" style="margin:10px 0 0">
        <a href="<?= esc(base_url('firma/' . rawurlencode((string) $company['slug'])), 'attr') ?>">
          <?= esc(lang('Lfl.repBackToFirm')) ?>
        </a>
      </p>
    </div>
    <p class="ll-legal"><?= esc(lang('Lfl.repPrivacy')) ?></p>
  </aside>
</div>
<?= $this->endSection() ?>
