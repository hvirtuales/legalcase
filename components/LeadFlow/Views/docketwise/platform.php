<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/*
 * La app OAuth de la PLATAFORMA ante Docketwise: una sola para todas las firmas,
 * y por eso solo la configura el Super Admin. Cada firma autoriza después su propia
 * cuenta desde «Docketwise → Conexión». El secreto nunca se vuelve a mostrar.
 */
?>
<?= view('Components\LeadFlow\Views\partials\_diagnostic', ['diag' => $diag ?? null]) ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2"><i class="icon icon-key"></i><h5 class="font-semibold"><?= lang('Lfl.dwPlatform') ?></h5></div>
        <a href="<?= base_url('leadflow/docketwise') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-arrow-left"></i> <?= lang('Lfl.back') ?></a>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.platformIntro') ?></p>
        <form action="<?= base_url('leadflow/docketwise/plataforma') ?>" method="post">
            <?= csrf_field() ?>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.mode') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.platformModeHint') ?></p>
                <?php foreach (['simulated' => ['modeSimulated', 'modeSimulatedHint'], 'live' => ['modeLive', 'modeLiveHint']] as $mode => [$label, $hint]): ?>
                    <label class="form-check mb-1">
                        <input type="radio" name="mode" value="<?= esc($mode, 'attr') ?>" class="form-check-input" <?= $provider['mode'] === $mode ? 'checked' : '' ?>>
                        <span class="form-check-label"><strong><?= lang('Lfl.' . $label) ?></strong> — <?= lang('Lfl.' . $hint) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.platformCredentials') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.platformCredentialsHint') ?></p>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="client_id" class="form-label">Client ID</label>
                        <input type="text" id="client_id" name="client_id" maxlength="255" class="form-input" value="<?= esc((string) $provider['client_id'], 'attr') ?>">
                    </div>
                    <div class="form-group">
                        <label for="client_secret" class="form-label">Client secret</label>
                        <input type="password" id="client_secret" name="client_secret" autocomplete="new-password" class="form-input"
                            placeholder="<?= esc(lang(empty($provider['secret']) ? 'Lfl.secretEmpty' : 'Lfl.secretConfigured'), 'attr') ?>">
                        <p class="form-hint"><?= lang('Lfl.secretHint') ?></p>
                    </div>
                    <div class="form-group">
                        <label for="redirect_uri" class="form-label">Redirect URI</label>
                        <input type="url" id="redirect_uri" name="redirect_uri" maxlength="255" class="form-input" value="<?= esc((string) $provider['redirect_uri'], 'attr') ?>"
                            placeholder="<?= esc($callbackUrl, 'attr') ?>">
                        <p class="form-hint"><?= esc(lang('Lfl.redirectHint', [$callbackUrl])) ?></p>
                    </div>
                    <div class="form-group">
                        <label for="scope" class="form-label">Scope</label>
                        <input type="text" id="scope" name="scope" maxlength="100" class="form-input" value="<?= esc((string) $provider['scope'], 'attr') ?>">
                    </div>
                </div>
            </fieldset>
            <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
        </form>

        <?php /* Fuera del formulario de arriba: valida lo GUARDADO, no lo que se esté escribiendo. */ ?>
        <div class="mt-6 border-t border-line pt-4">
            <form action="<?= base_url('leadflow/docketwise/plataforma/validar') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline"><i class="icon icon-shield-check"></i> <?= lang('Lfl.dwValidateApp') ?></button>
            </form>
            <p class="mt-2 text-xs text-muted"><?= lang('Lfl.dwValidateAppHint') ?></p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
