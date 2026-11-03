<?php
/**
 * Hoja de estilos del formulario público, EN LINEA.
 *
 * Autosuficiencia (contrato 7.3): esta vista no carga `tailwind.css`, ni
 * `icons.css`, ni `fonts.css` del núcleo, ni un solo recurso externo. La prueba
 * de fuego es que si se borra todo `<link rel="stylesheet">` del HTML servido,
 * la página se sigue viendo bien, porque no hay ninguno.
 *
 * El color de marca llega ya VALIDADO (`FormService::hexColor()`) y se emite
 * CRUDO: `esc($hex, 'css')` produce `\23 ...` y `esc($hex, 'attr')` produce
 * `&#x23;...`, y ninguno de los dos es un color CSS válido. Es el gotcha
 * documentado del proyecto.
 *
 * OJO: este archivo ABRE y CIERRA su propio `<style>`. Sin el cierre el
 * navegador se traga como CSS todo lo que venga después: el `<body>` llega
 * VACIO al DOM y la página entera se ve en gris, tanto aquí como en el editor,
 * que incrusta esta misma hoja. No lo destapó ninguna comprobación de bytes con
 * curl —el marcado estaba ahí, sin parsear—; hizo falta abrirlo en un navegador.
 * `formularios:contrato` comprueba ahora el equilibrio de las etiquetas.
 *
 * @var string $brand color de acento ya validado
 * @var string $ink   tinta sobre el acento, decidida por luminancia
 */
?>
<style>
:root{
  --ff-brand: <?= $brand ?>;
  --ff-ink-on: <?= $ink ?>;
  --ff-bg:#f4f4f5; --ff-card:#ffffff; --ff-fg:#18181b; --ff-muted:#71717a;
  --ff-line:#e4e4e7; --ff-field:#d4d4d8; --ff-danger:#dc2626;
  --ff-radius:14px;
  --ff-font: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
@media (prefers-color-scheme: dark){
  :root{
    --ff-bg:#0b0f19; --ff-card:#111827; --ff-fg:#e5e7eb; --ff-muted:#9ca3af;
    --ff-line:#1f2937; --ff-field:#374151;
  }
}
*{box-sizing:border-box}
/* El atributo `hidden` tiene que ganar SIEMPRE. Sin esto, cualquier clase que
   declare `display` (`.ff-btn{display:inline-flex}`) lo anula, porque una regla
   de autor gana a la del navegador: en el paso 1 se veían a la vez «Anterior» y
   «Enviar», que el servidor había marcado como ocultos. */
[hidden]{display:none !important}
html,body{margin:0;padding:0}
body{
  background:var(--ff-bg); color:var(--ff-fg); font-family:var(--ff-font);
  line-height:1.55; -webkit-font-smoothing:antialiased;
}
.ff-wrap{max-width:720px; margin:0 auto; padding:28px 18px 64px}
.ff-card{
  background:var(--ff-card); border:1px solid var(--ff-line);
  border-radius:var(--ff-radius); overflow:hidden;
}
.ff-accent{height:6px; background:var(--ff-brand)}
.ff-head{padding:26px 26px 20px; border-bottom:1px solid var(--ff-line)}
.ff-brand{display:flex; align-items:center; gap:10px; margin-bottom:12px}
.ff-brand img{height:34px; width:auto; border-radius:6px}
.ff-brand span{font-size:.8rem; font-weight:600; color:var(--ff-muted); letter-spacing:.02em}
.ff-title{margin:0 0 6px; font-size:1.5rem; line-height:1.25; font-weight:700}
.ff-desc{margin:0; color:var(--ff-muted); font-size:.95rem}
.ff-body{padding:26px}
.ff-foot{
  padding:18px 26px; border-top:1px solid var(--ff-line);
  display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;
}
.ff-legal{display:flex; align-items:center; gap:6px; color:var(--ff-muted); font-size:.8rem}

/* --- Rejilla de campos: los anchos 100/50/33 de la app original ---------- */
.ff-grid{display:flex; flex-wrap:wrap; gap:18px; align-items:flex-start}
.ff-item{flex:1 1 100%; min-width:0}
@media (min-width:640px){
  .ff-w50{flex:0 1 calc(50% - 9px)}
  .ff-w33{flex:0 1 calc(33.333% - 12px)}
}
.ff-page-head{margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid var(--ff-line)}
.ff-step{
  display:inline-block; font-size:.72rem; font-weight:700; text-transform:uppercase;
  letter-spacing:.06em; color:var(--ff-muted); margin-bottom:4px;
}
.ff-page-title{margin:0; font-size:1.15rem; font-weight:700}
.ff-page-desc{margin:4px 0 0; color:var(--ff-muted); font-size:.9rem}
.ff-empty{color:var(--ff-muted); font-size:.9rem; font-style:italic}

/* --- Controles ----------------------------------------------------------- */
.ff-label{display:block; font-size:.875rem; font-weight:600; margin-bottom:6px}
.ff-req{color:var(--ff-danger)}
.ff-help{margin:6px 0 0; font-size:.8rem; color:var(--ff-muted)}
.ff-error{margin:6px 0 0; font-size:.8rem; color:var(--ff-danger); font-weight:600}
.ff-input{
  width:100%; padding:11px 13px; font:inherit; font-size:.95rem;
  color:var(--ff-fg); background:var(--ff-card);
  /* Sin `appearance:none` iOS pinta su propio marco encima del nuestro. */
  appearance:none; -webkit-appearance:none;
  border:1px solid var(--ff-field); border-radius:10px;
  transition:border-color .15s, box-shadow .15s;
}
.ff-input:focus{
  /* Halo en vez de `outline`: con outline + borde transparente el campo perdía
     su caja justo al enfocarlo. */
  outline:none; border-color:var(--ff-brand);
  box-shadow:0 0 0 3px color-mix(in srgb, var(--ff-brand) 25%, transparent);
}
.ff-textarea{min-height:110px; resize:vertical}
.ff-select{
  background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2371717a' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right 12px center; background-size:16px;
  padding-right:38px;
}
[data-ff-invalid] .ff-input{border-color:var(--ff-danger)}

/* --- Opciones ------------------------------------------------------------ */
.ff-choices{display:flex; flex-direction:column; gap:8px}
.ff-choice{
  display:flex; align-items:center; gap:10px; padding:11px 13px;
  border:1px solid var(--ff-line); border-radius:10px; cursor:pointer;
  font-size:.92rem; transition:border-color .15s, background .15s;
}
.ff-choice:hover{border-color:var(--ff-field)}
.ff-choice.is-on{border-color:var(--ff-brand); background:color-mix(in srgb, var(--ff-brand) 8%, transparent)}
.ff-choice input{accent-color:var(--ff-brand); width:17px; height:17px; flex:none}
.ff-switch{display:flex; align-items:center; gap:12px; cursor:pointer}
.ff-switch input[type=checkbox]{position:absolute; opacity:0; width:0; height:0}
.ff-switch-track{
  position:relative; flex:none; width:46px; height:26px; border-radius:999px;
  background:var(--ff-field); transition:background .18s;
}
.ff-switch-track::after{
  content:""; position:absolute; top:3px; left:3px; width:20px; height:20px;
  border-radius:50%; background:#fff; transition:transform .18s;
  box-shadow:0 1px 3px rgba(0,0,0,.3);
}
.ff-switch input:checked + .ff-switch-track{background:var(--ff-brand)}
.ff-switch input:checked + .ff-switch-track::after{transform:translateX(20px)}
.ff-switch-text{font-size:.92rem}

/* --- Calificación, NPS y deslizador -------------------------------------- */
.ff-ico{width:1em; height:1em; display:inline-block; vertical-align:-.125em}
.ff-rating{display:flex; align-items:center; gap:4px; flex-wrap:wrap}
.ff-star{
  background:none; border:0; padding:4px; cursor:pointer; color:var(--ff-field);
  font-size:30px; line-height:0; transition:transform .12s, color .12s;
}
.ff-star .ff-ico{width:30px; height:30px}
.ff-star:hover{transform:scale(1.12)}
.ff-star.is-on{color:#f59e0b; fill:#f59e0b}
.ff-star.is-on .ff-ico{fill:#f59e0b}
.ff-rating-text{margin-left:8px; font-size:.85rem; color:var(--ff-muted)}
.ff-nps-row{display:grid; grid-template-columns:repeat(11,1fr); gap:4px}
.ff-nps-btn{
  padding:10px 0; font:inherit; font-size:.85rem; font-weight:600; cursor:pointer;
  color:var(--ff-fg); background:var(--ff-card);
  border:1px solid var(--ff-line); border-radius:8px; transition:all .12s;
}
.ff-nps-btn:hover{border-color:var(--ff-field)}
.ff-nps-btn.is-on{color:#fff; border-color:transparent}
.ff-nps-low.is-on{background:#dc2626}
.ff-nps-mid.is-on{background:#f59e0b}
.ff-nps-high.is-on{background:#059669}
.ff-nps-legend{display:flex; justify-content:space-between; margin-top:6px; font-size:.75rem; color:var(--ff-muted)}
@media (max-width:520px){
  .ff-nps-row{grid-template-columns:repeat(6,1fr)}
}
.ff-slider-head{display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:.82rem; color:var(--ff-muted)}
.ff-slider-out{
  font-weight:700; font-size:.95rem; color:var(--ff-ink-on);
  background:var(--ff-brand); padding:2px 12px; border-radius:999px;
}
.ff-range{width:100%; accent-color:var(--ff-brand)}

/* --- Archivo y firma ----------------------------------------------------- */
.ff-file{display:block; cursor:pointer}
.ff-file input{position:absolute; opacity:0; width:0; height:0}
.ff-file-face{
  display:flex; flex-direction:column; align-items:center; gap:6px; text-align:center;
  padding:24px 16px; border:2px dashed var(--ff-field); border-radius:12px;
  transition:border-color .15s, background .15s;
}
.ff-file-face .ff-ico{width:26px; height:26px; color:var(--ff-muted)}
.ff-file:hover .ff-file-face{border-color:var(--ff-brand)}
.ff-file-text{font-size:.9rem; font-weight:600}
.ff-file-hint{font-size:.78rem; color:var(--ff-muted)}
.ff-file.is-picked .ff-file-face{border-style:solid; border-color:var(--ff-brand)}
.ff-sign{border:1px solid var(--ff-field); border-radius:12px; overflow:hidden}
.ff-sign-bar{
  display:flex; justify-content:space-between; align-items:center;
  padding:7px 12px; font-size:.78rem; color:var(--ff-muted);
  background:var(--ff-bg); border-bottom:1px solid var(--ff-line);
}
.ff-sign-clear{background:none; border:0; color:var(--ff-danger); font:inherit; font-size:.78rem; cursor:pointer}
.ff-sign-pad{display:block; width:100%; height:180px; background:#fff; touch-action:none; cursor:crosshair}

/* --- Estructura ---------------------------------------------------------- */
.ff-heading{margin:8px 0 0; font-weight:700}
.ff-h2{font-size:1.25rem} .ff-h3{font-size:1.1rem} .ff-h4{font-size:1rem}
.ff-divider{border:0; border-top:1px solid var(--ff-line); margin:10px 0; width:100%}
.ff-note{display:flex; gap:10px; padding:13px 15px; border-radius:10px; border-left:4px solid; font-size:.88rem}
.ff-note .ff-ico{width:18px; height:18px; flex:none; margin-top:2px}
.ff-note strong{display:block; margin-bottom:2px}
.ff-note-info{background:#eff6ff; border-color:#3b82f6; color:#1e3a8a}
.ff-note-warning{background:#fffbeb; border-color:#f59e0b; color:#78350f}
.ff-note-success{background:#ecfdf5; border-color:#10b981; color:#064e3b}
.ff-note-note{background:var(--ff-bg); border-color:var(--ff-field); color:var(--ff-fg)}
@media (prefers-color-scheme: dark){
  .ff-note-info{background:#1e293b; color:#bfdbfe}
  .ff-note-warning{background:#292524; color:#fde68a}
  .ff-note-success{background:#022c22; color:#a7f3d0}
}

/* --- Botones y barra de navegación --------------------------------------- */
.ff-btn{
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  padding:12px 22px; font:inherit; font-size:.95rem; font-weight:600;
  border:1px solid transparent; border-radius:999px; cursor:pointer;
  text-decoration:none; transition:filter .15s, background .15s;
}
.ff-btn:disabled{opacity:.5; cursor:not-allowed}
.ff-btn-main{background:var(--ff-brand); color:var(--ff-ink-on)}
.ff-btn-main:hover{filter:brightness(1.08)}
.ff-btn-ghost{background:transparent; color:var(--ff-fg); border-color:var(--ff-field)}
.ff-btn-ghost:hover{background:var(--ff-bg)}
.ff-nav{display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap}
.ff-progress{height:5px; background:var(--ff-line); border-radius:999px; overflow:hidden; margin:14px 0 0}
.ff-progress i{display:block; height:100%; background:var(--ff-brand); transition:width .25s}
.ff-steps{display:flex; gap:6px; flex-wrap:wrap; margin-top:12px}
.ff-pill{
  padding:4px 11px; font-size:.75rem; font-weight:600; border-radius:999px;
  border:1px solid var(--ff-line); color:var(--ff-muted); background:var(--ff-card);
}
.ff-pill.is-now{background:var(--ff-brand); color:var(--ff-ink-on); border-color:transparent}
.ff-pill.is-past{border-color:var(--ff-brand); color:var(--ff-brand)}

/* --- Avisos, captcha y consentimiento ------------------------------------ */
.ff-alert{padding:13px 16px; border-radius:10px; font-size:.9rem; margin-bottom:18px; border-left:4px solid}
.ff-alert-bad{background:#fef2f2; border-color:var(--ff-danger); color:#7f1d1d}
@media (prefers-color-scheme: dark){ .ff-alert-bad{background:#2a1215; color:#fecaca} }
.ff-captcha{display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; margin-top:6px}
.ff-captcha img{border:1px solid var(--ff-line); border-radius:8px}
.ff-captcha .ff-input{max-width:180px; text-transform:uppercase; letter-spacing:.18em}
.ff-consent{display:flex; gap:10px; align-items:flex-start; font-size:.85rem; color:var(--ff-muted); margin-top:18px}
.ff-consent input{margin-top:3px; accent-color:var(--ff-brand); width:17px; height:17px}
.ff-trap{position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden}

/* --- Pantallas de estado -------------------------------------------------- */
.ff-state{text-align:center; padding:56px 26px}
.ff-state .ff-ico{width:44px; height:44px; color:var(--ff-brand); margin-bottom:14px}
.ff-state h1{margin:0 0 8px; font-size:1.5rem}
.ff-state p{margin:0 auto 22px; max-width:38ch; color:var(--ff-muted)}
.ff-ref{
  display:inline-block; font-family:ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size:1.05rem; font-weight:700; padding:8px 18px; border-radius:10px;
  background:var(--ff-bg); border:1px solid var(--ff-line); margin-bottom:22px;
}

/* --- PRESENTACION A PANTALLA COMPLETA ------------------------------------- */
/* Misma hoja y mismo renderizador: cambia el envoltorio, no el formulario.
   `100dvh` y no `100vh` porque en el móvil la barra del navegador se recoge al
   desplazar y con `vh` el pie queda cortado o bailando. */
body.ff-full{background:var(--ff-card)}
body.ff-full .ff-wrap{max-width:none; margin:0; padding:0; min-height:100dvh; display:flex; flex-direction:column}
body.ff-full .ff-card{border:0; border-radius:0; flex:1; display:flex; flex-direction:column}
body.ff-full .ff-head{padding:22px clamp(18px,6vw,64px) 18px}
body.ff-full .ff-body{
  flex:1; overflow-y:auto; padding:clamp(22px,5vh,52px) clamp(18px,6vw,64px);
  display:flex; flex-direction:column; justify-content:center;
}
body.ff-full .ff-inner{width:100%; max-width:760px; margin:0 auto}
body.ff-full .ff-title{font-size:clamp(1.5rem,3.4vw,2.2rem)}
body.ff-full .ff-page-title{font-size:clamp(1.15rem,2.2vw,1.5rem)}
body.ff-full .ff-label{font-size:1rem}
body.ff-full .ff-input{padding:14px 16px; font-size:1rem}
body.ff-full .ff-foot{
  padding:16px clamp(18px,6vw,64px);
  padding-bottom:calc(16px + env(safe-area-inset-bottom));
  position:sticky; bottom:0; background:var(--ff-card);
}
.ff-fs-toggle{
  background:none; border:1px solid var(--ff-line); border-radius:999px;
  color:var(--ff-muted); cursor:pointer; padding:6px 10px; line-height:0;
}
.ff-fs-toggle .ff-ico{width:16px; height:16px}
.ff-fs-toggle:hover{color:var(--ff-fg); border-color:var(--ff-field)}
@media print{ .ff-foot, .ff-fs-toggle{display:none} }
</style>
