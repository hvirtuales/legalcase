<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Models\ProfileModel;

/*
 * El perfil del directorio.
 *
 * Secciones en el orden del proyecto —decisión, datos, contacto, publicación—: primero
 * qué es este perfil y de quién, luego qué cuenta, después cómo lo contactan, y al
 * final lo que afecta a cómo se ve fuera.
 *
 * El slug no se edita a mano: se calcula del nombre y solo se renueva marcando la
 * casilla, porque una URL publicada que se mueve sola rompe los enlaces que ya
 * circulan.
 *
 * @var array|null $profile
 * @var string     $action
 * @var array      $team
 * @var array      $allAreas
 * @var array      $allJurs
 * @var array      $modes
 * @var int[]      $areas
 * @var int[]      $jurs
 */
$v = static fn (string $k, $d = '') => old($k, $profile[$k] ?? $d);
$selectedModes = ProfileModel::listOf($profile['consult_modes'] ?? '');
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-people"></i>
            <h5 class="font-semibold"><?= lang($profile ? 'Lfl.dirEditProfile' : 'Lfl.dirNewProfile') ?></h5>
        </div>
        <a href="<?= base_url('leadflow/directorio') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Lfl.back') ?></a>
    </div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.dirWhoTitle') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.dirWhoHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="kind" class="form-label"><?= lang('Lfl.dirProfileKind') ?></label>
                        <select id="kind" name="kind" class="form-select">
                            <?php foreach (ProfileModel::KINDS as $k): ?>
                                <option value="<?= esc($k, 'attr') ?>" <?= (string) $v('kind', 'lawyer') === $k ? 'selected' : '' ?>>
                                    <?= lang($k === 'firm' ? 'Lfl.dirKindFirm' : 'Lfl.dirKindLawyer') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="user_id" class="form-label"><?= lang('Lfl.user') ?></label>
                        <select id="user_id" name="user_id" class="form-select">
                            <option value=""><?= lang('Lfl.none') ?></option>
                            <?php foreach ($team as $uid => $uname): ?>
                                <option value="<?= (int) $uid ?>" <?= (int) $v('user_id') === (int) $uid ? 'selected' : '' ?>><?= esc($uname) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint"><?= lang('Lfl.dirUserHint') ?></p>
                    </div>

                    <?php
                    /*
                     * La función comercial y el usuario de Docketwise son de la PERSONA,
                     * no de la ficha pública. Se editan aquí porque el vínculo
                     * perfil↔usuario es 1:1 y obligatorio para un abogado: tenerlos en
                     * dos pantallas que no se hablaban ya descuadró los datos una vez.
                     *
                     * Se leen del usuario seleccionado al abrir el formulario. Si se
                     * cambia el usuario, lo que se guarde se aplica al NUEVO.
                     */
                    $uidActual = (int) $v('user_id');
                    $miembro   = $members[$uidActual] ?? null;
                    ?>
                    <div class="form-group">
                        <label for="member_role" class="form-label"><?= lang('Lfl.commercialRole') ?></label>
                        <select id="member_role" name="member_role" class="form-select">
                            <option value=""><?= lang('Lfl.noRole') ?></option>
                            <?php foreach ($roles as $val => $lbl): ?>
                                <option value="<?= esc($val, 'attr') ?>" <?= ($miembro['role'] ?? '') === $val ? 'selected' : '' ?>>
                                    <?= esc($lbl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint"><?= lang('Lfl.dirRoleHint') ?></p>
                    </div>

                    <div class="form-group">
                        <label for="docketwise_user_id" class="form-label"><?= lang('Lfl.dwUser') ?></label>
                        <?php if ($dwUsers): ?>
                            <select id="docketwise_user_id" name="docketwise_user_id" class="form-select">
                                <option value=""><?= lang('Lfl.none') ?></option>
                                <?php foreach ($dwUsers as $dwid => $dwname): ?>
                                    <option value="<?= esc((string) $dwid, 'attr') ?>" <?= (string) ($miembro['docketwise_user_id'] ?? '') === (string) $dwid ? 'selected' : '' ?>>
                                        <?= esc($dwname) ?> (#<?= esc((string) $dwid) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" id="docketwise_user_id" name="docketwise_user_id" maxlength="40" class="form-input"
                                value="<?= esc((string) ($miembro['docketwise_user_id'] ?? ''), 'attr') ?>"
                                placeholder="<?= esc(lang('Lfl.dwUserPh'), 'attr') ?>">
                        <?php endif; ?>
                        <p class="form-hint"><?= lang('Lfl.dwUserHint') ?></p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.dirContentTitle') ?></legend>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="display_name" class="form-label"><?= lang('Lfl.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="display_name" name="display_name" maxlength="150" required class="form-input" value="<?= esc((string) $v('display_name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="headline" class="form-label"><?= lang('Lfl.dirHeadline') ?></label>
                        <input type="text" id="headline" name="headline" maxlength="180" class="form-input" value="<?= esc((string) $v('headline'), 'attr') ?>">
                        <p class="form-hint"><?= lang('Lfl.dirHeadlineHint') ?></p>
                    </div>
                </div>
                <div class="form-group">
                    <label for="bio" class="form-label"><?= lang('Lfl.dirBio') ?></label>
                    <textarea id="bio" name="bio" rows="5" maxlength="4000" class="form-textarea"><?= esc((string) $v('bio')) ?></textarea>
                </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="experience_years" class="form-label"><?= lang('Lfl.dirExperience') ?></label>
                        <input type="number" id="experience_years" name="experience_years" min="0" max="70" class="form-input" value="<?= esc((string) $v('experience_years'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="languages" class="form-label"><?= lang('Lfl.dirLanguages') ?></label>
                        <input type="text" id="languages" name="languages" maxlength="120" class="form-input" value="<?= esc((string) $v('languages'), 'attr') ?>" placeholder="<?= esc(lang('Lfl.dirLanguagesPh'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= lang('Lfl.dirModes') ?></label>
                        <?php foreach ($modes as $m): ?>
                            <label class="form-check mb-1">
                                <input type="checkbox" name="consult_modes[]" value="<?= esc($m, 'attr') ?>" class="form-check-input"
                                    <?= in_array($m, $selectedModes, true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= lang('Lfl.dirMode_' . $m) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= lang('Lfl.dirAreas') ?> <span class="text-danger">*</span></label>
                    <p class="form-hint mb-2"><?= lang('Lfl.dirAreasHint') ?></p>
                    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($allAreas as $a): ?>
                            <label class="form-check mb-1">
                                <input type="checkbox" name="areas[]" value="<?= (int) $a['id'] ?>" class="form-check-input"
                                    <?= in_array((int) $a['id'], $areas, true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= esc($a['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= lang('Lfl.dirJurisdictions') ?></label>
                    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($allJurs as $j): ?>
                            <label class="form-check mb-1">
                                <input type="checkbox" name="jurisdictions[]" value="<?= (int) $j['id'] ?>" class="form-check-input"
                                    <?= in_array((int) $j['id'], $jurs, true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= esc($j['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.dirContactTitle') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.dirContactHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="email" class="form-label"><?= lang('Lfl.email') ?></label>
                        <input type="email" id="email" name="email" maxlength="150" class="form-input" value="<?= esc((string) $v('email'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label"><?= lang('Lfl.phone') ?></label>
                        <input type="text" id="phone" name="phone" maxlength="30" class="form-input" data-phone value="<?= esc((string) $v('phone'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="whatsapp" class="form-label">WhatsApp</label>
                        <input type="text" id="whatsapp" name="whatsapp" maxlength="30" class="form-input" data-phone value="<?= esc((string) $v('whatsapp'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="city" class="form-label"><?= lang('Lfl.city') ?></label>
                        <input type="text" id="city" name="city" maxlength="80" class="form-input" value="<?= esc((string) $v('city'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="website" class="form-label"><?= lang('Lfl.dirWebsite') ?></label>
                        <input type="url" id="website" name="website" maxlength="255" class="form-input" value="<?= esc((string) $v('website'), 'attr') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.dirCredentials') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.dirCredentialsHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="bar_number" class="form-label"><?= lang('Lfl.dirLicense') ?></label>
                        <input type="text" id="bar_number" name="bar_number" maxlength="60" class="form-input" value="<?= esc((string) $v('bar_number'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="bar_state" class="form-label"><?= lang('Lfl.dirBarState') ?></label>
                        <input type="text" id="bar_state" name="bar_state" maxlength="20" class="form-input" value="<?= esc((string) $v('bar_state'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="bar_url" class="form-label"><?= lang('Lfl.dirBarUrl') ?></label>
                        <input type="url" id="bar_url" name="bar_url" maxlength="255" class="form-input" value="<?= esc((string) $v('bar_url'), 'attr') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.dirPublishTitle') ?></legend>
                <div class="form-group">
                    <label for="seo_description" class="form-label"><?= lang('Lfl.dirSeo') ?></label>
                    <input type="text" id="seo_description" name="seo_description" maxlength="200" class="form-input" value="<?= esc((string) $v('seo_description'), 'attr') ?>">
                    <p class="form-hint"><?= lang('Lfl.dirSeoHint') ?></p>
                </div>
                <?php if ($profile): ?>
                    <div class="form-group">
                        <p class="text-sm"><?= lang('Lfl.dirUrl') ?>
                            <span class="font-mono"><?= esc(base_url('abogado/' . $profile['slug'])) ?></span>
                        </p>
                        <label class="form-check">
                            <input type="checkbox" name="renew_slug" value="1" class="form-check-input">
                            <span class="form-check-label"><?= lang('Lfl.dirRenewSlug') ?></span>
                        </label>
                    </div>
                <?php endif; ?>
            </fieldset>

            <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
