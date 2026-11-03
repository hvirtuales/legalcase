<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Perfil de red. Los campos van agrupados en secciones con borde y en el orden en
 * que se toman las decisiones: primero SI aparezco, luego CÓMO me presento y por
 * último QUÉ datos de contacto expongo.
 *
 * @var array      $profile
 * @var array|null $user
 */
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-person"></i>
        <h5 class="font-semibold"><?= lang('Net.myProfile') ?></h5>
    </div>
    <div class="card-body">
        <?= view('Components\Networking\Views\_tabs', ['counts' => $counts, 'current' => 'mi-perfil']) ?>

        <?php if (!($planEnabled ?? true)): ?>
            <div class="alert alert-warning"><i class="icon icon-exclamation-circle"></i><span><?= lang('Net.planDisabled') ?></span></div>
        <?php endif; ?>

        <form action="<?= base_url('networking/mi-perfil') ?>" method="post">
            <?= csrf_field() ?>

            <!-- 1. ¿Aparezco en la red? -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Net.visibility') ?></legend>
                <p class="form-section-hint"><?= lang('Net.visibilityHint') ?></p>

                <label class="form-check mb-1" for="share_profile">
                    <input type="checkbox" class="form-check-input" id="share_profile" name="share_profile" value="1"
                        <?= !empty($profile['share_profile']) ? 'checked' : '' ?>>
                    <span class="form-check-label">
                        <span class="font-semibold"><?= lang('Net.shareProfile') ?></span>
                        <span class="mt-0.5 block text-xs text-muted"><?= lang('Net.shareProfileHint') ?></span>
                    </span>
                </label>
            </fieldset>

            <!-- 2. Mi rol: lo único que la plataforma no sabe ya -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Net.presentation') ?></legend>
                <p class="form-section-hint"><?= lang('Net.presentationHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="position" class="form-label"><?= lang('Net.position') ?></label>
                        <input type="text" class="form-input" id="position" name="position" maxlength="120"
                            value="<?= esc(old('position', $profile['position'] ?? ''), 'attr') ?>"
                            placeholder="<?= esc(lang('Net.positionPlaceholder'), 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="branch" class="form-label"><?= lang('Net.branch') ?></label>
                        <input type="text" class="form-input" id="branch" name="branch" maxlength="120"
                            value="<?= esc(old('branch', $profile['branch'] ?? ''), 'attr') ?>"
                            placeholder="<?= esc(lang('Net.branchPlaceholder'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Net.branchHint') ?></small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="interests" class="form-label"><?= lang('Net.interests') ?></label>
                    <input type="text" class="form-input" id="interests" name="interests" maxlength="255"
                        value="<?= esc(old('interests', $profile['interests'] ?? ''), 'attr') ?>"
                        placeholder="<?= esc(lang('Net.interestsPlaceholder'), 'attr') ?>">
                </div>

                <div class="form-group mb-2">
                    <label for="bio" class="form-label"><?= lang('Net.about') ?></label>
                    <textarea class="form-textarea" id="bio" name="bio" rows="3"><?= esc(old('bio', $profile['bio'] ?? '')) ?></textarea>
                </div>
            </fieldset>

            <!-- 3. Empresa: viene de la relación, no se escribe a mano -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Net.companySection') ?></legend>
                <p class="form-section-hint"><?= lang('Net.companySectionHint') ?></p>

                <?php if (empty($companies)): ?>
                    <p class="mb-2 text-sm text-muted"><i class="icon icon-info-circle"></i> <?= lang('Net.noCompany') ?></p>
                <?php else: ?>
                    <?php if (count($companies) > 1): ?>
                        <div class="form-group">
                            <label for="company_id" class="form-label"><?= lang('Net.companyShown') ?></label>
                            <select class="form-select" id="company_id" name="company_id">
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($profile['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= esc($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="company_id" value="<?= (int) $companies[0]['id'] ?>">
                    <?php endif; ?>

                    <!-- Vista previa de lo que verán los demás -->
                    <?php if (!empty($card)): ?>
                        <div class="mt-2 max-w-sm">
                            <p class="mb-2 text-xs font-semibold text-muted"><?= lang('Net.cardPreview') ?></p>
                            <?= view('Components\Networking\Views\_card', ['card' => $card, 'showForm' => false]) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($canEditCompany)): ?>
                        <p class="mt-3 text-xs text-muted">
                            <i class="icon icon-info-circle"></i> <?= lang('Net.brandHint') ?>
                            <a href="<?= base_url('mi-empresa') ?>" class="text-primary"><?= lang('Net.brandLink') ?></a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </fieldset>

            <!-- 3. Qué datos de contacto expongo -->
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Net.contactData') ?></legend>
                <p class="form-section-hint"><?= lang('Net.contactDataHint') ?></p>

                <label class="form-check mb-2" for="show_email">
                    <input type="checkbox" class="form-check-input" id="show_email" name="show_email" value="1"
                        <?= !empty($profile['show_email']) ? 'checked' : '' ?>>
                    <span class="form-check-label">
                        <?= lang('Net.showEmail') ?> <span class="text-muted">(<?= esc($user['email'] ?? '') ?>)</span>
                    </span>
                </label>

                <label class="form-check mb-1" for="show_phone">
                    <input type="checkbox" class="form-check-input" id="show_phone" name="show_phone" value="1"
                        <?= !empty($profile['show_phone']) ? 'checked' : '' ?>>
                    <span class="form-check-label">
                        <?= lang('Net.showPhone') ?>
                        <span class="text-muted">(<?= esc($user['phone'] ?: lang('Net.noPhone')) ?>)</span>
                    </span>
                </label>

                <?php if (empty($user['phone'])): ?>
                    <p class="mb-2 mt-2 text-xs text-muted">
                        <i class="icon icon-info-circle"></i> <?= lang('Net.noPhoneHint') ?>
                        <a href="<?= base_url('profile') ?>" class="text-primary"><?= lang('Net.myProfile') ?></a>
                    </p>
                <?php endif; ?>
            </fieldset>

            <div class="flex flex-wrap gap-2">
                <button class="btn btn-primary"><i class="icon icon-check-circle"></i> <?= lang('Net.save') ?></button>
                <a href="<?= base_url('networking/perfil/' . (int) $me) ?>" class="btn btn-secondary">
                    <i class="icon icon-eye"></i> <?= lang('Net.previewAsOthers') ?>
                </a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
