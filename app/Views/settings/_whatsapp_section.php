<?php
/**
 * Panel: WhatsApp Business — Meta Cloud API (Graph API).
 * Solo configuración + prueba de conexión (valida el token contra el Phone Number ID).
 * @var array $settings
 */
$enabled  = !empty($settings['whatsapp_enabled']);
$hasToken = !empty($settings['whatsapp_token']);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-whatsapp"></i>
        <h6 class="font-semibold">WhatsApp API (Meta Cloud API)</h6>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted">
            <i class="icon icon-info-circle"></i>
            API oficial de WhatsApp Business. Obtén el <strong>Phone Number ID</strong>, el
            <strong>token permanente</strong> y el <strong>Business Account ID</strong> desde
            Meta for Developers. Guarda antes de <strong>Probar conexión</strong>.
        </p>

        <label class="form-check mb-4" for="whatsapp_enabled">
            <input class="form-check-input" type="checkbox" id="whatsapp_enabled"
                name="whatsapp_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            <span class="form-check-label">Habilitar envío por WhatsApp</span>
        </label>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <div class="form-group">
                <label for="whatsapp_phone_number_id" class="form-label">Phone Number ID</label>
                <input type="text" class="form-input" id="whatsapp_phone_number_id" name="whatsapp_phone_number_id"
                    value="<?= esc($settings['whatsapp_phone_number_id'] ?? '', 'attr') ?>" placeholder="1234567890">
            </div>
            <div class="form-group">
                <label for="whatsapp_business_id" class="form-label">Business Account ID (WABA)</label>
                <input type="text" class="form-input" id="whatsapp_business_id" name="whatsapp_business_id"
                    value="<?= esc($settings['whatsapp_business_id'] ?? '', 'attr') ?>" placeholder="1234567890">
            </div>
        </div>

        <div class="form-group">
            <label for="whatsapp_token" class="form-label">Token permanente</label>
            <div class="input-group">
                <input type="password" class="form-input" id="whatsapp_token" name="whatsapp_token"
                    autocomplete="new-password" placeholder="<?= $hasToken ? '•••••••• (configurado)' : 'EAAG...' ?>">
                <button class="btn btn-outline rounded-l-none" type="button" onclick="togglePassword('whatsapp_token')">
                    <i class="icon icon-eye"></i>
                </button>
            </div>
            <small class="form-hint">Déjalo vacío para conservar el token actual.</small>
        </div>

        <div class="form-group w-full md:w-1/3">
            <label for="whatsapp_api_version" class="form-label">Versión de API</label>
            <input type="text" class="form-input" id="whatsapp_api_version" name="whatsapp_api_version"
                value="<?= esc($settings['whatsapp_api_version'] ?? 'v21.0', 'attr') ?>" placeholder="v21.0">
        </div>

        <div class="mt-2 flex items-center gap-3">
            <button type="button" class="btn btn-outline js-test-connection" data-service="whatsapp">
                <i class="icon icon-plug"></i> Probar conexión
            </button>
            <span class="js-test-result text-sm" data-for="whatsapp"></span>
        </div>
    </div>
</div>
