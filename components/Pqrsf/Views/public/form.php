<?= $this->extend('Components\Pqrsf\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php
/**
 * El formulario del QR.
 *
 * Empieza por la decisión que cambia todo lo demás —identificarse o no—, porque
 * en anónima no tiene sentido pedir datos que no vamos a guardar.
 *
 * @var array  $point @var array $company @var array $cfg @var string $token
 * @var array  $types tipos activos de la empresa
 * @var string $captchaImg @var string $stamp
 */
$ic   = require __DIR__ . '/_icons.php';
$mode = old('mode') ?: 'identificada';
?>

<header class="pq-top">
    <div class="pq-top-in">
        <?php if (!empty($company['logo'])): ?>
            <img class="pq-logo" src="<?= media_url($company['logo']) ?>" alt="">
        <?php endif; ?>
        <div>
            <h1><?= esc($company['name']) ?></h1>
            <p><?= lang('Pqr.pubHeadline') ?><?= $point['area'] ? ' · ' . esc($point['area']) : '' ?></p>
        </div>
    </div>
</header>

<div class="pq-wrap">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="pq-alert"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= base_url('pqrsf/p/' . $token) ?>" enctype="multipart/form-data" id="pqForm">
        <?= csrf_field() ?>
        <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">

        <!-- Trampa para bots: invisible y fuera del tabulador -->
        <div class="pq-trap" aria-hidden="true">
            <label>No llenar<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="pq-card">
            <h2><?= lang('Pqr.formHow') ?></h2>
            <p class="pq-hint"><?= lang('Pqr.formHowHint') ?></p>

            <div class="pq-modes">
                <label class="pq-mode <?= $mode === 'identificada' ? 'is-on' : '' ?>" data-mode>
                    <input type="radio" name="mode" value="identificada" <?= $mode === 'identificada' ? 'checked' : '' ?>>
                    <span>
                        <b><?= lang('Pqr.modeIdentified') ?></b>
                        <span><?= lang('Pqr.modeIdentifiedHint') ?></span>
                    </span>
                </label>

                <?php if (!empty($cfg['allow_anonymous'])): ?>
                    <label class="pq-mode <?= $mode === 'anonima' ? 'is-on' : '' ?>" data-mode>
                        <input type="radio" name="mode" value="anonima" <?= $mode === 'anonima' ? 'checked' : '' ?>>
                        <span>
                            <b><?= lang('Pqr.modeAnonymous') ?></b>
                            <span><?= lang('Pqr.modeAnonymousHint') ?></span>
                        </span>
                    </label>
                <?php endif; ?>
            </div>

            <div class="pq-grid">
                <div class="pq-field">
                    <label for="type"><?= lang('Pqr.fType') ?> <span class="pq-req">*</span></label>
                    <select id="type" name="type" required>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= esc($t['code'], 'attr') ?>" <?= old('type') === $t['code'] ? 'selected' : '' ?>>
                                <?= esc($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pq-field">
                    <label for="subject"><?= lang('Pqr.fSubject') ?> <span class="pq-req">*</span></label>
                    <input type="text" id="subject" name="subject" required maxlength="200" value="<?= esc(old('subject'), 'attr') ?>">
                </div>
                <div class="pq-field pq-wide">
                    <label for="message"><?= lang('Pqr.fMessage') ?> <span class="pq-req">*</span></label>
                    <textarea id="message" name="message" rows="5" required minlength="10"><?= esc(old('message')) ?></textarea>
                </div>
                <div class="pq-field pq-wide">
                    <label for="files"><?= lang('Pqr.fFiles') ?></label>
                    <input type="file" id="files" name="files[]" multiple accept="image/*,application/pdf,.doc,.docx,.txt">
                    <small style="font-size:11.5px;color:var(--pq-muted)"><?= lang('Pqr.fFilesHint') ?></small>
                </div>
            </div>
        </div>

        <!-- Datos: solo si se identifica -->
        <div class="pq-card" id="idBox" <?= $mode === 'anonima' ? 'hidden' : '' ?>>
            <h2><?= lang('Pqr.formYou') ?></h2>
            <p class="pq-hint"><?= lang('Pqr.formYouHint') ?></p>

            <div class="pq-grid">
                <div class="pq-field">
                    <label for="name"><?= lang('Pqr.fName') ?> <span class="pq-req">*</span></label>
                    <input type="text" id="name" name="name" maxlength="150" value="<?= esc(old('name'), 'attr') ?>">
                </div>
                <div class="pq-field">
                    <label for="doc"><?= lang('Pqr.fDoc') ?></label>
                    <input type="text" id="doc" name="doc" maxlength="40" value="<?= esc(old('doc'), 'attr') ?>">
                </div>
                <div class="pq-field">
                    <label for="email"><?= lang('Pqr.fEmail') ?></label>
                    <input type="email" id="email" name="email" maxlength="190" value="<?= esc(old('email'), 'attr') ?>">
                </div>
                <div class="pq-field">
                    <label for="phone"><?= lang('Pqr.fPhone') ?></label>
                    <input type="tel" id="phone" name="phone" maxlength="40" value="<?= esc(old('phone'), 'attr') ?>">
                </div>
                <div class="pq-field">
                    <label for="city"><?= lang('Pqr.fCity') ?></label>
                    <input type="text" id="city" name="city" maxlength="100" value="<?= esc(old('city'), 'attr') ?>">
                </div>
            </div>
        </div>

        <!-- Autorizaciones: tres, porque jurídicamente son distintas -->
        <div class="pq-card">
            <h2><?= lang('Pqr.formLegal') ?></h2>
            <div class="pq-legal">
                <label class="pq-check">
                    <input type="checkbox" name="accept_terms" value="1" required <?= old('accept_terms') ? 'checked' : '' ?>>
                    <span>
                        <?= lang('Pqr.consentTerms') ?> <span class="pq-req">*</span>
                        <?php if (!empty($cfg['terms_url'])): ?>
                            — <a href="<?= esc($cfg['terms_url']) ?>" target="_blank" rel="noopener"><?= lang('Pqr.seeTerms') ?></a>
                        <?php endif; ?>
                    </span>
                </label>

                <label class="pq-check" id="consentDataBox" <?= $mode === 'anonima' ? 'hidden' : '' ?>>
                    <input type="checkbox" name="consent_data" value="1" <?= old('consent_data') ? 'checked' : '' ?>>
                    <span>
                        <?= lang('Pqr.consentData', [$company['name']]) ?> <span class="pq-req">*</span>
                        <?php if (!empty($cfg['privacy_url'])): ?>
                            — <a href="<?= esc($cfg['privacy_url']) ?>" target="_blank" rel="noopener"><?= lang('Pqr.seePrivacy') ?></a>
                        <?php endif; ?>
                    </span>
                </label>

                <label class="pq-check" id="consentCommsBox" <?= $mode === 'anonima' ? 'hidden' : '' ?>>
                    <input type="checkbox" name="consent_comms" value="1" <?= old('consent_comms') ? 'checked' : '' ?>>
                    <span><?= lang('Pqr.consentComms') ?></span>
                </label>

                <?php if (!empty($cfg['privacy_text']) || !empty($cfg['terms_text'])): ?>
                    <details style="margin-top:8px">
                        <summary style="cursor:pointer;font-size:12.5px;color:var(--pq-muted)"><?= lang('Pqr.readPolicies') ?></summary>
                        <div style="font-size:12.5px;color:var(--pq-muted);white-space:pre-line;margin-top:8px">
                            <?= esc($cfg['terms_text'] ?? '') ?>

                            <?= esc($cfg['privacy_text'] ?? '') ?>
                        </div>
                    </details>
                <?php endif; ?>

                <p style="margin:10px 0 0;font-size:11.5px;color:var(--pq-muted)">
                    <?= lang('Pqr.consentStamp', [$cfg['policy_version'] ?: 'v1']) ?>
                </p>
            </div>
        </div>

        <?php if (!empty($cfg['captcha_enabled']) && $captchaImg !== ''): ?>
            <div class="pq-card">
                <h2><?= lang('Pqr.formCaptcha') ?></h2>
                <p class="pq-hint"><?= lang('Pqr.formCaptchaHint') ?></p>
                <div class="pq-captcha">
                    <img id="captchaImg" src="<?= $captchaImg ?>" alt="<?= esc(lang('Pqr.formCaptcha'), 'attr') ?>" width="190" height="60">
                    <button type="button" class="pq-refresh pq-i" id="captchaReload"
                        title="<?= esc(lang('Pqr.captchaReload'), 'attr') ?>"><?= $ic['flip'] ?></button>
                    <div class="pq-field">
                        <label for="captcha"><?= lang('Pqr.fCaptcha') ?> <span class="pq-req">*</span></label>
                        <input type="text" id="captcha" name="captcha" maxlength="8" autocomplete="off" required>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <p style="margin-top:18px">
            <button type="submit" class="pq-btn"><span class="pq-i"><?= $ic['send'] ?? $ic['share'] ?></span> <?= lang('Pqr.formSend') ?></button>
        </p>
    </form>
</div>

<footer class="pq-foot">
    <?= esc($company['name']) ?><?= !empty($company['nit']) ? ' · NIT ' . esc($company['nit']) : '' ?>
    · <?= esc($point['name']) ?>
</footer>

<script>
(function () {
    // Identificarme / anónima: en anónima no se piden datos ni autorizaciones
    // que no aplican.
    var idBox   = document.getElementById('idBox');
    var dataBox = document.getElementById('consentDataBox');
    var commBox = document.getElementById('consentCommsBox');
    var name    = document.getElementById('name');

    function apply() {
        var anon = (document.querySelector('input[name=mode]:checked') || {}).value === 'anonima';

        if (idBox)   { idBox.hidden   = anon; }
        if (dataBox) { dataBox.hidden = anon; }
        if (commBox) { commBox.hidden = anon; }
        if (name)    { name.required  = !anon; }

        document.querySelectorAll('[data-mode]').forEach(function (m) {
            m.classList.toggle('is-on', m.querySelector('input').checked);
        });
    }

    document.querySelectorAll('input[name=mode]').forEach(function (r) { r.addEventListener('change', apply); });
    apply();

    // Refrescar el captcha sin recargar la página.
    var reload = document.getElementById('captchaReload');
    if (reload) {
        reload.addEventListener('click', function () {
            fetch('<?= base_url('pqrsf/captcha/' . $token) ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.img) { document.getElementById('captchaImg').src = j.img; }
                    document.getElementById('captcha').value = '';
                });
        });
    }
}());
</script>
<?= $this->endSection() ?>
