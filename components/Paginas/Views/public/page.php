<?php
/**
 * La página publicada. **Autosuficiente** (contrato §7.3): toda la hoja de
 * estilos va en línea, los iconos son SVG en línea, la tipografía es la del
 * sistema y no se carga nada del núcleo ni de ningún CDN. Prueba de fuego:
 * quitar todos los `<link rel="stylesheet">` del HTML servido no cambia nada,
 * porque no hay ninguno.
 *
 * El cuerpo (`$body`) ya viene renderizado por `Libraries\Renderer`, que es
 * quien escapa y valida. Aquí solo se monta el lienzo alrededor.
 *
 * @var array      $page      title, meta_title, meta_description, og_image
 * @var array|null $company   marca de la empresa (logo, nombre, ciudad, redes)
 * @var array      $settings  accent, width, header, footer
 * @var string     $body      HTML del árbol de bloques
 * @var bool|null  $isPreview vista previa desde el editor
 */
helper('media');

$title = trim((string) ($page['meta_title'] ?? '')) ?: (string) ($page['title'] ?? '');
$desc  = (string) ($page['meta_description'] ?? '');
$showHeader = ($settings['header'] ?? '1') !== '0' && !empty($company);
$showFooter = ($settings['footer'] ?? '1') !== '0' && !empty($company);

// El color de marca se emite crudo tras validarlo: `esc($hex,'css')` produce
// `\23 …` y `esc($hex,'attr')` `&#x23;…`, y ninguno es un color válido (§7.3).
$brand = '';
if (!empty($company['brand_color']) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $company['brand_color'])) {
    $brand = (string) $company['brand_color'];
}
// Si la página no fija acento propio, hereda el de la empresa.
if (empty($settings['accent']) && $brand !== '') {
    $settings['accent'] = $brand;
}
$ogImage = !empty($page['og_image']) ? media_url($page['og_image']) : '';
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= esc($title) ?></title>
    <?php if ($desc !== ''): ?>
        <meta name="description" content="<?= esc($desc, 'attr') ?>">
    <?php endif; ?>
    <?php if (!empty($isPreview)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= esc($title, 'attr') ?>">
    <meta property="og:description" content="<?= esc($desc, 'attr') ?>">
    <meta property="og:url" content="<?= esc(current_url(), 'attr') ?>">
    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?= esc($ogImage, 'attr') ?>">
        <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>
    <style>
        html, body { margin: 0; padding: 0; }
        body { background: #fff; }
        @media (prefers-color-scheme: dark) { body { background: #14161a; } }

        /* Cabecera y pie de la página publicada (la marca de la empresa). Van
           aquí y no en _styles.php porque no son parte del contenido: el editor
           no los pinta, solo se ven en la web publicada. */
        .pgw-bar {
            border-bottom: 1px solid var(--pg-line);
            background: var(--pg-bg);
        }
        .pgw-bar-in, .pgw-foot-in {
            max-width: var(--pg-max); margin: 0 auto;
            padding: 14px 20px; display: flex; align-items: center; gap: 12px;
        }
        .pgw-logo { height: 40px; width: auto; max-width: 160px; object-fit: contain; }
        .pgw-name { font-weight: 700; font-size: 1.05rem; letter-spacing: -.01em; }
        .pgw-foot {
            border-top: 1px solid var(--pg-line); margin-top: 40px;
            color: var(--pg-muted); font-size: .85rem;
        }
        .pgw-foot-in { flex-wrap: wrap; justify-content: space-between; }
        .pgw-social { display: flex; gap: 10px; align-items: center; }
        .pgw-social a { color: var(--pg-muted); display: inline-flex; }
        .pgw-social a:hover { color: var(--pg-accent); }
        .pgw-social svg { width: 18px; height: 18px; display: block; }

        /* Aviso de previsualización: no debe confundirse con la página real. */
        .pgw-preview {
            position: sticky; top: 0; z-index: 20;
            background: #1f2937; color: #fff; font-size: .82rem; font-weight: 600;
            padding: 8px 20px; text-align: center;
            /* El @font-face de Inter lo declara _styles.php, más abajo en este
               mismo <style>: el orden no importa para las fuentes. */
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }
        .pgw-preview a { color: #93c5fd; }

<?= $this->include('Components\Paginas\Views\public\_styles') ?>
    </style>
</head>
<body>
<div class="pg-root">
    <?php if (!empty($isPreview)): ?>
        <div class="pgw-preview"><?= esc(lang('Pag.previewNotice')) ?></div>
    <?php endif; ?>

    <?php if ($showHeader): ?>
        <header class="pgw-bar">
            <div class="pgw-bar-in">
                <?php if (!empty($company['logo'])): ?>
                    <img class="pgw-logo" src="<?= esc(media_url($company['logo']), 'attr') ?>"
                        alt="<?= esc((string) ($company['name'] ?? ''), 'attr') ?>">
                <?php else: ?>
                    <span class="pgw-name"><?= esc((string) ($company['name'] ?? '')) ?></span>
                <?php endif; ?>
            </div>
        </header>
    <?php endif; ?>

    <main><?= $body ?></main>

    <?php if ($showFooter): ?>
        <?php $icons = require __DIR__ . '/_icons.php'; ?>
        <footer class="pgw-foot">
            <div class="pgw-foot-in">
                <span>
                    © <?= esc(date('Y')) ?> <?= esc((string) ($company['name'] ?? '')) ?>
                    <?php if (!empty($company['nit'])): ?> · NIT <?= esc((string) $company['nit']) ?><?php endif; ?>
                    <?php if (!empty($company['city'])): ?> · <?= esc((string) $company['city']) ?><?php endif; ?>
                </span>
                <?php
                $networks = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
                $links    = array_filter($networks, static fn ($n) => !empty($company[$n]));
                ?>
                <?php if ($links): ?>
                    <span class="pgw-social">
                        <?php foreach ($links as $n): ?>
                            <a href="<?= esc((string) $company[$n], 'attr') ?>" target="_blank"
                                rel="noopener noreferrer" aria-label="<?= esc(ucfirst($n), 'attr') ?>">
                                <?= $icons[$n] ?? '' ?>
                            </a>
                        <?php endforeach; ?>
                    </span>
                <?php endif; ?>
            </div>
        </footer>
    <?php endif; ?>
</div>
</body>
</html>
