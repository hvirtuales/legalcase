<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/*
 * Conexión de la firma con USCIS.
 *
 * **La pantalla explica por qué las credenciales son suyas y no de la plataforma**, y
 * no por cortesía: pedirle a una firma que se registre en un portal ajeno sin decirle
 * para qué es la forma más rápida de que no lo haga. El motivo es el cupo —1.000
 * consultas al día por credencial—, y entenderlo es lo que hace que la firma acepte el
 * trámite.
 *
 * El **secret nunca se muestra**. USCIS no lo deja volver a leer en su portal, así que
 * si se perdiera aquí no habría forma de recuperarlo; se indica si hay uno guardado y
 * se deja el campo vacío, donde vacío significa «no lo cambio».
 *
 * El botón de prueba es lo que convierte esto en algo verificable: sin él, una
 * credencial mal pegada se descubre el día que un cliente consulta su caso.
 *
 * @var array      $cfg      client_id, mode, configured
 * @var array      $samples  recibos de prueba del sandbox
 * @var bool       $canConfigure
 */
$diag = session()->getFlashdata('lfl_diag');
?>
<div class="card">
    <div class="card-header">
        <div class="flex items-center gap-2">
            <i class="icon icon-landmark"></i>
            <h5 class="font-semibold"><?= lang('Lfl.uscTitle') ?></h5>
            <span class="badge <?= $cfg['mode'] === 'live' ? 'badge-success' : 'badge-warning' ?>">
                <?= lang('Lfl.uscMode_' . $cfg['mode']) ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_settings_tabs', ['active' => 'uscis', 'canConfigure' => $canConfigure]) ?>

        <p class="mb-4 text-sm text-muted"><?= lang('Lfl.uscIntro') ?></p>

        <div class="alert alert-info mb-4">
            <i class="icon icon-info-circle"></i>
            <span><?= lang('Lfl.uscWhyOwn') ?></span>
        </div>

        <?php if ($diag): ?>
            <?= view('Components\LeadFlow\Views\partials\_diagnostic', ['diag' => $diag]) ?>
        <?php endif; ?>

        <form method="post" action="<?= base_url('leadflow/configuracion/uscis') ?>">
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.uscCredentials') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.uscCredentialsHint') ?></p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="uscis_client_id" class="form-label"><?= lang('Lfl.uscClientId') ?></label>
                        <input type="text" class="form-input" id="uscis_client_id" name="uscis_client_id"
                            value="<?= esc(old('uscis_client_id', $cfg['client_id']), 'attr') ?>" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="uscis_client_secret" class="form-label"><?= lang('Lfl.uscClientSecret') ?></label>
                        <input type="password" class="form-input" id="uscis_client_secret" name="uscis_client_secret"
                            autocomplete="new-password" placeholder="<?= esc(lang($cfg['configured'] ? 'Lfl.uscSecretKeep' : 'Lfl.uscSecretEmpty'), 'attr') ?>">
                        <small class="form-hint"><?= lang('Lfl.uscSecretHint') ?></small>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title"><?= lang('Lfl.uscModeTitle') ?></legend>
                <p class="form-section-hint"><?= lang('Lfl.uscModeHint') ?></p>

                <?php foreach (['simulated', 'live'] as $m): ?>
                    <label class="form-check mb-1" for="mode-<?= esc($m, 'attr') ?>">
                        <input class="form-check-input" type="radio" id="mode-<?= esc($m, 'attr') ?>"
                            name="uscis_mode" value="<?= esc($m, 'attr') ?>"
                            <?= old('uscis_mode', $cfg['mode']) === $m ? 'checked' : '' ?>>
                        <span class="form-check-label">
                            <strong><?= lang('Lfl.uscMode_' . $m) ?></strong> —
                            <span class="text-muted"><?= lang('Lfl.uscModeDesc_' . $m) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="icon icon-save"></i> <?= lang('Lfl.save') ?>
                </button>
            </div>
        </form>

        <?php // La prueba y la desconexión van en formularios APARTE: el de arriba
              // guarda, y un botón que envía otra cosa dentro del mismo formulario
              // obligaría a adivinar cuál de las dos acciones se pidió. ?>
        <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
            <form method="post" action="<?= base_url('leadflow/configuracion/uscis/probar') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="icon icon-plug"></i> <?= lang('Lfl.uscTest') ?>
                </button>
            </form>

            <?php if ($cfg['configured']): ?>
                <form method="post" action="<?= base_url('leadflow/configuracion/uscis/desconectar') ?>"
                    onsubmit="return confirm('<?= esc(lang('Lfl.uscDisconnectConfirm'), 'js') ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm text-danger">
                        <i class="icon icon-x-circle"></i> <?= lang('Lfl.uscDisconnect') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="mt-4 rounded border border-line p-4">
            <p class="mb-1 text-sm font-semibold"><?= lang('Lfl.uscSamplesTitle') ?></p>
            <p class="mb-2 text-xs text-muted"><?= lang('Lfl.uscSamplesHint') ?></p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($samples as $r): ?>
                    <code class="badge badge-light"><?= esc($r) ?></code>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
