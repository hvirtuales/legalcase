<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * Acuse de la oportunidad.
 *
 * Dice **cuántos despachos pueden escribirle y que no tiene que hacer nada más**, que es
 * lo que no sabe quien acaba de describir su caso a nadie en concreto. No promete plazos:
 * quien contesta es un abogado, y la plataforma no controla cuándo.
 *
 * @var int $maxClaims
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>
<div class="ll-card ll-pad" style="max-width:660px;margin:0 auto;text-align:center">
  <p style="margin:0 0 10px;font-size:34px;color:var(--ll-gold)"><?= $icon('check') ?></p>
  <h1 style="margin:0 0 8px;font-size:24px"><?= esc(lang('Lfl.oppDoneTitle')) ?></h1>
  <p class="ll-muted" style="margin:0 0 6px"><?= esc(lang('Lfl.oppDoneBody', [$maxClaims])) ?></p>
  <p class="ll-small ll-muted" style="margin:0 0 18px"><?= esc(lang('Lfl.oppDoneNext')) ?></p>
  <a class="ll-btn ll-btn-ghost" href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.oppDoneMeanwhile')) ?></a>
</div>
<p class="ll-legal" style="max-width:660px;margin:16px auto 0"><?= esc(lang('Lfl.dirDisclaimer')) ?></p>
<?= $this->endSection() ?>
