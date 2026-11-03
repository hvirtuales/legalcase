<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\LeadModel;

/*
 * Orden por decisión: quién es → cómo se le contacta → qué necesita (el caso) →
 * a quién se le asigna. La información jurídica variable NO está aquí: va en los
 * formularios de precalificación (§8).
 */
$v       = static fn (string $k, $d = '') => old($k, $lead[$k] ?? $d);
$isNew   = $lead === [];
$country = (string) $v('country');
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-person"></i>
        <h5 class="font-semibold"><?= esc($title) ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>

        <?php if ($dups): ?>
            <?php /* Prevención de duplicados (§23): se enseña con quién coincide y por
                    qué, y se deja seguir solo marcándolo a propósito. */ ?>
            <div class="alert alert-warning">
                <i class="icon icon-exclamation-circle"></i>
                <div class="flex-1">
                    <p class="font-semibold"><?= lang('Lfl.dupTitle') ?></p>
                    <ul class="mt-1 list-inside list-disc">
                        <?php foreach ($dups as $d): ?>
                            <li>
                                <a class="text-primary hover:underline" href="<?= base_url('leadflow/leads/ficha/' . (int) $d['lead']['id']) ?>" target="_blank" rel="noopener">
                                    <?= esc(LeadModel::fullName($d['lead'])) ?>
                                </a>
                                <span class="text-xs">— <?= esc(implode(', ', array_map(static fn ($r) => lang('Lfl.dupBy_' . $r), $d['reasons']))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.secIdentity') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.secIdentityHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="first_name" class="form-label"><?= lang('Lfl.firstName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-input" required maxlength="80" value="<?= esc($v('first_name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="middle_name" class="form-label"><?= lang('Lfl.middleName') ?></label>
                        <input type="text" id="middle_name" name="middle_name" class="form-input" maxlength="80" value="<?= esc($v('middle_name'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name" class="form-label"><?= lang('Lfl.lastName') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-input" required maxlength="80" value="<?= esc($v('last_name'), 'attr') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.secContact') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.secContactHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group">
                        <label for="email" class="form-label"><?= lang('Lfl.email') ?></label>
                        <input type="email" id="email" name="email" class="form-input" maxlength="150" value="<?= esc($v('email'), 'attr') ?>"
                            placeholder="<?= esc(lang('Lfl.emailPh'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label"><?= lang('Lfl.phone') ?></label>
                        <input type="text" id="phone" name="phone" class="form-input" maxlength="30" value="<?= esc($v('phone'), 'attr') ?>"
                            placeholder="<?= esc(lang('Lfl.phonePh'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="whatsapp" class="form-label"><?= lang('Lfl.whatsapp') ?></label>
                        <input type="text" id="whatsapp" name="whatsapp" class="form-input" maxlength="30" value="<?= esc($v('whatsapp'), 'attr') ?>"
                            placeholder="<?= esc(lang('Lfl.phonePh'), 'attr') ?>">
                        <label class="form-check mt-1">
                            <input type="checkbox" name="whatsapp_same" value="1" class="form-check-input">
                            <span class="form-check-label text-xs"><?= lang('Lfl.whatsappSame') ?></span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="country" class="form-label"><?= lang('Lfl.country') ?></label>
                        <select id="country" name="country" class="form-select">
                            <option value=""><?= lang('Lfl.choose') ?></option>
                            <?php foreach (Meta::countries() as $code => $name): ?>
                                <option value="<?= esc($code, 'attr') ?>" <?= $country === $code ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="city" class="form-label"><?= lang('Lfl.city') ?></label>
                        <input type="text" id="city" name="city" class="form-input" maxlength="80" value="<?= esc($v('city'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="preferred_language" class="form-label"><?= lang('Lfl.language') ?></label>
                        <select id="preferred_language" name="preferred_language" class="form-select">
                            <option value=""><?= lang('Lfl.choose') ?></option>
                            <?php foreach (LeadModel::LANGUAGES as $lng): ?>
                                <option value="<?= esc($lng, 'attr') ?>" <?= $v('preferred_language') === $lng ? 'selected' : '' ?>><?= lang('Lfl.lang_' . $lng) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.secCase') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.secCaseHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                    <?php foreach ([['service_id', 'service', $services], ['source_id', 'source', $sources], ['campaign_id', 'campaign', $campaigns]] as [$field, $label, $opts]): ?>
                        <div class="form-group">
                            <label for="<?= esc($field, 'attr') ?>" class="form-label"><?= lang('Lfl.' . $label) ?></label>
                            <select id="<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-select">
                                <option value=""><?= lang('Lfl.none') ?></option>
                                <?php foreach ($opts as $id => $name): ?>
                                    <option value="<?= (int) $id ?>" <?= (int) $v($field) === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-group">
                        <label for="potential_value" class="form-label"><?= lang('Lfl.potentialValue') ?> (<?= esc($currency) ?>)</label>
                        <input type="number" step="0.01" min="0" id="potential_value" name="potential_value" class="form-input" value="<?= esc((string) $v('potential_value'), 'attr') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="summary" class="form-label"><?= lang('Lfl.summary') ?></label>
                    <textarea id="summary" name="summary" class="form-textarea" rows="3" placeholder="<?= esc(lang('Lfl.summaryPh'), 'attr') ?>"><?= esc($v('summary')) ?></textarea>
                </div>

                <?php
                /*
                 * El número de recibo de USCIS. Va en «El caso» y no en los datos de
                 * contacto porque es del trámite, no de la persona.
                 *
                 * Se pinta siempre, también si la firma no tiene USCIS conectado:
                 * apuntar el recibo es útil por sí solo —el asesor lo necesita para
                 * hablar con el cliente— y esconder el campo obligaría a guardarlo en
                 * el resumen, donde nadie lo encuentra después.
                 */
                ?>
                <div class="form-group">
                    <label for="uscis_receipt" class="form-label"><?= lang('Lfl.uscLeadReceipt') ?></label>
                    <input type="text" id="uscis_receipt" name="uscis_receipt" class="form-input font-mono"
                        maxlength="13" autocomplete="off" spellcheck="false"
                        placeholder="EAC9999103403" value="<?= esc((string) $v('uscis_receipt'), 'attr') ?>">
                    <small class="form-hint"><?= lang('Lfl.uscLeadReceiptHint') ?></small>
                </div>
                <label class="form-check mb-2">
                    <input type="checkbox" name="requested_consultation" value="1" class="form-check-input" <?= $v('requested_consultation') ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang('Lfl.requestedConsultation') ?></span>
                </label>
                <p class="form-hint"><?= lang('Lfl.requestedConsultationHint') ?></p>
            </fieldset>

            <?php if ($isNew && $access->canAssignOthers()): ?>
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lfl.secAssign') ?></legend>
                    <p class="form-section-hint"><?= lang('Lfl.secAssignHint') ?></p>
                    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                        <?php foreach (['assigned_user_id' => 'advisor', 'lawyer_user_id' => 'lawyer'] as $field => $label): ?>
                            <div class="form-group">
                                <label for="<?= esc($field, 'attr') ?>" class="form-label"><?= lang('Lfl.' . $label) ?></label>
                                <select id="<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-select">
                                    <option value=""><?= lang('Lfl.nobody') ?></option>
                                    <?php foreach ($team as $uid => $uname): ?>
                                        <option value="<?= (int) $uid ?>" <?= (int) old($field) === (int) $uid ? 'selected' : '' ?>><?= esc($uname) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <details class="form-section" <?= $v('utm_source') || $v('utm_campaign') ? 'open' : '' ?>>
                <summary class="form-section-title cursor-pointer"><?= lang('Lfl.secAttribution') ?></summary>
                <p class="form-section-hint mt-2"><?= lang('Lfl.secAttributionHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <?php foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer'] as $field): ?>
                        <div class="form-group">
                            <label for="<?= esc($field, 'attr') ?>" class="form-label"><?= esc($field) ?></label>
                            <input type="text" id="<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-input" maxlength="255" value="<?= esc((string) $v($field), 'attr') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>

            <?php if ($dups): ?>
                <label class="form-check mb-3">
                    <input type="checkbox" name="force_duplicate" value="1" class="form-check-input">
                    <span class="form-check-label"><?= lang('Lfl.dupForce') ?></span>
                </label>
            <?php endif; ?>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                <a href="<?= $isNew ? base_url('leadflow/leads') : base_url('leadflow/leads/ficha/' . (int) $lead['id']) ?>" class="btn btn-secondary">
                    <i class="icon icon-x-circle"></i> <?= lang('Lfl.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
