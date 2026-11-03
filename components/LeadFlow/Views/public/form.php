<?= $this->extend('Components\LeadFlow\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php
/*
 * El formulario que llena el prospecto. Arriba los datos de contacto (siempre los
 * mismos: con ellos se crea el lead), después las preguntas del formulario con su
 * lógica condicional, y al final el consentimiento y la verificación antibots.
 *
 * Las UTM, la página de entrada y el referrer viajan ocultos (§26): el servidor
 * los recorta y los guarda como atribución, sin creérselos para nada más.
 */
include __DIR__ . '/_icons.php';

$old  = (array) (old('q') ?? []);
$o    = static fn (string $k, $d = '') => (string) old($k, $d);
$lang = service('request')->getLocale();
?>
<?= view('Components\LeadFlow\Views\public\_header', ['company' => $company, 'subtitle' => $form['name']]) ?>

<main class="lf-wrap">
    <?php if (session()->getFlashdata('pub_error')): ?>
        <div class="lf-alert" role="alert"><?= lfl_icon('alert') ?> <?= esc(session()->getFlashdata('pub_error')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('pub_errors')): ?>
        <div class="lf-alert" role="alert">
            <ul><?php foreach ((array) session()->getFlashdata('pub_errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= base_url('leadflow-publico/f/' . $form['token']) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="stamp" value="<?= esc($stamp, 'attr') ?>">
        <?php foreach ($utm as $k => $val): ?>
            <input type="hidden" name="<?= esc($k, 'attr') ?>" value="<?= esc($o($k, $val), 'attr') ?>">
        <?php endforeach; ?>
        <input type="hidden" name="landing_page" value="<?= esc($o('landing_page', $landing), 'attr') ?>">
        <input type="hidden" name="referrer" value="<?= esc($o('referrer', $referrer), 'attr') ?>">
        <div class="lf-trap" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <section class="lf-card">
            <h2><?= lang('Lfl.pubYourData') ?></h2>
            <p class="lf-hint"><?= esc(($settings['public_intro'] ?? '') ?: ($form['description'] ?: lang('Lfl.pubYourDataHint'))) ?></p>
            <div class="lf-grid">
                <div class="lf-field">
                    <label class="lf-label" for="first_name"><?= lang('Lfl.firstName') ?> <span class="lf-req">*</span></label>
                    <input type="text" id="first_name" name="first_name" maxlength="80" required autocomplete="given-name" value="<?= esc($o('first_name'), 'attr') ?>">
                </div>
                <div class="lf-field">
                    <label class="lf-label" for="last_name"><?= lang('Lfl.lastName') ?> <span class="lf-req">*</span></label>
                    <input type="text" id="last_name" name="last_name" maxlength="80" required autocomplete="family-name" value="<?= esc($o('last_name'), 'attr') ?>">
                </div>
                <div class="lf-field">
                    <label class="lf-label" for="email"><?= lang('Lfl.email') ?></label>
                    <input type="email" id="email" name="email" maxlength="150" autocomplete="email" value="<?= esc($o('email'), 'attr') ?>">
                </div>
                <div class="lf-field">
                    <label class="lf-label" for="phone"><?= lang('Lfl.phone') ?></label>
                    <input type="tel" id="phone" name="phone" maxlength="30" autocomplete="tel" value="<?= esc($o('phone'), 'attr') ?>" placeholder="<?= esc(lang('Lfl.phonePh'), 'attr') ?>">
                </div>
                <div class="lf-field lf-wide">
                    <label class="lf-opt"><input type="checkbox" name="whatsapp_same" value="1" <?= old('first_name') === null || old('whatsapp_same') ? 'checked' : '' ?> data-wa-same> <?= lang('Lfl.whatsappSame') ?></label>
                </div>
                <div class="lf-field" data-wa-field>
                    <label class="lf-label" for="whatsapp"><?= lang('Lfl.whatsapp') ?></label>
                    <input type="tel" id="whatsapp" name="whatsapp" maxlength="30" value="<?= esc($o('whatsapp'), 'attr') ?>">
                </div>
                <div class="lf-field">
                    <label class="lf-label" for="country"><?= lang('Lfl.country') ?></label>
                    <select id="country" name="country">
                        <option value=""><?= lang('Lfl.choose') ?></option>
                        <?php foreach ($countries as $code => $name): ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $o('country') === $code ? 'selected' : '' ?>><?= esc($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lf-field">
                    <label class="lf-label" for="city"><?= lang('Lfl.city') ?></label>
                    <input type="text" id="city" name="city" maxlength="80" value="<?= esc($o('city'), 'attr') ?>">
                </div>
                <div class="lf-field">
                    <label class="lf-label" for="preferred_language"><?= lang('Lfl.language') ?></label>
                    <select id="preferred_language" name="preferred_language">
                        <?php foreach (['es', 'en'] as $lng): ?>
                            <option value="<?= esc($lng, 'attr') ?>" <?= $o('preferred_language', $lang) === $lng ? 'selected' : '' ?>><?= lang('Lfl.lang_' . $lng) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="lf-help"><?= lang('Lfl.pubContactNote') ?></p>
        </section>

        <?php if ($fields): ?>
            <section class="lf-card">
                <h2><?= lang('Lfl.pubQuestions') ?></h2>
                <p class="lf-hint"><?= lang('Lfl.pubQuestionsHint') ?></p>
                <div class="lf-grid" data-dyn>
                    <?php foreach ($fields as $f): ?>
                        <?php
                        $id   = $f['id'];
                        $name = 'q[' . $id . ']';
                        $val  = $old[$id] ?? '';
                        $wide = in_array($f['type'], ['textarea', 'address', 'multiselect', 'file', 'yesno'], true);
                        ?>
                        <div class="lf-field <?= $wide ? 'lf-wide' : '' ?>" data-field="<?= esc($id, 'attr') ?>"
                            <?= isset($f['rule']) ? 'data-rule="' . esc(json_encode($f['rule'], JSON_UNESCAPED_UNICODE), 'attr') . '"' : '' ?>>
                            <label class="lf-label" for="<?= esc($id, 'attr') ?>"><?= esc($f['label']) ?><?= $f['required'] ? ' <span class="lf-req">*</span>' : '' ?></label>
                            <?php if ($f['type'] === 'textarea' || $f['type'] === 'address'): ?>
                                <textarea id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" rows="4"><?= esc(is_string($val) ? $val : '') ?></textarea>
                            <?php elseif ($f['type'] === 'select' || $f['type'] === 'country'): ?>
                                <select id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>">
                                    <option value=""><?= lang('Lfl.choose') ?></option>
                                    <?php foreach ($f['type'] === 'country' ? $countries : array_combine($f['options'], $f['options']) as $ov => $ol): ?>
                                        <option value="<?= esc((string) $ov, 'attr') ?>" <?= (string) $val === (string) $ov ? 'selected' : '' ?>><?= esc($ol) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($f['type'] === 'multiselect'): ?>
                                <div class="lf-opts">
                                    <?php foreach ($f['options'] as $opt): ?>
                                        <label class="lf-opt"><input type="checkbox" name="<?= esc($name, 'attr') ?>[]" value="<?= esc($opt, 'attr') ?>" <?= in_array($opt, (array) $val, true) ? 'checked' : '' ?>> <?= esc($opt) ?></label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($f['type'] === 'yesno'): ?>
                                <div class="lf-opts">
                                    <?php foreach (['yes' => 'Lfl.yes', 'no' => 'Lfl.no'] as $ov => $ol): ?>
                                        <label class="lf-opt"><input type="radio" name="<?= esc($name, 'attr') ?>" value="<?= esc($ov, 'attr') ?>" <?= $val === $ov ? 'checked' : '' ?>> <?= lang($ol) ?></label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($f['type'] === 'file'): ?>
                                <input type="file" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" accept="application/pdf,image/*">
                                <p class="lf-help"><?= lang('Lfl.docLimits') ?></p>
                            <?php else: ?>
                                <?php $type = ['email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date'][$f['type']] ?? 'text'; ?>
                                <input type="<?= esc($type, 'attr') ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc(is_string($val) ? $val : '', 'attr') ?>" <?= $type === 'number' ? 'step="any"' : '' ?>>
                            <?php endif; ?>
                            <?php if ($f['help'] !== ''): ?><p class="lf-help"><?= esc($f['help']) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="lf-card">
            <?php if ((int) $form['ask_consultation'] === 1): ?>
                <label class="lf-check">
                    <input type="checkbox" name="request_consultation" value="1" <?= old('request_consultation') ? 'checked' : '' ?>>
                    <span><strong><?= lang('Lfl.pubRequestConsultation') ?></strong><br><?= lang('Lfl.pubRequestConsultationHint') ?></span>
                </label>
            <?php endif; ?>

            <div class="lf-legal"><?= esc(($settings['consent_text'] ?? '') ?: lang('Lfl.pubConsentDefault', [$company['name'] ?? ''])) ?></div>
            <label class="lf-check">
                <input type="checkbox" name="consent" value="1" required <?= old('consent') ? 'checked' : '' ?>>
                <span><?= lang('Lfl.pubConsent') ?> <span class="lf-req">*</span></span>
            </label>

            <?php if ($captchaImg !== ''): ?>
                <div class="lf-captcha">
                    <img src="<?= esc($captchaImg, 'attr') ?>" alt="<?= esc(lang('Lfl.pubCaptchaAlt'), 'attr') ?>" width="190" height="60" data-captcha-img>
                    <button type="button" class="lf-refresh" title="<?= esc(lang('Lfl.pubCaptchaRefresh'), 'attr') ?>" data-captcha-refresh><?= lfl_icon('refresh') ?></button>
                    <div class="lf-field">
                        <label class="lf-label" for="captcha"><?= lang('Lfl.pubCaptcha') ?> <span class="lf-req">*</span></label>
                        <input type="text" id="captcha" name="captcha" maxlength="8" autocomplete="off" required>
                    </div>
                </div>
            <?php endif; ?>

            <p style="margin-top:18px">
                <button type="submit" class="lf-btn"><?= lfl_icon('send') ?> <?= lang('Lfl.pubSend') ?></button>
            </p>
        </section>
    </form>
</main>
<footer class="lf-foot"><?= esc($company['name'] ?? '') ?></footer>

<?= view('Components\LeadFlow\Views\forms\_logic_js') ?>
<script>
(function () {
    var same = document.querySelector('[data-wa-same]'), wa = document.querySelector('[data-wa-field]');
    var sync = function () { if (wa) { wa.hidden = same && same.checked; } };
    if (same) { same.addEventListener('change', sync); sync(); }

    var btn = document.querySelector('[data-captcha-refresh]');
    if (btn) {
        btn.addEventListener('click', function () {
            fetch(<?= json_encode(base_url('leadflow-publico/f/' . $form['token'] . '/captcha')) ?>, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d && d.img) { document.querySelector('[data-captcha-img]').src = d.img; } })
                .catch(function () {});
        });
    }
})();
</script>
<?= $this->endSection() ?>
