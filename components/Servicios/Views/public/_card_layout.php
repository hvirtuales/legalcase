<?php
/**
 * Lienzo de la tarjeta digital pública. **Autosuficiente a propósito.**
 *
 * A diferencia del resto de las vistas del componente, esta NO carga el CSS del
 * núcleo (`tailwind.css`, `icons.css`, `fonts.css`) ni usa sus clases: todo el
 * estilo vive en el `<style>` de abajo y los iconos son SVG en línea
 * (`_icons.php`). Así la tarjeta se puede llevar a otra plataforma copiando solo
 * la carpeta del componente, sin que se rompa la presentación.
 *
 * Tipografía: pila del sistema (ninguna fuente que descargar, cero CDN).
 *
 * @var string      $pageTitle
 * @var array       $palette   base/dark/ink de la marca de la empresa
 * @var array|null  $og        previsualización del enlace al compartirlo
 */
$palette = $palette ?? ['base' => '#1f2937', 'dark' => '#111827', 'ink' => '#ffffff'];
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= esc($pageTitle ?? '') ?></title>
    <?php if (!empty($og)): ?>
        <?php /* Previsualización al compartir el enlace (WhatsApp, Telegram, redes). */ ?>
        <meta property="og:type" content="profile">
        <meta property="og:title" content="<?= esc($og['title'] ?? $pageTitle, 'attr') ?>">
        <meta property="og:description" content="<?= esc($og['description'] ?? '', 'attr') ?>">
        <meta property="og:url" content="<?= esc($og['url'] ?? current_url(), 'attr') ?>">
        <?php if (!empty($og['image'])): ?>
            <meta property="og:image" content="<?= esc($og['image'], 'attr') ?>">
        <?php endif; ?>
        <meta name="twitter:card" content="<?= empty($og['image']) ? 'summary' : 'summary_large_image' ?>">
        <meta name="description" content="<?= esc($og['description'] ?? '', 'attr') ?>">
    <?php endif; ?>
    <style>
        /* ---------------------------------------------------------------
           Tarjeta digital — hoja de estilos completa del componente.
           Sin dependencias externas: ni framework, ni fuentes, ni iconos.
           --------------------------------------------------------------- */
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --dc-bg: #eef0f4;
            --dc-surface: #ffffff;
            --dc-surface-2: #f5f6f8;
            --dc-ink: #111827;
            --dc-muted: #6b7280;
            --dc-line: #e5e7eb;
            --dc-shadow: 0 25px 50px -12px rgb(0 0 0 / .28);
            --dc-brand: <?= $palette['base'] ?>;
            --dc-brand-dark: <?= $palette['dark'] ?>;
            --dc-brand-ink: <?= $palette['ink'] ?>;
            --dc-wa: #25d366;
        }

        /* La tarjeta se mira igual con el tema claro que con el oscuro. */
        @media (prefers-color-scheme: dark) {
            :root {
                --dc-bg: #0b0f19;
                --dc-surface: #161c2a;
                --dc-surface-2: #1e2534;
                --dc-ink: #f3f4f6;
                --dc-muted: #9aa3b2;
                --dc-line: #2a3346;
                --dc-shadow: 0 25px 50px -12px rgb(0 0 0 / .6);
            }
        }

        html, body { margin: 0; padding: 0; }
        body {
            min-height: 100vh; background: var(--dc-bg); color: var(--dc-ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased; line-height: 1.4;
        }
        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }
        button { font: inherit; color: inherit; background: none; border: 0; cursor: pointer; }

        /* Icono: hereda color y tamaño del contenedor. */
        .dc-i { display: inline-flex; }
        .dc-i > svg { width: 1em; height: 1em; display: block; }

        /* ----------------------------- escenario ----------------------- */
        .dc-stage { display: flex; align-items: center; justify-content: center;
                    min-height: 100vh; padding: 16px 16px 104px; }
        .dc-wrap  { width: min(100%, 22.5rem); perspective: 1600px; }

        /* CR80 de pie: 54 × 86 mm — la proporción de una tarjeta física. */
        .dc-card {
            position: relative; width: 100%; aspect-ratio: 54 / 86;
            transform-style: preserve-3d; transition: transform .8s cubic-bezier(.22,.61,.36,1);
        }
        .dc-card.is-flipped { transform: rotateY(180deg); }

        /* El giro se siente como una tarjeta que se voltea en la mano: mientras
           rota, se aleja un poco y la sombra se abre. La escala va en el
           envoltorio para no pelearse con el `rotateY` de la tarjeta. */
        .dc-wrap.is-turning { animation: dc-turn .8s cubic-bezier(.22,.61,.36,1); }
        @keyframes dc-turn {
            0%, 100% { transform: scale(1) translateY(0); }
            50%      { transform: scale(.93) translateY(-6px); }
        }
        .dc-card.is-turning .dc-face { box-shadow: 0 40px 60px -20px rgb(0 0 0 / .45); }
        .dc-face {
            position: absolute; inset: 0; display: flex; flex-direction: column; overflow: hidden;
            border-radius: 24px; background: var(--dc-surface); box-shadow: var(--dc-shadow);
            backface-visibility: hidden; -webkit-backface-visibility: hidden;
            transition: opacity 0s .35s;   /* el cruce ocurre a mitad del giro */
        }
        .dc-back { transform: rotateY(180deg); }
        /* Safari deja ver la cara de atrás solo con `backface-visibility`. */
        .dc-card:not(.is-flipped) .dc-back,
        .dc-card.is-flipped .dc-front { opacity: 0; pointer-events: none; }

        .dc-scroll { flex: 1; overflow-y: auto; scrollbar-width: none; }
        .dc-scroll::-webkit-scrollbar { display: none; }

        /* ----------------------------- frente -------------------------- */
        .dc-cover { position: relative; height: 112px; flex-shrink: 0;
                    background: linear-gradient(135deg, var(--dc-brand) 0%, var(--dc-brand-dark) 100%); }
        .dc-cover > img { width: 100%; height: 100%; object-fit: cover; }
        .dc-cover-fade { position: absolute; inset: 0;
                         background: linear-gradient(180deg, transparent 40%, rgb(0 0 0 / .18) 100%); }

        .dc-body { padding: 0 24px 24px; }
        /* La portada está posicionada: sin `position:relative` aquí se pintaría
           ENCIMA de la foto del vendedor. */
        .dc-avatar { position: relative; z-index: 1; margin: -40px 0 12px; }
        .dc-avatar img, .dc-avatar span {
            width: 80px; height: 80px; border-radius: 18px; object-fit: cover;
            border: 4px solid var(--dc-surface); box-shadow: 0 8px 20px rgb(0 0 0 / .18);
        }
        .dc-avatar span { display: flex; align-items: center; justify-content: center;
                          background: var(--dc-surface-2); color: var(--dc-muted); font-size: 28px; }

        .dc-name    { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -.02em; line-height: 1.15; }
        .dc-accent  { width: 48px; height: 6px; border-radius: 999px; background: var(--dc-brand); margin-top: 6px; }
        .dc-role    { margin: 8px 0 0; font-size: 11px; font-weight: 700; letter-spacing: .12em;
                      text-transform: uppercase; color: var(--dc-muted); }
        .dc-org     { margin: 2px 0 0; font-size: 12px; font-weight: 600; color: var(--dc-muted); }

        .dc-rows  { margin-top: 20px; display: flex; flex-direction: column; gap: 8px; }
        .dc-row   { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .dc-chip  { display: flex; align-items: center; justify-content: center; flex-shrink: 0;
                    width: 28px; height: 28px; border-radius: 8px; font-size: 13px;
                    background: var(--dc-surface-2); border: 1px solid var(--dc-line);
                    transition: transform .15s ease, background .15s ease, border-color .15s ease, color .15s ease; }
        /* Todas las fichas de contacto van con el color de la empresa y el icono
           en blanco. `--dc-brand-ink` es blanco salvo que la marca sea tan clara
           que el blanco no se leyera; ahí cae a tinta oscura. */
        .dc-rows .dc-chip { background: var(--dc-brand); border-color: var(--dc-brand); color: var(--dc-brand-ink); }
        /* Al pasar por encima hacen el camino inverso al de los iconos de redes:
           se levantan y el fondo pasa al gris neutro (el color en el que las
           redes están en reposo). */
        .dc-row:hover .dc-chip { transform: translateY(-2px) scale(1.1);
                                 background: var(--dc-surface-2); border-color: var(--dc-line);
                                 color: var(--dc-muted); }
        .dc-chip-brand { background: var(--dc-brand); border-color: var(--dc-brand); color: var(--dc-brand-ink); }
        .dc-chip-wa    { background: rgb(37 211 102 / .12); border-color: rgb(37 211 102 / .3); color: var(--dc-wa); }
        .dc-row span   { font-size: 12px; font-weight: 500; color: var(--dc-muted);
                         overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dc-row:hover span { color: var(--dc-ink); }

        .dc-socials { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
        /* En el reverso van centradas; el botón de girar ya no las estorba
           porque vive en la banda del pie. */
        .dc-socials-back { justify-content: center; margin-bottom: 4px; }
        .dc-social  { display: flex; align-items: center; justify-content: center; width: 34px; height: 34px;
                      border-radius: 10px; font-size: 15px; color: var(--dc-muted);
                      background: var(--dc-surface-2); border: 1px solid var(--dc-line);
                      transition: transform .15s ease, background .15s ease, color .15s ease; }
        .dc-social:hover { transform: translateY(-2px); background: var(--dc-brand); color: var(--dc-brand-ink);
                           border-color: var(--dc-brand); }

        /* Pie de la tarjeta: banda a sangre con el color de la empresa. El
           relleno derecho deja libre la esquina del botón de girar. */
        .dc-foot { position: relative; flex-shrink: 0; display: flex; align-items: center;
                   min-height: 56px; padding: 12px 68px 12px 24px;
                   background: var(--dc-brand); color: var(--dc-brand-ink);
                   font-size: 11.5px; line-height: 1.5; }
        /* Con pie, el botón de girar vive DENTRO de la banda: fuera quedaba
           montado a medias sobre ella. */
        .dc-foot .dc-flip { top: 50%; bottom: auto; right: 14px; transform: translateY(-50%); }
        .dc-foot .dc-flip:hover { transform: translateY(-50%) scale(1.1); }

        .dc-flip { position: absolute; right: 16px; bottom: 16px; z-index: 2;
                   display: flex; align-items: center; justify-content: center; width: 40px; height: 40px;
                   border-radius: 999px; font-size: 16px; color: var(--dc-muted);
                   background: var(--dc-surface); border: 1px solid var(--dc-line);
                   box-shadow: 0 6px 16px rgb(0 0 0 / .18); transition: transform .15s ease, color .15s ease; }
        .dc-flip:hover { transform: scale(1.1); color: var(--dc-ink); }
        .dc-flip:focus-visible { outline: 2px solid var(--dc-brand); outline-offset: 3px; }
        .dc-flip .dc-pulse { animation: dc-pulse 2s ease-in-out infinite; }
        @keyframes dc-pulse { 0%, 100% { opacity: 1 } 50% { opacity: .45 } }

        /* ----------------------------- reverso ------------------------- */
        /* Sin banda de color: la empresa se presenta con su logo sobre el mismo
           fondo de la tarjeta y una línea fina que hace de separador. */
        .dc-backbody { flex: 1; display: flex; flex-direction: column; align-items: center;
                       justify-content: center; text-align: center; padding: 28px 24px 20px; overflow-y: auto;
                       scrollbar-width: none; }
        .dc-backbody::-webkit-scrollbar { display: none; }

        .dc-orglogo { max-height: 104px; max-width: 260px; object-fit: contain; margin: 0 auto; }
        .dc-rule    { width: 56px; height: 1px; background: var(--dc-line); margin: 14px auto; }
        .dc-orgname { margin: 0; font-size: 16px; font-weight: 800; letter-spacing: -.01em; }
        .dc-orgbio  { margin: 6px 0 0; font-size: 12px; line-height: 1.6; color: var(--dc-muted); }

        .dc-qr { width: 144px; height: 144px; padding: 8px; border-radius: 12px;
                 background: #fff; border: 1px solid var(--dc-line); }
        .dc-scan { margin: 12px 0 0; font-size: 10px; font-weight: 700; letter-spacing: .12em;
                   text-transform: uppercase; color: var(--dc-muted); }
        .dc-web  { display: block; margin: 8px 0 0; font-size: 12px; font-weight: 600; color: var(--dc-brand); }

        .dc-hint { margin: 16px 0 0; text-align: center; font-size: 11px; font-weight: 500; color: var(--dc-muted); }

        /* ------------------------- barra de acciones -------------------- */
        .dc-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40;
                  background: color-mix(in srgb, var(--dc-surface) 94%, transparent);
                  border-top: 1px solid var(--dc-line);
                  padding-bottom: env(safe-area-inset-bottom); }
        @supports (backdrop-filter: blur(1px)) { .dc-bar { backdrop-filter: blur(16px); } }
        .dc-bar-in { display: flex; align-items: stretch; max-width: 32rem; margin: 0 auto; }
        .dc-act { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center;
                  gap: 4px; padding: 12px 4px; color: var(--dc-muted); transition: color .15s ease, transform .1s ease; }
        .dc-act:hover  { color: var(--dc-ink); }
        .dc-act:active { transform: scale(.95); }
        .dc-act .dc-i  { font-size: 18px; }
        .dc-act b { font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
                    line-height: 1.1; text-align: center; overflow-wrap: break-word; }

        /* ----------------------------- capas ---------------------------- */
        .dc-overlay { position: fixed; inset: 0; z-index: 50; display: none;
                      background: rgb(0 0 0 / .68); }
        @supports (backdrop-filter: blur(1px)) { .dc-overlay { backdrop-filter: blur(4px); } }
        .dc-overlay.is-open { display: flex; }
        .dc-modal { margin: auto; width: 100%; max-width: 20rem; padding: 32px; text-align: center;
                    border-radius: 24px; background: #fff; color: #111827; box-shadow: var(--dc-shadow); }
        .dc-modal img { margin: 0 auto; width: 100%; max-width: 220px; }
        .dc-modal strong { display: block; margin-top: 16px; font-size: 14px; font-weight: 800; }
        .dc-modal small  { display: block; margin-top: 4px; font-size: 12px; color: #9ca3af; }

        .dc-sheet-wrap { align-items: flex-end; justify-content: center; }
        .dc-sheet { width: 100%; max-width: 32rem; padding: 12px 24px 32px;
                    border-radius: 24px 24px 0 0; background: var(--dc-surface); box-shadow: var(--dc-shadow);
                    padding-bottom: calc(32px + env(safe-area-inset-bottom)); }
        .dc-grab  { width: 40px; height: 4px; border-radius: 999px; background: var(--dc-line); margin: 0 auto 16px; }
        .dc-sheet h2 { margin: 0 0 16px; font-size: 12px; font-weight: 800; letter-spacing: .12em;
                       text-transform: uppercase; color: var(--dc-muted); }
        .dc-opt { display: flex; align-items: center; gap: 12px; width: 100%; margin-bottom: 8px;
                  padding: 14px 16px; border-radius: 16px; text-align: left;
                  background: var(--dc-surface-2); border: 1px solid var(--dc-line); }
        .dc-opt:hover { filter: brightness(.97); }
        .dc-opt .dc-chip { width: 36px; height: 36px; border-radius: 12px; font-size: 16px; }
        .dc-opt b     { display: block; font-size: 14px; font-weight: 700; }
        .dc-opt small { display: block; font-size: 11px; color: var(--dc-muted);
                        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dc-opt div   { min-width: 0; flex: 1; }

        @media (prefers-reduced-motion: reduce) {
            .dc-card, .dc-flip, .dc-social, .dc-chip { transition: none; }
            .dc-flip .dc-pulse, .dc-wrap.is-turning { animation: none; }
        }

        /* Al imprimir: las dos caras, una debajo de la otra, sin cromo. */
        @media print {
            .dc-bar, .dc-overlay, .dc-hint, .dc-flip { display: none !important; }
            .dc-stage { min-height: 0; padding: 0; }
            .dc-card  { transform: none !important; aspect-ratio: auto; height: auto; }
            .dc-face  { position: static; transform: none !important; opacity: 1 !important;
                        box-shadow: none; border: 1px solid #ccc; margin-bottom: 8mm; aspect-ratio: 54 / 86; }
        }
    </style>
</head>
<body>
    <?= $this->renderSection('content') ?>
</body>
</html>
