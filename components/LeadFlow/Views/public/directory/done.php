<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * Acuse de la consulta.
 *
 * Dice **qué pasa ahora y quién va a responder**, que es lo único que quiere saber
 * quien acaba de contar su problema a un desconocido. No promete plazos que la
 * plataforma no controla: el que contesta es el abogado.
 *
 * @var array $profile
 */
$icon = static fn (string $n) => view('Components\LeadFlow\Views\public\directory\_icons', ['name' => $n]);
?>
<div class="ll-card ll-pad" style="max-width:660px;margin:0 auto;text-align:center">
  <p style="margin:0 0 10px;font-size:34px;color:var(--ll-gold)"><?= $icon('check') ?></p>
  <h1 style="margin:0 0 8px;font-size:24px"><?= esc(lang('Lfl.dirDoneTitle')) ?></h1>
  <p class="ll-muted" style="margin:0 0 6px"><?= esc(lang('Lfl.dirDoneBody', [$profile['display_name']])) ?></p>
  <p class="ll-small ll-muted" style="margin:0 0 18px"><?= esc(lang('Lfl.dirDoneNext')) ?></p>
  <a class="ll-btn ll-btn-ghost" href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirBackToSearch')) ?></a>
</div>
<p class="ll-legal" style="max-width:660px;margin:16px auto 0"><?= esc(lang('Lfl.dirDisclaimer')) ?></p>
<?= $this->endSection() ?>
