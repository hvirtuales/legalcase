<?php
/**
 * Panel: Correo saliente. Contiene el SMTP existente + Amazon SES (API).
 * SES: solo configuración + prueba (getSendQuota). El envío real de la app
 * sigue usando la configuración SMTP por ahora.
 * @var array $settings
 */
$hasSesSecret = !empty($settings['ses_secret']);
?>

<!-- SMTP -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-envelope"></i>
        <h6 class="font-semibold">Configuración de Correo (SMTP)</h6>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
            <div class="form-group md:col-span-2">
                <label for="smtp_host" class="form-label">Servidor SMTP (Host)</label>
                <input type="text" class="form-input" id="smtp_host" name="smtp_host"
                    value="<?= old('smtp_host', $settings['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
            </div>
            <div class="form-group">
                <label for="smtp_port" class="form-label">Puerto</label>
                <input type="number" class="form-input" id="smtp_port" name="smtp_port"
                    value="<?= old('smtp_port', $settings['smtp_port'] ?? '587') ?>">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <div class="form-group">
                <label for="smtp_user" class="form-label">Usuario SMTP</label>
                <input type="text" class="form-input" id="smtp_user" name="smtp_user"
                    value="<?= old('smtp_user', $settings['smtp_user'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="smtp_pass" class="form-label">Contraseña SMTP</label>
                <div class="input-group">
                    <input type="password" class="form-input" id="smtp_pass" name="smtp_pass"
                        autocomplete="new-password"
                        placeholder="<?= !empty($settings['smtp_pass']) ? '•••••••• (configurada)' : '******' ?>">
                    <button class="btn btn-outline rounded-l-none" type="button"
                        onclick="togglePassword('smtp_pass')">
                        <i class="icon icon-eye"></i>
                    </button>
                </div>
                <small class="form-hint">Dejar vacío para mantener la contraseña actual.</small>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
            <div class="form-group">
                <label for="smtp_crypto" class="form-label">Cifrado</label>
                <select class="form-select" id="smtp_crypto" name="smtp_crypto">
                    <option value="tls" <?= ($settings['smtp_crypto'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Recomendado)</option>
                    <option value="ssl" <?= ($settings['smtp_crypto'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="" <?= ($settings['smtp_crypto'] ?? '') === '' ? 'selected' : '' ?>>Ninguno</option>
                </select>
            </div>
            <div class="form-group md:col-span-2">
                <label for="smtp_from_email" class="form-label">Email Remitente (From)</label>
                <input type="email" class="form-input" id="smtp_from_email" name="smtp_from_email"
                    value="<?= old('smtp_from_email', $settings['smtp_from_email'] ?? '') ?>"
                    placeholder="noreply@example.com">
            </div>
        </div>
    </div>
</div>

<!-- Amazon SES -->
<div class="card">
    <div class="card-header">
        <i class="icon icon-amazon"></i>
        <h6 class="font-semibold">Amazon SES (API)</h6>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted">
            <i class="icon icon-info-circle"></i>
            Credenciales IAM con permiso <code>ses:SendEmail</code>. La prueba consulta la cuota de envío
            (<code>GetSendQuota</code>). Guarda antes de <strong>Probar conexión</strong>.
        </p>

        <label class="form-check mb-4" for="ses_enabled">
            <input class="form-check-input" type="checkbox" id="ses_enabled"
                name="ses_enabled" value="1" <?= !empty($settings['ses_enabled']) ? 'checked' : '' ?>>
            <span class="form-check-label">Habilitar Amazon SES</span>
        </label>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <div class="form-group">
                <label for="ses_key" class="form-label">Access Key ID</label>
                <input type="text" class="form-input" id="ses_key" name="ses_key" autocomplete="off"
                    value="<?= esc($settings['ses_key'] ?? '', 'attr') ?>" placeholder="AKIA...">
            </div>
            <div class="form-group">
                <label for="ses_secret" class="form-label">Secret Access Key</label>
                <div class="input-group">
                    <input type="password" class="form-input" id="ses_secret" name="ses_secret"
                        autocomplete="new-password" placeholder="<?= $hasSesSecret ? '•••••••• (configurado)' : 'Secret key' ?>">
                    <button class="btn btn-outline rounded-l-none" type="button" onclick="togglePassword('ses_secret')">
                        <i class="icon icon-eye"></i>
                    </button>
                </div>
                <small class="form-hint">Déjalo vacío para conservar el secreto actual.</small>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <div class="form-group">
                <label for="ses_region" class="form-label">Región</label>
                <input type="text" class="form-input" id="ses_region" name="ses_region"
                    value="<?= esc($settings['ses_region'] ?? '', 'attr') ?>" placeholder="us-east-1">
            </div>
            <div class="form-group">
                <label for="ses_from_email" class="form-label">Email Remitente verificado</label>
                <input type="email" class="form-input" id="ses_from_email" name="ses_from_email"
                    value="<?= esc($settings['ses_from_email'] ?? '', 'attr') ?>" placeholder="noreply@midominio.com">
            </div>
        </div>

        <div class="form-group">
            <label for="ses_configuration_set" class="form-label">Conjunto de configuración (opcional)</label>
            <input type="text" class="form-input" id="ses_configuration_set" name="ses_configuration_set"
                value="<?= esc($settings['ses_configuration_set'] ?? '', 'attr') ?>" placeholder="mi-conjunto">
            <small class="form-hint">
                Es lo que hace que SES publique los <strong>rebotes y las quejas</strong> en un tema de SNS.
                Sin él, los envíos funcionan pero nadie se entera de qué direcciones rebotaron y la lista de
                supresión solo crece con las bajas manuales. Créalo en la consola de AWS (Configuration sets),
                añádele un destino de eventos hacia SNS y suscribe ahí la URL del webhook.
            </small>
        </div>

        <div class="mt-2 flex items-center gap-3">
            <button type="button" class="btn btn-outline js-test-connection" data-service="ses">
                <i class="icon icon-plug"></i> Probar conexión
            </button>
            <span class="js-test-result text-sm" data-for="ses"></span>
        </div>
    </div>
</div>
