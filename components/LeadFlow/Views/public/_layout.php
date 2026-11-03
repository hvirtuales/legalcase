<?php
/**
 * Lienzo de las páginas que ve el PROSPECTO. **Autosuficiente** (contrato §7.3):
 * no carga `tailwind.css`, `icons.css` ni `fonts.css` del núcleo — todo el estilo
 * va aquí y los iconos son SVG en línea (`_icons.php`) —, tipografía del sistema y
 * cero CDN. El color de marca de la firma se valida con un patrón y se emite crudo:
 * `esc($hex,'css'|'attr')` produciría un color inválido.
 *
 * @var string $pageTitle
 * @var string $brand     hex ya validado
 */
$brandHex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($brand ?? '')) ? $brand : '#1f2937';
$r = hexdec(substr($brandHex, 1, 2));
$g = hexdec(substr($brandHex, 3, 2));
$b = hexdec(substr($brandHex, 5, 2));
// Tinta por luminancia: sobre una marca clara, el blanco no se leería.
$inkHex = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111827' : '#ffffff';
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
            --lf-bg: #f4f5f7; --lf-surface: #ffffff; --lf-ink: #111827; --lf-muted: #6b7280; --lf-line: #d9dde3;
            --lf-brand: <?= $brandHex ?>; --lf-brand-ink: <?= $inkHex ?>; --lf-danger: #b42318; --lf-ok: #067647;
        }
        @media (prefers-color-scheme: dark) {
            :root { --lf-bg: #0b0f19; --lf-surface: #151a26; --lf-ink: #f3f4f6; --lf-muted: #9aa3b2; --lf-line: #2a3346; }
        }
        [hidden] { display: none !important; }
        html, body { margin: 0; padding: 0; }
        body { background: var(--lf-bg); color: var(--lf-ink); line-height: 1.5;
               font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; display: block; }
        a { color: var(--lf-brand); }
        button, input, select, textarea { font: inherit; color: inherit; }
        .lf-i { display: inline-flex; vertical-align: -2px; }
        .lf-i > svg { width: 1em; height: 1em; display: block; }

        .lf-top { background: var(--lf-brand); color: var(--lf-brand-ink); padding: 22px 0; }
        .lf-top-in, .lf-wrap { max-width: 44rem; margin: 0 auto; padding: 0 16px; }
        .lf-top-in { display: flex; align-items: center; gap: 14px; }
        .lf-logo { height: 44px; width: auto; max-width: 120px; object-fit: contain; background: #fff; border-radius: 8px; padding: 4px; }
        .lf-top h1 { margin: 0; font-size: 19px; font-weight: 800; }
        .lf-top p { margin: 2px 0 0; font-size: 12.5px; opacity: .9; }
        .lf-wrap { padding-bottom: 48px; }

        .lf-card { background: var(--lf-surface); border: 1px solid var(--lf-line); border-radius: 12px; padding: 22px; margin-top: 18px; }
        .lf-card h2 { margin: 0 0 4px; font-size: 16px; font-weight: 800; }
        .lf-hint { margin: 0 0 16px; font-size: 13px; color: var(--lf-muted); }
        /* min(100%, 200px): en una pantalla más estrecha que dos columnas, una sola sin desbordar. */
        .lf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 14px; }
        .lf-field label.lf-label { display: block; font-size: 12px; font-weight: 700; color: var(--lf-muted); margin-bottom: 5px; }
        .lf-field input[type=text], .lf-field input[type=email], .lf-field input[type=tel], .lf-field input[type=number],
        .lf-field input[type=date], .lf-field input[type=file], .lf-field select, .lf-field textarea {
            width: 100%; padding: 10px 12px; background: var(--lf-surface); border: 1px solid var(--lf-line); border-radius: 8px; appearance: none; }
        .lf-field select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
        .lf-field input:focus, .lf-field select:focus, .lf-field textarea:focus { outline: 0; border-color: var(--lf-brand);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--lf-brand) 20%, transparent); }
        .lf-field.lf-wide { grid-column: 1 / -1; }
        .lf-help { margin: 4px 0 0; font-size: 12px; color: var(--lf-muted); }
        .lf-req { color: var(--lf-danger); }
        .lf-opts { display: flex; flex-wrap: wrap; gap: 8px 18px; }
        .lf-opt { display: inline-flex; align-items: center; gap: 7px; font-size: 14px; cursor: pointer; }
        .lf-check { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; margin-bottom: 10px; }
        .lf-check input { margin-top: 3px; flex-shrink: 0; }
        .lf-legal { border: 1px solid var(--lf-line); border-radius: 10px; padding: 14px; font-size: 12.5px; color: var(--lf-muted); white-space: pre-line; margin-bottom: 12px; }

        .lf-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border-radius: 8px;
                  border: 1px solid var(--lf-brand); background: var(--lf-brand); color: var(--lf-brand-ink); font-weight: 700; cursor: pointer; text-decoration: none; }
        .lf-btn:hover { filter: brightness(1.08); }
        .lf-alert { border-radius: 10px; padding: 12px 14px; font-size: 13px; margin-top: 14px; border: 1px solid var(--lf-danger); color: var(--lf-danger);
                    background: color-mix(in srgb, var(--lf-danger) 8%, transparent); }
        .lf-alert ul { margin: 0; padding-left: 18px; }
        .lf-ok { text-align: center; }
        .lf-ok .lf-i { font-size: 44px; color: var(--lf-ok); }

        .lf-captcha { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
        .lf-captcha img { border: 1px solid var(--lf-line); border-radius: 8px; }
        .lf-captcha .lf-field { flex: 1; min-width: 140px; }
        .lf-refresh { border: 1px solid var(--lf-line); background: var(--lf-surface); color: var(--lf-muted); border-radius: 8px; padding: 9px 11px; cursor: pointer; }
        .lf-trap { position: absolute; left: -9999px; opacity: 0; height: 0; overflow: hidden; }
        .lf-foot { text-align: center; font-size: 12px; color: var(--lf-muted); padding: 22px 16px 30px; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>
    <?= $this->renderSection('content') ?>
</body>
</html>
