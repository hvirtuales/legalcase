<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * Acuse de la opinión.
 *
 * Dice lo único que falta por saber: **que todavía no está publicada** y que la revisa
 * la plataforma. Prometer que saldrá sería mentir —puede rechazarse— y callarlo haría
 * pensar que ya está en el perfil.
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>
<div class="ll-card ll-pad" style="max-width:660px;margin:0 auto;text-align:center">
  <p style="margin:0 0 10px;font-size:34px;color:var(--ll-gold)"><?= $icon('check') ?></p>
  <h1 style="margin:0 0 8px;font-size:24px"><?= esc(lang('Lfl.revThanksTitle')) ?></h1>
  <p class="ll-muted" style="margin:0 0 6px"><?= esc(lang('Lfl.revThanksBody')) ?></p>
  <p class="ll-small ll-muted" style="margin:0 0 18px"><?= esc(lang('Lfl.revThanksNext')) ?></p>
  <a class="ll-btn ll-btn-ghost" href="<?= base_url('/') ?>"><?= esc(lang('Lfl.dirBrand')) ?></a>
</div>
<p class="ll-legal" style="max-width:660px;margin:16px auto 0"><?= esc(lang('Lfl.revDisclaimer')) ?></p>
<?= $this->endSection() ?>
