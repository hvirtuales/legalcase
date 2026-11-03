<?php
/**
 * Cabecera con la marca de la firma. La ruta del logo se resuelve aquí (absoluta si
 * ya lo es, si no `base_url()`) y no con el `media_url()` del núcleo, que en otra
 * instalación podría no estar cargado.
 *
 * @var array  $company
 * @var string $subtitle
 */
$logo = (string) ($company['logo'] ?? '');
if ($logo !== '' && ! preg_match('~^https?://~i', $logo)) {
    $logo = base_url(ltrim($logo, '/'));
}
?>
<header class="lf-top">
    <div class="lf-top-in">
        <?php if ($logo !== ''): ?>
            <img class="lf-logo" src="<?= esc($logo, 'attr') ?>" alt="">
        <?php endif; ?>
        <div>
            <h1><?= esc($company['name'] ?? '') ?></h1>
            <?php if (($subtitle ?? '') !== ''): ?><p><?= esc($subtitle) ?></p><?php endif; ?>
        </div>
    </div>
</header>
