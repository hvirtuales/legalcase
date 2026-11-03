<?= $this->extend('Components\Mailer\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php

/**
 * Alta pública a una lista, en sus cuatro estados: el formulario, «revisa tu
 * correo», el alta confirmada y el enlace caducado.
 *
 * Autosuficiente (§7.3): los SVG van en línea porque esta pantalla no carga el
 * `icons.css` del núcleo, y el lienzo lo pone `_layout.php`, también en línea.
 *
 * @var string $state  form | sent | done | invalid
 */
$empresa = trim((string) ($company['name'] ?? ''));
$logo    = trim((string) ($company['logo'] ?? ''));
// Absoluta si ya lo es (S3/R2); si no, contra la propia instalación. Sin
// `media_url()` del núcleo, que en otra plataforma podría no estar cargado.
if ($logo !== '' && !preg_match('#^https?://#i', $logo)) {
    $logo = base_url(ltrim($logo, '/'));
}
$titular = trim((string) ($list['consent_text'] ?? '')) ?: lang('Mailer.signupDefaultConsent');
?>
<?php if ($empresa !== '' || $logo !== ''): ?>
    <div class="mu-brand">
        <?php if ($logo !== ''): ?><img src="<?= esc($logo, 'attr') ?>" alt="<?= esc($empresa, 'attr') ?>"><?php endif; ?>
        <?php if ($empresa !== ''): ?><span><?= esc($empresa) ?></span><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($state === 'form'): ?>
    <svg class="mu-ico is-warn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-10 6L2 7"></path>
    </svg>
    <h1><?= lang('Mailer.signupTitle') ?></h1>
    <p><?= lang('Mailer.signupIntro', [esc($list['name'])]) ?></p>

    <?php if ($error !== ''): ?>
        <p class="mu-error"><?= esc($error) ?></p>
    <?php endif; ?>

    <form method="post" class="mu-form">
        <?= csrf_field() ?>
        <input type="hidden" name="t" value="<?= esc($stamp, 'attr') ?>">
        <!-- La trampa: invisible para una persona, irresistible para un robot.
             `aria-hidden` y `tabindex` la sacan también del lector de pantalla. -->
        <div class="mu-hp" aria-hidden="true">
            <label for="website"><?= lang('Mailer.signupTrap') ?></label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
        </div>

        <label for="email"><?= lang('Mailer.email') ?></label>
        <input type="email" name="email" id="email" required autocomplete="email"
               placeholder="<?= esc(lang('Mailer.mailPh'), 'attr') ?>">

        <label for="name"><?= lang('Mailer.contactName') ?></label>
        <input type="text" name="name" id="name" autocomplete="name">

        <label for="entity"><?= lang('Mailer.entityOpt') ?></label>
        <input type="text" name="entity" id="entity" autocomplete="organization">

        <label class="mu-check">
            <input type="checkbox" name="consent" value="1" required>
            <span><?= esc($titular) ?></span>
        </label>

        <button type="submit" class="mu-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>
            </svg>
            <?= lang('Mailer.signupSubmit') ?>
        </button>
        <p class="mu-foot"><?= lang('Mailer.signupPrivacy') ?></p>
    </form>

<?php elseif ($state === 'sent'): ?>
    <svg class="mu-ico is-ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-10 6L2 7"></path>
    </svg>
    <h1><?= lang('Mailer.signupSentTitle') ?></h1>
    <p><?= lang('Mailer.signupSentText') ?></p>
    <p class="mu-foot"><?= lang('Mailer.signupSentSpam') ?></p>

<?php elseif ($state === 'done'): ?>
    <svg class="mu-ico is-ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>
    </svg>
    <h1><?= lang('Mailer.signupDoneTitle') ?></h1>
    <p><?= lang('Mailer.signupDoneText', ['<span class="mu-mail">' . esc($email) . '</span>']) ?></p>
    <p class="mu-foot"><?= lang('Mailer.pubClose') ?></p>

<?php else: ?>
    <svg class="mu-ico is-bad" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>
    </svg>
    <h1><?= lang('Mailer.signupBadTitle') ?></h1>
    <p><?= lang('Mailer.signupBadText') ?></p>
<?php endif; ?>
<?= $this->endSection() ?>
