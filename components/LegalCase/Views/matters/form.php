<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Alta y edición del asunto. Orden por decisión:
 *   1. qué es y para quién (cliente, abogado responsable);
 *   2. clasificación (especialidad, jurisdicción);
 *   3. lo INTERNO (hechos, objetivos, estrategia) — nunca llega al cliente;
 *   4. lo que VE el cliente (nombre comprensible y etapa);
 *   5. visibilidad interna y sensibilidad.
 *
 * @var array $row @var string $action @var array $contacts @var array $users
 */
use Components\LegalCase\Models\MatterModel;

$v       = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$editing = !empty($row['id']);
?>
<div class="mx-auto max-w-4xl">
    <div class="card">
        <div class="card-header">
            <i class="icon icon-briefcase"></i>
            <h5 class="font-semibold"><?= esc($title) ?><?= $editing ? ' · <span class="font-mono">' . esc($row['number']) . '</span>' : '' ?></h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= esc($action, 'attr') ?>">
                <?= csrf_field() ?>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secMatter') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secMatterHint') ?></p>
                    <div class="form-group">
                        <label for="title" class="form-label"><?= lang('Lgc.fTitle') ?> <span class="text-danger">*</span></label>
                        <input type="text" id="title" name="title" class="form-input" required maxlength="190"
                            value="<?= esc($v('title'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phMatterTitle'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Lgc.fTitleHint') ?></small>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="form-group">
                            <label for="client_contact_id" class="form-label"><?= lang('Lgc.fClient') ?></label>
                            <select id="client_contact_id" name="client_contact_id" class="form-select">
                                <option value=""><?= lang('Lgc.none') ?></option>
                                <?php foreach ($contacts as $id => $name): ?>
                                    <option value="<?= (int) $id ?>" <?= (string) $v('client_contact_id') === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">
                                <a class="text-primary" href="<?= base_url('legalcase/contactos/nuevo?volver=' . rawurlencode($editing ? 'legalcase/asuntos/editar/' . (int) $row['id'] : 'legalcase/asuntos/nuevo')) ?>"><?= lang('Lgc.contactNewInline') ?></a>
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="lead_lawyer_id" class="form-label"><?= lang('Lgc.fLawyer') ?></label>
                            <select id="lead_lawyer_id" name="lead_lawyer_id" class="form-select">
                                <option value=""><?= lang('Lgc.none') ?></option>
                                <?php foreach ($users as $id => $name): ?>
                                    <option value="<?= (int) $id ?>" <?= (string) $v('lead_lawyer_id') === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secClassification') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secClassificationHint') ?></p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="form-group">
                            <label for="specialty" class="form-label"><?= lang('Lgc.fSpecialty') ?></label>
                            <select id="specialty" name="specialty" class="form-select">
                                <option value=""><?= lang('Lgc.none') ?></option>
                                <?php foreach (MatterModel::SPECIALTIES as $s): ?>
                                    <option value="<?= $s ?>" <?= $v('specialty') === $s ? 'selected' : '' ?>><?= lang('Lgc.spec_' . $s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="country" class="form-label"><?= lang('Lgc.fCountry') ?></label>
                            <input type="text" id="country" name="country" class="form-input uppercase" maxlength="2" value="<?= esc($v('country', 'CO'), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label for="jurisdiction" class="form-label"><?= lang('Lgc.fJurisdiction') ?></label>
                            <input type="text" id="jurisdiction" name="jurisdiction" class="form-input" maxlength="120"
                                value="<?= esc($v('jurisdiction'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phJurisdiction'), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label for="risk_level" class="form-label"><?= lang('Lgc.fRisk') ?></label>
                            <select id="risk_level" name="risk_level" class="form-select">
                                <?php foreach (MatterModel::RISKS as $r): ?>
                                    <option value="<?= $r ?>" <?= $v('risk_level', 'medium') === $r ? 'selected' : '' ?>><?= lang('Lgc.risk_' . $r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <?= view('Components\\LegalCase\\Views\\_conflicts') ?>

                <?php if (!empty($insider)): ?>
                <fieldset class="form-section">
                    <legend class="form-section-title"><i class="icon icon-lock"></i> <?= lang('Lgc.secInternalMatter') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secInternalMatterHint') ?></p>
                    <div class="form-group">
                        <label for="description" class="form-label"><?= lang('Lgc.fFacts') ?></label>
                        <textarea id="description" name="description" rows="3" class="form-textarea"><?= esc($v('description')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="objectives" class="form-label"><?= lang('Lgc.fObjectives') ?></label>
                        <textarea id="objectives" name="objectives" rows="2" class="form-textarea"><?= esc($v('objectives')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="strategy" class="form-label"><?= lang('Lgc.fStrategy') ?></label>
                        <textarea id="strategy" name="strategy" rows="3" class="form-textarea"><?= esc($v('strategy')) ?></textarea>
                        <small class="form-hint"><?= lang('Lgc.fStrategyHint') ?></small>
                    </div>
                </fieldset>
                <?php endif; ?>

                <fieldset class="form-section">
                    <legend class="form-section-title"><i class="icon icon-eye"></i> <?= lang('Lgc.secClientSees') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secClientSeesHint') ?></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="form-group">
                            <label for="public_title" class="form-label"><?= lang('Lgc.fPublicTitle') ?></label>
                            <input type="text" id="public_title" name="public_title" class="form-input" maxlength="190"
                                value="<?= esc($v('public_title'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phPublicTitle'), 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label for="public_stage" class="form-label"><?= lang('Lgc.fPublicStage') ?></label>
                            <input type="text" id="public_stage" name="public_stage" class="form-input" maxlength="120"
                                value="<?= esc($v('public_stage'), 'attr') ?>" placeholder="<?= esc(lang('Lgc.phPublicStage'), 'attr') ?>">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="stage_hidden" name="stage_hidden" value="1" class="form-check-input" <?= $v('stage_hidden') ? 'checked' : '' ?>>
                        <label for="stage_hidden" class="form-check-label">
                            <?= lang('Lgc.fStageHidden') ?>
                            <span class="block text-xs text-muted"><?= lang('Lgc.fStageHiddenHint') ?></span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Lgc.secVisibility') ?></legend>
                    <p class="form-section-hint"><?= lang('Lgc.secVisibilityHint') ?></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="form-group">
                            <label for="visibility" class="form-label"><?= lang('Lgc.fVisibility') ?></label>
                            <select id="visibility" name="visibility" class="form-select">
                                <?php foreach (MatterModel::VISIBILITIES as $o): ?>
                                    <option value="<?= $o ?>" <?= $v('visibility', 'firm') === $o ? 'selected' : '' ?>><?= lang('Lgc.visibility_' . $o) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sensitivity" class="form-label"><?= lang('Lgc.fSensitivity') ?></label>
                            <select id="sensitivity" name="sensitivity" class="form-select">
                                <?php foreach (MatterModel::SENSITIVITIES as $o): ?>
                                    <option value="<?= $o ?>" <?= $v('sensitivity', 'normal') === $o ? 'selected' : '' ?>><?= lang('Lgc.sensitivity_' . $o) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint"><?= lang('Lgc.fSensitivityHint') ?></small>
                        </div>
                    </div>
                </fieldset>

                <div class="mt-4 flex justify-end gap-2">
                    <a href="<?= base_url($editing ? 'legalcase/asuntos/ficha/' . (int) $row['id'] : 'legalcase/asuntos') ?>" class="btn btn-ghost"><?= lang('Lgc.cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lgc.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
