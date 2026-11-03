<?= $this->extend('Components\LeadFlow\Views\public\directory\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * 404 del directorio.
 *
 * **No distingue entre «no existe», «está en borrador» y «su plan no incluye el
 * directorio»**: distinguirlos convertiría la URL en una sonda de quién ha contratado
 * qué. Y ofrece la salida, que es el buscador.
 */
?>
<div class="ll-card ll-empty" style="max-width:600px;margin:0 auto">
  <h1 style="margin:0 0 8px;font-size:22px"><?= esc(lang('Lfl.dirNotFoundTitle')) ?></h1>
  <p class="ll-muted" style="margin:0 0 18px"><?= esc(lang('Lfl.dirNotFoundBody')) ?></p>
  <a class="ll-btn" href="<?= base_url('buscar') ?>"><?= esc(lang('Lfl.dirFindTitle')) ?></a>
</div>
<?= $this->endSection() ?>
