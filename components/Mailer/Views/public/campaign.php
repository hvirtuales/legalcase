<?php

/**
 * El correo, visto en el navegador.
 *
 * Es el clásico «¿no lo ves bien? ábrelo en el navegador», para el que abre
 * desde un cliente que le destroza la maqueta o le bloquea las imágenes.
 *
 * **No usa el layout público del componente** (el de la baja, que es una
 * tarjeta centrada): aquí lo que hay que enseñar es un correo de 600 px tal
 * como se envió, así que el envoltorio tiene que apartarse. Autosuficiente
 * igual (§7.3): CSS en línea, tipografía del sistema y cero recursos externos.
 *
 * El cuerpo se emite **sin escapar** a propósito: es el HTML que produjo
 * `EmailRenderer` en el servidor a partir del árbol ya saneado. Escaparlo
 * mostraría las etiquetas en pantalla.
 *
 * @var string $subject
 * @var string $body
 * @var array  $company
 */
$empresa = trim((string) ($company['name'] ?? ''));
?>
<!doctype html>
<html lang="<?= esc(lang('Mailer.htmlLang'), 'attr') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= esc($subject !== '' ? $subject : lang('Mailer.webViewTitle')) ?></title>
<style>
:root{ --wv-bg:#f4f4f5; --wv-bar:#ffffff; --wv-ink:#111827; --wv-soft:#6b7280; --wv-line:#e5e7eb; }
@media (prefers-color-scheme: dark){
  :root{ --wv-bg:#0b0b0c; --wv-bar:#161618; --wv-ink:#f4f4f5; --wv-soft:#a1a1aa; --wv-line:#27272a; }
}
*,*::before,*::after{ box-sizing:border-box }
html,body{ margin:0; padding:0 }
body{
  background:var(--wv-bg); color:var(--wv-ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  font-size:16px; line-height:1.5;
}
.wv-bar{
  display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;
  padding:.75rem 1.25rem; background:var(--wv-bar); border-bottom:1px solid var(--wv-line);
}
.wv-who{ display:flex; align-items:center; gap:.6rem; min-width:0 }
.wv-who img{ max-height:30px; max-width:130px; height:auto; display:block }
.wv-who span{ font-size:.85rem; font-weight:600; color:var(--wv-soft) }
.wv-subject{
  font-size:.85rem; color:var(--wv-soft);
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:min(100%,42ch);
}
/* El correo se pinta tal cual: nada de aquí puede tocarlo. */
.wv-mail{ width:100% }
</style>
</head>
<body>
<header class="wv-bar">
  <div class="wv-who">
    <?php if (!empty($company['logo'])): ?>
      <?php
      // Absoluta si ya lo es (S3/R2); si no, contra la propia instalación. Sin
      // `media_url()` del núcleo, que en otra plataforma podría no estar cargado.
      $logo = (string) $company['logo'];
      if (!preg_match('#^https?://#i', $logo)) {
          $logo = base_url(ltrim($logo, '/'));
      }
      ?>
      <img src="<?= esc($logo, 'attr') ?>" alt="<?= esc($empresa, 'attr') ?>">
    <?php elseif ($empresa !== ''): ?>
      <span><?= esc($empresa) ?></span>
    <?php endif; ?>
  </div>
  <div class="wv-subject"><?= esc($subject) ?></div>
</header>

<div class="wv-mail"><?= $body ?></div>
</body>
</html>
