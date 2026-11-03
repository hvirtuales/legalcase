<?php
/**
 * Índice público de las **vistas** de un grupo: `/directorio-publico/{grupo}`.
 *
 * Solo se llega aquí si el grupo tiene más de una vista publicada; con una sola
 * el controlador salta directo a ella.
 *
 * Autosuficiente (contrato base §7.3 y tenant §7): toda la hoja de estilos va
 * en línea, los iconos son SVG en línea y la tipografía es la del sistema. Ni
 * `tailwind.css`, ni `icons.css`, ni CDN. Aquí no hay mapa, así que tampoco se
 * carga Leaflet: cada vista lo carga por su cuenta si le hace falta.
 *
 * @var array $tenant
 * @var array $boards
 */

/**
 * Color de marca del **grupo**. Se valida y se emite **crudo**: `esc($hex,'css')`
 * produce `\23 …` y `esc($hex,'attr')` `&#x23;…`, y ninguno es un color válido.
 * La tinta se decide por luminancia para que el texto se lea sobre él.
 */
$hex = (string) ($tenant['brand_color'] ?? '');
if (!preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
    $hex = '#1f2937';
}
[$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
$ink = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) > 150 ? '#111827' : '#ffffff';

$base = rtrim(base_url(), '/') . '/directorio-publico/' . rawurlencode((string) $tenant['slug']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($tenant['name']) ?></title>
<meta name="description" content="<?= esc(mb_substr(trim((string) ($tenant['description'] ?? '')) ?: $tenant['name'], 0, 160), 'attr') ?>">
<style>
:root {
    --d-brand: <?= $hex ?>;
    --d-ink: <?= $ink ?>;
    --d-bg: #f6f7f9;
    --d-surface: #ffffff;
    --d-text: #111827;
    --d-muted: #6b7280;
    --d-line: #e5e7eb;
}
@media (prefers-color-scheme: dark) {
    :root {
        --d-bg: #0f1216;
        --d-surface: #171b21;
        --d-text: #e5e7eb;
        --d-muted: #9aa3af;
        --d-line: #2b323c;
    }
}
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
    background: var(--d-bg);
    color: var(--d-text);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.5;
}
a { color: inherit; text-decoration: none; }

.d-head { background: var(--d-brand); color: var(--d-ink); padding: 22px 18px; }
.d-head-in { display: flex; align-items: center; gap: 16px; max-width: 900px; margin: 0 auto; }
.d-logo { height: 54px; width: auto; max-width: 170px; object-fit: contain; flex: none; }
.d-name { margin: 0; font-size: 1.4rem; font-weight: 700; letter-spacing: -.01em; }
.d-desc { margin: 4px 0 0; font-size: .9rem; opacity: .9; }

.d-wrap { max-width: 900px; margin: 0 auto; padding: 22px 18px 40px; }
.d-lead { margin: 0 0 16px; font-size: .9rem; color: var(--d-muted); }

.d-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.d-item {
    display: block;
    padding: 18px;
    border: 1px solid var(--d-line);
    border-radius: 12px;
    background: var(--d-surface);
    transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
}
.d-item:hover {
    border-color: var(--d-brand);
    box-shadow: 0 6px 20px rgba(15, 23, 42, .1);
    transform: translateY(-1px);
}
.d-item-title { margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.d-item-title svg { width: 17px; height: 17px; flex: none; color: var(--d-brand); }
.d-item-intro { margin: 6px 0 0; font-size: .85rem; color: var(--d-muted); }

.d-empty { color: var(--d-muted); font-size: .9rem; }
.d-foot { max-width: 900px; margin: 0 auto; padding: 0 18px 30px; font-size: .78rem; color: var(--d-muted); text-align: center; }
</style>
</head>
<body>

<header class="d-head">
    <div class="d-head-in">
        <?php if (!empty($tenant['logo'])): ?>
            <img class="d-logo" src="<?= esc(media_url($tenant['logo'])) ?>" alt="<?= esc($tenant['name'], 'attr') ?>">
        <?php endif; ?>
        <div>
            <h1 class="d-name"><?= esc($tenant['name']) ?></h1>
            <?php if (!empty($tenant['description'])): ?>
                <p class="d-desc"><?= esc($tenant['description']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="d-wrap">
    <?php if (!$boards): ?>
        <p class="d-empty"><?= lang('Dir.noPublicBoards') ?></p>
    <?php else: ?>
        <p class="d-lead"><?= lang('Dir.pickABoard') ?></p>
        <div class="d-list">
            <?php foreach ($boards as $b): ?>
                <a class="d-item" href="<?= $base . '/' . rawurlencode((string) $b['slug']) ?>">
                    <h2 class="d-item-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/></svg>
                        <?= esc($b['title']) ?>
                    </h2>
                    <?php if (!empty($b['intro'])): ?>
                        <p class="d-item-intro"><?= esc($b['intro']) ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer class="d-foot">
    <?= esc($tenant['name']) ?>
    <?php if (!empty($tenant['city'])): ?> · <?= esc($tenant['city']) ?><?php endif; ?>
</footer>

</body>
</html>
