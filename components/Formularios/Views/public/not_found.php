<?php
/**
 * 404 del componente, con su propio lienzo.
 *
 * No usa el del núcleo porque la vista pública es autosuficiente (contrato 7.3)
 * y su error también: si la carpeta se lleva a otra instalación, este 404 se
 * sigue viendo igual.
 *
 * NO distingue entre «no existe», «está en borrador», «está cerrado» y «el plan
 * no lo incluye»: distinguirlos convertiría la URL en una sonda de lo que tiene
 * contratado cada empresa.
 */

use Components\Formularios\Libraries\Icons;
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= esc(lang('Frm.pubNotAvailable')) ?></title>
<?= view('Components\Formularios\Views\public\_styles', ['brand' => '#2563eb', 'ink' => '#ffffff']) ?>
</head>
<body>
<main class="ff-wrap">
  <div class="ff-card">
    <div class="ff-accent"></div>
    <div class="ff-state">
      <?= Icons::svg('lock') ?>
      <h1><?= esc(lang('Frm.pubNotAvailable')) ?></h1>
      <p><?= esc(lang('Frm.pubClosed')) ?></p>
    </div>
  </div>
</main>
</body>
</html>
