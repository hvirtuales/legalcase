<?= $this->extend('Components\Mailer\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php

/**
 * Baja del suscriptor. Los SVG van en línea porque esta pantalla NO carga el
 * `icons.css` del núcleo (contrato §7.3): un `<i class="icon">` aquí no pintaría
 * nada, y la página tiene que verse igual servida desde cualquier sitio.
 */

$empresa = trim((string) ($company['name'] ?? ''));
$logo    = trim((string) ($company['logo'] ?? ''));
// La ruta se resuelve AQUI y no con el `media_url()` del núcleo: esta
// pantalla tiene que pintarse en cualquier instalación, tenga o no ese
// ayudante cargado. Si ya es absoluta (S3/R2) se deja como está.
if ($logo !== '' && !preg_match('#^https?://#i', $logo)) {
    $logo = base_url(ltrim($logo, '/'));
}
?>
<?php if ($empresa !== '' || $logo !== ''): ?>
    <div class="mu-brand">
        <?php if ($logo !== ''): ?>
            <img src="<?= esc($logo, 'attr') ?>" alt="<?= esc($empresa, 'attr') ?>">
        <?php endif; ?>
        <?php if ($empresa !== ''): ?><span><?= esc($empresa) ?></span><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($state === 'invalid'): ?>
    <svg class="mu-ico is-bad" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>
    </svg>
    <h1><?= lang('Mailer.pubInvalid') ?></h1>
    <p><?= lang('Mailer.pubInvalidText') ?></p>

<?php elseif ($state === 'done'): ?>
    <svg class="mu-ico is-ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>
    </svg>
    <h1><?= lang('Mailer.pubDone') ?></h1>
    <p><?= lang('Mailer.pubDoneText', ['<span class="mu-mail">' . esc($email) . '</span>']) ?></p>
    <p class="mu-foot"><?= lang('Mailer.pubClose') ?></p>

<?php else: ?>
    <svg class="mu-ico is-warn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-10 6L2 7"></path>
    </svg>
    <h1><?= lang('Mailer.pubAsk') ?></h1>
    <p><?= lang('Mailer.pubAskText', ['<span class="mu-mail">' . esc($email) . '</span>']) ?></p>
    <form method="post" action="<?= base_url('mailer-publico/baja/' . rawurlencode((string) $token)) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="mu-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>
            </svg>
            <?= lang('Mailer.pubConfirm') ?>
        </button>
    </form>
<?php endif; ?>
<?= $this->endSection() ?>
