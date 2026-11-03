<?php
/**
 * Layout del portal del cliente. VISTA PÚBLICA AUTOSUFICIENTE (contrato §7.3): toda la
 * hoja de estilos en línea, SVG en línea, tipografía del sistema, variables propias
 * `--lc-*` y modo oscuro por `prefers-color-scheme`. Sin tailwind.css, icons.css ni CDN.
 * Diseño móvil primero.
 *
 * @var string $title @var ?array $account @var array $company @var array $flash @var string $content
 */
require_once __DIR__ . '/_icons.php';

$brand  = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($company['brand_color'] ?? '')) ? $company['brand_color'] : '#1f4e79';
$locale = service('request')->getLocale();
?>
<!DOCTYPE html>
<html lang="<?= esc($locale, 'attr') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= esc($title ?? lang('Lgc.ptBrand')) ?></title>
<style>
  :root{
    --lc-brand: <?= $brand ?>;
    --lc-bg:#f4f5f7; --lc-card:#ffffff; --lc-fg:#111827; --lc-muted:#6b7280; --lc-line:#e5e7eb;
    --lc-ok:#047857; --lc-ok-bg:#ecfdf5; --lc-err:#b91c1c; --lc-err-bg:#fef2f2; --lc-warn:#92400e; --lc-warn-bg:#fffbeb;
    --lc-radius:12px;
    --lc-font: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  }
  @media (prefers-color-scheme: dark){
    :root{ --lc-bg:#0b0f19; --lc-card:#111827; --lc-fg:#e5e7eb; --lc-muted:#9ca3af; --lc-line:#1f2937;
      --lc-ok:#6ee7b7; --lc-ok-bg:#052e22; --lc-err:#fca5a5; --lc-err-bg:#3b0d0d; --lc-warn:#fcd34d; --lc-warn-bg:#3a2a05; }
  }
  *{box-sizing:border-box}
  html{-webkit-text-size-adjust:100%}
  body{margin:0;background:var(--lc-bg);color:var(--lc-fg);font-family:var(--lc-font);font-size:16px;line-height:1.55}
  a{color:var(--lc-brand)}
  @media (prefers-color-scheme: dark){ a{color:#93c5fd} }
  img{display:block;max-width:100%}
  ul,ol{list-style:none;margin:0;padding:0}
  h1,h2,h3{margin:0;line-height:1.25}
  .lc-top{background:var(--lc-card);border-bottom:3px solid var(--lc-brand)}
  .lc-top-in{max-width:760px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .lc-firm{font-weight:700;font-size:1rem}
  .lc-wrap{max-width:760px;margin:0 auto;padding:16px}
  .lc-card{background:var(--lc-card);border:1px solid var(--lc-line);border-radius:var(--lc-radius);padding:16px;margin-bottom:16px}
  .lc-card h2{font-size:1.05rem;margin-bottom:10px;display:flex;align-items:center;gap:8px}
  .lc-title{font-size:1.35rem;margin:4px 0 12px}
  .lc-muted{color:var(--lc-muted);font-size:.875rem}
  .lc-small{font-size:.8rem}
  .lc-ico{width:20px;height:20px;flex-shrink:0;vertical-align:-4px}
  .lc-btn{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:10px 16px;border-radius:999px;border:0;background:var(--lc-brand);color:#fff;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
  .lc-btn-ghost{background:transparent;color:var(--lc-fg);border:1px solid var(--lc-line)}
  .lc-btn:focus-visible,.lc-in:focus-visible,a:focus-visible{outline:3px solid var(--lc-brand);outline-offset:2px}
  .lc-in{width:100%;min-height:44px;padding:10px 12px;border:1px solid var(--lc-line);border-radius:10px;background:var(--lc-card);color:var(--lc-fg);font:inherit}
  textarea.lc-in{min-height:110px;resize:vertical}
  label.lc-label{display:block;font-weight:600;font-size:.9rem;margin:10px 0 4px}
  .lc-alert{border-radius:10px;padding:10px 12px;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start}
  .lc-ok{background:var(--lc-ok-bg);color:var(--lc-ok)} .lc-err{background:var(--lc-err-bg);color:var(--lc-err)} .lc-warn{background:var(--lc-warn-bg);color:var(--lc-warn)}
  .lc-pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.78rem;font-weight:600;border:1px solid var(--lc-line)}
  .lc-stage{background:var(--lc-brand);color:#fff;border-color:transparent}
  .lc-list>li{padding:12px 0;border-top:1px solid var(--lc-line)} .lc-list>li:first-child{border-top:0;padding-top:0}
  .lc-sec{margin-top:14px} .lc-sec h3{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--lc-muted);margin-bottom:4px}
  .lc-pre{white-space:pre-line;margin:0}
  .lc-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between}
  .lc-msg{border:1px solid var(--lc-line);border-radius:10px;padding:10px;margin-top:8px}
  .lc-msg-out{background:var(--lc-bg)}
  details>summary{cursor:pointer;font-weight:600;min-height:44px;display:flex;align-items:center}
  .lc-skip{position:absolute;left:-999px} .lc-skip:focus{left:8px;top:8px;background:var(--lc-card);padding:8px;z-index:10}
  @media (prefers-reduced-motion: reduce){ *{transition:none!important;animation:none!important} }
</style>
</head>
<body>
<a class="lc-skip" href="#main"><?= esc(lang('Lgc.ptSkip')) ?></a>
<header class="lc-top">
  <div class="lc-top-in">
    <div class="lc-firm"><?= esc($company['name'] ?? lang('Lgc.ptBrand')) ?></div>
    <?php if (!empty($account)): ?>
      <form method="post" action="<?= esc(\Components\LegalCase\Libraries\Portal::url('salir'), 'attr') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="lc-btn lc-btn-ghost"><?= lgc_icon('out') ?> <?= esc(lang('Lgc.ptLogout')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</header>
<main id="main" class="lc-wrap">
  <?php if (!empty($flash['ok'])): ?><div class="lc-alert lc-ok" role="status"><?= lgc_icon('check') ?><span><?= esc($flash['ok']) ?></span></div><?php endif; ?>
  <?php if (!empty($flash['err'])): ?><div class="lc-alert lc-err" role="alert"><?= lgc_icon('info') ?><span><?= esc($flash['err']) ?></span></div><?php endif; ?>
  <?= $content ?>
</main>
</body>
</html>
