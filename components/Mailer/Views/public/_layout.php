<?php

/**
 * Lienzo de las pantallas PÚBLICAS del componente.
 *
 * Es AUTOSUFICIENTE a propósito (contrato §7.3): no carga `tailwind.css`,
 * `icons.css` ni `fonts.css` del núcleo, no enlaza ninguna hoja externa y no
 * llama a ningún CDN. Toda la hoja de estilos va aquí en línea y los iconos son
 * SVG en línea, así que la carpeta del componente se puede llevar a otra
 * plataforma —o servir esta página desde un servidor sin el panel— sin que se
 * rompa. La tipografía es la del sistema por la misma razón.
 *
 * Quien abre esto es alguien que recibió un correo, no un usuario con sesión:
 * la página tiene que pintarse entera con lo que trae.
 */

$titulo = $titulo ?? lang('Mailer.pubTitle');
?>
<!doctype html>
<html lang="<?= esc(lang('Mailer.htmlLang'), 'attr') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= esc($titulo) ?></title>
<style>
:root{
  --mu-bg:#f4f4f5; --mu-card:#ffffff; --mu-ink:#111827; --mu-soft:#6b7280;
  --mu-line:#e5e7eb; --mu-ok:#059669; --mu-warn:#b45309; --mu-bad:#dc2626;
  --mu-btn:#111827; --mu-btn-ink:#ffffff; --mu-field:#c7ccd4;
}
@media (prefers-color-scheme: dark){
  :root{
    --mu-bg:#0b0b0c; --mu-card:#161618; --mu-ink:#f4f4f5; --mu-soft:#a1a1aa;
    --mu-line:#27272a; --mu-ok:#34d399; --mu-warn:#fbbf24; --mu-bad:#f87171;
    --mu-btn:#f4f4f5; --mu-btn-ink:#111827; --mu-field:#3f3f46;
  }
}
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
  background:var(--mu-bg); color:var(--mu-ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  font-size:16px; line-height:1.5;
  min-height:100vh; display:flex; align-items:center; justify-content:center;
  padding:24px 16px;
}
.mu-card{
  width:100%; max-width:26rem; background:var(--mu-card);
  border:1px solid var(--mu-line); border-radius:14px;
  padding:32px 28px; text-align:center;
  box-shadow:0 1px 2px rgba(0,0,0,.04),0 8px 24px rgba(0,0,0,.06);
}
.mu-brand{margin:0 0 20px; display:flex; flex-direction:column; align-items:center; gap:8px}
.mu-brand img{max-width:150px; max-height:52px; height:auto; display:block}
.mu-brand span{font-size:.8125rem; color:var(--mu-soft)}
.mu-ico{display:block; margin:0 auto 14px; width:44px; height:44px}
.mu-ico.is-ok{color:var(--mu-ok)} .mu-ico.is-bad{color:var(--mu-bad)} .mu-ico.is-warn{color:var(--mu-warn)}
.mu-card h1{margin:0 0 8px; font-size:1.25rem; line-height:1.3; font-weight:600}
.mu-card p{margin:0 0 4px; font-size:.9375rem; color:var(--mu-soft)}
.mu-mail{color:var(--mu-ink); font-weight:600; word-break:break-all}
.mu-btn{
  -webkit-appearance:none; appearance:none; cursor:pointer;
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  margin-top:22px; width:100%; padding:12px 20px;
  background:var(--mu-btn); color:var(--mu-btn-ink);
  border:1px solid transparent; border-radius:9px;
  font:inherit; font-size:.9375rem; font-weight:600;
}
.mu-btn:hover{opacity:.88}
.mu-btn:focus-visible{outline:none; box-shadow:0 0 0 3px var(--mu-bg),0 0 0 5px var(--mu-btn)}
.mu-btn svg{width:18px; height:18px; flex:none}
.mu-foot{margin-top:18px; font-size:.75rem; color:var(--mu-soft)}

/* --- el formulario de alta --- */
.mu-form{margin-top:20px; text-align:left; display:flex; flex-direction:column; gap:4px}
.mu-form label{font-size:.8125rem; font-weight:600; margin-top:10px}
.mu-form input[type=email],.mu-form input[type=text]{
  -webkit-appearance:none; appearance:none; width:100%;
  padding:10px 12px; font:inherit; font-size:.9375rem;
  color:var(--mu-ink); background:var(--mu-card);
  /* Borde propio, más oscuro que el de un divisor: con el gris de las
     líneas el campo se difumina sobre el fondo de la tarjeta. */
  border:1px solid var(--mu-field); border-radius:8px;
}
.mu-form input:focus-visible{
  outline:none; border-color:var(--mu-btn);
  box-shadow:0 0 0 3px color-mix(in srgb, var(--mu-btn) 22%, transparent);
}
.mu-check{
  display:flex; align-items:flex-start; gap:10px; margin-top:18px;
  font-size:.875rem; font-weight:400; line-height:1.45; color:var(--mu-soft); cursor:pointer;
}
.mu-check input{flex:none; margin-top:3px; width:16px; height:16px; accent-color:var(--mu-btn)}
.mu-error{
  margin-top:14px; padding:10px 12px; border-radius:8px; font-size:.875rem;
  color:var(--mu-bad); background:color-mix(in srgb, var(--mu-bad) 12%, transparent);
  border:1px solid color-mix(in srgb, var(--mu-bad) 30%, transparent);
}
/* La trampa para robots: fuera de la vista sin usar `display:none`, que
   algunos la detectan y se la saltan. */
.mu-hp{position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden}

</style>
</head>
<body>
<main class="mu-card">
<?= $this->renderSection('content') ?>
</main>
</body>
</html>
