<?php
/**
 * Lienzo de las páginas que ve el ciudadano. **Autosuficiente** (contrato §7.3):
 * no carga el CSS ni los iconos del núcleo — todo el estilo va aquí y los iconos
 * son SVG en línea (`_icons.php`)—, así el componente se puede llevar a otra
 * plataforma con su carpeta y nada más.
 *
 * Tipografía del sistema, cero CDN. El color de marca de la empresa sí se usa
 * (a diferencia del catálogo): aquí el ciudadano necesita reconocer a quién le
 * está escribiendo.
 *
 * @var string     $pageTitle
 * @var array|null $brand  ['base','dark','ink']
 */
$brand = $brand ?? ['base' => '#1f2937', 'dark' => '#111827', 'ink' => '#ffffff'];
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($pageTitle ?? '') ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --pq-bg: #f4f5f7;
            --pq-surface: #ffffff;
            --pq-ink: #111827;
            --pq-muted: #6b7280;
            --pq-line: #e5e7eb;
            --pq-brand: <?= $brand['base'] ?>;
            --pq-brand-dark: <?= $brand['dark'] ?>;
            --pq-brand-ink: <?= $brand['ink'] ?>;
            --pq-danger: #b42318;
            --pq-ok: #067647;
        }
        @media (prefers-color-scheme: dark) {
            :root { --pq-bg: #0b0f19; --pq-surface: #151a26; --pq-ink: #f3f4f6; --pq-muted: #9aa3b2; --pq-line: #2a3346; }
        }

        html, body { margin: 0; padding: 0; }
        body {
            background: var(--pq-bg); color: var(--pq-ink); line-height: 1.5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        img { max-width: 100%; display: block; }
        a { color: var(--pq-brand); }
        button { font: inherit; }

        .pq-i { display: inline-flex; }
        .pq-i > svg { width: 1em; height: 1em; display: block; }

        .pq-wrap { max-width: 44rem; margin: 0 auto; padding: 0 16px 48px; }

        /* Cabecera con la marca de la empresa */
        .pq-top { background: linear-gradient(135deg, var(--pq-brand) 0%, var(--pq-brand-dark) 100%);
                  color: var(--pq-brand-ink); padding: 22px 0; }
        .pq-top-in { max-width: 44rem; margin: 0 auto; padding: 0 16px; display: flex; align-items: center; gap: 14px; }
        .pq-logo { height: 44px; width: auto; max-width: 120px; object-fit: contain;
                   background: #fff; border-radius: 8px; padding: 4px; }
        .pq-top h1 { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -.01em; }
        .pq-top p { margin: 2px 0 0; font-size: 12.5px; opacity: .88; }

        .pq-card { background: var(--pq-surface); border: 1px solid var(--pq-line); border-radius: 12px;
                   padding: 22px; margin-top: 18px; }
        .pq-card h2 { margin: 0 0 4px; font-size: 16px; font-weight: 800; }
        .pq-card p.pq-hint { margin: 0 0 16px; font-size: 13px; color: var(--pq-muted); }

        .pq-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
        .pq-field label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .08em;
                          text-transform: uppercase; color: var(--pq-muted); margin-bottom: 5px; }
        .pq-field input, .pq-field select, .pq-field textarea {
            width: 100%; padding: 10px 12px; font: inherit; color: inherit; background: var(--pq-surface);
            border: 1px solid var(--pq-line); border-radius: 8px;
        }
        .pq-field input:focus, .pq-field select:focus, .pq-field textarea:focus {
            outline: 0; border-color: var(--pq-brand); box-shadow: 0 0 0 3px color-mix(in srgb, var(--pq-brand) 18%, transparent);
        }
        .pq-field.pq-wide { grid-column: 1 / -1; }
        .pq-req { color: var(--pq-danger); }

        /* Elección identificarme / anónima */
        .pq-modes { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 18px; }
        .pq-mode { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; cursor: pointer;
                   border: 1px solid var(--pq-line); border-radius: 10px; background: var(--pq-surface); }
        .pq-mode input { margin-top: 3px; }
        .pq-mode b { display: block; font-size: 13.5px; }
        .pq-mode span { display: block; font-size: 12px; color: var(--pq-muted); }
        .pq-mode.is-on { border-color: var(--pq-brand); box-shadow: 0 0 0 1px var(--pq-brand); }
        @media (max-width: 520px) { .pq-modes { grid-template-columns: 1fr; } }

        /* Consentimientos */
        .pq-check { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 12px; font-size: 13px; }
        .pq-check input { margin-top: 3px; flex-shrink: 0; }
        .pq-legal { border: 1px solid var(--pq-line); border-radius: 10px; padding: 14px; background: color-mix(in srgb, var(--pq-line) 22%, transparent); }

        .pq-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                  padding: 12px 22px; border-radius: 8px; border: 1px solid var(--pq-brand);
                  background: var(--pq-brand); color: var(--pq-brand-ink); font-size: 14px; font-weight: 700;
                  cursor: pointer; text-decoration: none; }
        .pq-btn:hover { filter: brightness(1.08); }
        .pq-btn-ghost { background: var(--pq-surface); color: var(--pq-ink); border-color: var(--pq-line); }

        .pq-alert { border-radius: 10px; padding: 12px 14px; font-size: 13px; margin-top: 14px;
                    border: 1px solid var(--pq-danger); color: var(--pq-danger);
                    background: color-mix(in srgb, var(--pq-danger) 8%, transparent); }
        .pq-note { border: 1px solid var(--pq-line); border-radius: 10px; padding: 12px 14px;
                   font-size: 13px; color: var(--pq-muted); }

        /* Captcha */
        .pq-captcha { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
        .pq-captcha img { border: 1px solid var(--pq-line); border-radius: 8px; }
        .pq-captcha .pq-field { flex: 1; min-width: 140px; }
        .pq-refresh { border: 1px solid var(--pq-line); background: var(--pq-surface); color: var(--pq-muted);
                      border-radius: 8px; padding: 9px 11px; cursor: pointer; }
        .pq-refresh:hover { color: var(--pq-ink); }
        /* La trampa: fuera de la vista y del tabulador, pero rellenable por un bot */
        .pq-trap { position: absolute; left: -9999px; opacity: 0; height: 0; overflow: hidden; }

        /* Ticket */
        .pq-ref { display: inline-block; margin: 6px 0 14px; padding: 8px 16px; border-radius: 8px;
                  border: 1px dashed var(--pq-brand); font-size: 18px; font-weight: 800; letter-spacing: .04em; }
        .pq-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px;
                    font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
                    background: color-mix(in srgb, var(--pq-brand) 12%, transparent); color: var(--pq-brand); }
        .pq-link { display: flex; gap: 8px; align-items: center; margin-top: 6px; }
        .pq-link input { flex: 1; min-width: 0; padding: 9px 11px; font-size: 12px; font-family: ui-monospace, monospace;
                         border: 1px solid var(--pq-line); border-radius: 8px; background: var(--pq-surface); color: inherit; }

        /* Soportes que la empresa comparte al responder */
        .pq-files { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--pq-line); }
        .pq-files-t { margin: 0 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .08em;
                      text-transform: uppercase; color: var(--pq-muted); }
        .pq-file { display: flex; align-items: center; gap: 9px; padding: 9px 12px; margin-bottom: 6px;
                   border: 1px solid var(--pq-line); border-radius: 8px; font-size: 13.5px;
                   text-decoration: none; color: var(--pq-ink); word-break: break-word; }
        .pq-file:hover { border-color: var(--pq-brand); color: var(--pq-brand); }
        .pq-file .pq-i { color: var(--pq-brand); flex-shrink: 0; }

        .pq-thread { list-style: none; margin: 14px 0 0; padding: 0; }
        .pq-thread li { border-left: 2px solid var(--pq-line); padding: 0 0 16px 14px; position: relative; }
        .pq-thread li::before { content: ''; position: absolute; left: -5px; top: 4px; width: 8px; height: 8px;
                                border-radius: 50%; background: var(--pq-brand); }
        .pq-thread time { display: block; font-size: 11px; color: var(--pq-muted); }
        .pq-thread p { margin: 3px 0 0; font-size: 13.5px; white-space: pre-line; }

        .pq-foot { text-align: center; font-size: 12px; color: var(--pq-muted); padding: 22px 16px 30px; }

        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>
    <?= $this->renderSection('content') ?>
</body>
</html>
