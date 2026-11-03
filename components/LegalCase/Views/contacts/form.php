<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta y edición de un contacto. Orden por decisión: quién es → cómo identificarlo →
 * cómo contactarlo → notas internas.
 *
 * @var array $row @var string $action
 */
use Components\LegalCase\Models\ContactModel;

$v = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="mx-auto max-w-3xl">
    <div class="card">
        <div class="card-header">
            <i class="icon icon-person"></i>
            <h5 class="font-semibold"><?= esc($title) ?></h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= esc($action, 'attr') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= esc((string) service('request')->getGet('volver'), 'attr') ?>">

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secWho') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secWhoHint') ?></p>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="form-group">
                            <label for="kind" class="form-label"><?= lang('Lgc.fKind') ?> <span class="text-danger">*</span></label>
                            <select id="kind" name="kind" class="form-select">
                                <?php foreach (ContactModel::KINDS as $k): ?>
                                    <option value="<?= $k ?>" <?= $v('kind') === $k ? 'selected' : '' ?>><?= lang('Lgc.kind_' . $k) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="name" class="form-label"><?= lang('Lgc.fName') ?> <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-input" required maxlength="190"
                                value="<?= esc($v('name'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phName'), 'attr') ?>">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secCapacity') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secCapacityHint') ?></p>
                    <select id="legal_capacity" name="legal_capacity" class="form-select" aria-label="<?= esc(lang('Lgc.secCapacity'), 'attr') ?>">
                        <?php foreach (ContactModel::CAPACITIES as $c): ?><option value="<?= $c ?>" <?= $v('legal_capacity', 'full') === $c ? 'selected' : '' ?>><?= lang('Lgc.cap_' . $c) ?></option><?php endforeach; ?>
                    </select>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secIdentity') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secIdentityHint') ?></p>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="form-group">
                            <label for="doc_type" class="form-label"><?= lang('Lgc.fDocType') ?></label>
                            <select id="doc_type" name="doc_type" class="form-select">
                                <option value=""><?= lang('Lgc.none') ?></option>
                                <?php foreach (ContactModel::DOC_TYPES as $d): ?>
                                    <option value="<?= $d ?>" <?= $v('doc_type') === $d ? 'selected' : '' ?>><?= lang('Lgc.doc_' . $d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="doc_number" class="form-label"><?= lang('Lgc.fDocNumber') ?></label>
                            <input type="text" id="doc_number" name="doc_number" class="form-input" maxlength="40"
                                value="<?= esc($v('doc_number'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phDocNumber'), 'attr') ?>">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secContact') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secContactHint') ?></p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="form-group">
                            <label for="email" class="form-label"><?= lang('Lgc.fEmail') ?></label>
                            <input type="email" id="email" name="email" class="form-input" maxlength="190"
                                value="<?= esc($v('email'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phEmail'), 'attr') ?>">
                            <small class="form-hint"><?= lang('Lgc.fEmailHint') ?></small>
                        </div>
                        <div class="form-group">
                            <label for="phone" class="form-label"><?= lang('Lgc.fPhone') ?></label>
                            <input type="tel" id="phone" name="phone" class="form-input" maxlength="40" data-phone
                                value="<?= esc($v('phone'), 'attr') ?>">
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="address" class="form-label"><?= lang('Lgc.fAddress') ?></label>
                            <input type="text" id="address" name="address" class="form-input" maxlength="255" value="<?= esc($v('address'), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label for="city" class="form-label"><?= lang('Lgc.fCity') ?></label>
                            <input type="text" id="city" name="city" class="form-input" maxlength="100" value="<?= esc($v('city'), 'attr') ?>">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="country" class="form-label"><?= lang('Lgc.fCountry') ?></label>
                                <input type="text" id="country" name="country" class="form-input uppercase" maxlength="2" value="<?= esc($v('country', 'CO'), 'attr') ?>">
                            </div>
                            <div class="form-group">
                                <label for="locale" class="form-label"><?= lang('Lgc.fLocale') ?></label>
                                <select id="locale" name="locale" class="form-select">
                                    <option value="es" <?= $v('locale', 'es') === 'es' ? 'selected' : '' ?>>ES</option>
                                    <option value="en" <?= $v('locale') === 'en' ? 'selected' : '' ?>>EN</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><i class="icon icon-shield-check"></i> <?= lang('Lgc.secConsent') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secConsentHint') ?></p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="form-group">
                            <label for="consent_status" class="form-label"><?= lang('Lgc.fConsentStatus') ?></label>
                            <select id="consent_status" name="consent_status" class="form-select">
                                <?php foreach (ContactModel::CONSENT_STATUSES as $c): ?>
                                    <option value="<?= $c ?>" <?= $v('consent_status', 'none') === $c ? 'selected' : '' ?>><?= lang('Lgc.consent_' . $c) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($row['consent_at'])): ?>
                                <small class="form-hint"><?= lang('Lgc.consentSince', [esc((string) $row['consent_at']), esc((string) ($row['consent_version'] ?: '—'))]) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="consent_method" class="form-label"><?= lang('Lgc.fConsentMethod') ?></label>
                            <select id="consent_method" name="consent_method" class="form-select">
                                <option value=""><?= lang('Lgc.none') ?></option>
                                <?php foreach (ContactModel::CONSENT_METHODS as $c): ?>
                                    <option value="<?= $c ?>" <?= $v('consent_method') === $c ? 'selected' : '' ?>><?= lang('Lgc.cmethod_' . $c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="consent_evidence" class="form-label"><?= lang('Lgc.fConsentEvidence') ?></label>
                        <input type="text" id="consent_evidence" name="consent_evidence" class="form-input" maxlength="255" value="<?= esc($v('consent_evidence'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phConsentEvidence'), 'attr') ?>">
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secInternal') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secInternalHint') ?></p>
                    <textarea id="notes" name="notes" rows="3" class="form-textarea"><?= esc($v('notes')) ?></textarea>
                </fieldset>

                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <?php if (!empty($row['id'])): ?>
                        <a href="<?= base_url('legalcase/contactos/datos/' . (int) $row['id']) ?>" class="btn btn-ghost"><i class="icon icon-download"></i> <?= lang('Lgc.exportData') ?></a>
                    <?php endif; ?>
                    <a href="<?= base_url('legalcase/contactos') ?>" class="btn btn-ghost"><?= lang('Lgc.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
