<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * «Ya está»: qué hacer ahora.
 *
 * Dice **el usuario con el que se entra y los tres pasos que quedan**, porque quien
 * acaba de registrarse no tiene todavía ni idea de dónde está el botón de publicar — y
 * un perfil en borrador no aparece en ninguna parte hasta que lo pulsa.
 *
 * @var string $username
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>
<div class="ll-card ll-pad" style="max-width:660px;margin:0 auto">
  <p style="margin:0 0 10px;font-size:34px;color:var(--ll-gold);text-align:center"><?= $icon('check') ?></p>
  <h1 style="margin:0 0 8px;font-size:24px;text-align:center"><?= esc(lang('Lfl.suDoneTitle')) ?></h1>
  <p class="ll-muted" style="margin:0 0 16px;text-align:center"><?= esc(lang('Lfl.suDoneBody')) ?></p>

  <?php if ($username !== ''): ?>
    <p class="ll-card ll-pad" style="margin:0 0 16px;text-align:center">
      <span class="ll-small ll-muted"><?= esc(lang('Lfl.suYourUser')) ?></span><br>
      <strong style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:18px"><?= esc($username) ?></strong>
    </p>
  <?php endif; ?>

  <ol class="ll-list">
    <?php for ($i = 1; $i <= 3; $i++): ?>
      <li><?= esc(lang('Lfl.suNext' . $i)) ?></li>
    <?php endfor; ?>
  </ol>

  <p style="margin:18px 0 0;text-align:center">
    <a class="ll-btn" href="<?= base_url('login') ?>"><?= esc(lang('Lfl.suGoLogin')) ?></a>
  </p>
</div>
<p class="ll-legal" style="max-width:660px;margin:16px auto 0"><?= esc(lang('Lfl.dirDisclaimer')) ?></p>
<?= $this->endSection() ?>
