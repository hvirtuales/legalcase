<?php
/**
 * Panel: Almacenamiento de objetos (Amazon S3 / Cloudflare R2 / compatible).
 * Solo configuración: guarda credenciales y permite "Probar conexión".
 * El secreto no se muestra nunca; se conserva si el campo se envía vacío.
 * @var array $settings
 */
$driver     = $settings['storage_driver'] ?? 'local';
$hasSecret  = !empty($settings['storage_secret']);
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-hdd-network"></i>
        <h6 class="font-semibold">Almacenamiento de Archivos (S3 / Cloudflare R2)</h6>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted">
            <i class="icon icon-info-circle"></i>
            Configura un bucket compatible con S3 (Amazon S3 o Cloudflare R2) para almacenar archivos.
            Guarda los cambios antes de usar <strong>Probar conexión</strong>.
        </p>

        <div class="form-group w-full md:w-1/2">
            <label for="storage_driver" class="form-label">Proveedor</label>
            <select class="form-select" id="storage_driver" name="storage_driver">
                <option value="local" <?= $driver === 'local' ? 'selected' : '' ?>>Local (servidor, por defecto)</option>
                <option value="s3" <?= $driver === 's3' ? 'selected' : '' ?>>Amazon S3</option>
                <option value="r2" <?= $driver === 'r2' ? 'selected' : '' ?>>Cloudflare R2</option>
                <option value="s3compatible" <?= $driver === 's3compatible' ? 'selected' : '' ?>>Otro compatible con S3</option>
            </select>
            <small class="form-hint">Cloudflare R2 y otros compatibles requieren el campo <em>Endpoint</em>.</small>
        </div>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <div class="form-group">
                <label for="storage_key" class="form-label">Access Key ID</label>
                <input type="text" class="form-input" id="storage_key" name="storage_key" autocomplete="off"
                    value="<?= esc($settings['storage_key'] ?? '', 'attr') ?>" placeholder="AKIA... / R2 token">
            </div>
            <div class="form-group">
                <label for="storage_secret" class="form-label">Secret Access Key</label>
                <div class="input-group">
                    <input type="password" class="form-input" id="storage_secret" name="storage_secret"
                        autocomplete="new-password" placeholder="<?= $hasSecret ? '•••••••• (configurado)' : 'Secret key' ?>">
                    <button class="btn btn-outline rounded-l-none" type="button" onclick="togglePassword('storage_secret')">
                        <i class="icon icon-eye"></i>
                    </button>
                </div>
                <small class="form-hint">Déjalo vacío para conservar el secreto actual.</small>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <div class="form-group">
                <label for="storage_bucket" class="form-label">Bucket</label>
                <input type="text" class="form-input" id="storage_bucket" name="storage_bucket"
                    value="<?= esc($settings['storage_bucket'] ?? '', 'attr') ?>" placeholder="mi-bucket">
            </div>
            <div class="form-group">
                <label for="storage_region" class="form-label">Región</label>
                <input type="text" class="form-input" id="storage_region" name="storage_region"
                    value="<?= esc($settings['storage_region'] ?? '', 'attr') ?>" placeholder="us-east-1 (R2: auto)">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <div class="form-group">
                <label for="storage_endpoint" class="form-label">Endpoint</label>
                <input type="text" class="form-input" id="storage_endpoint" name="storage_endpoint"
                    value="<?= esc($settings['storage_endpoint'] ?? '', 'attr') ?>"
                    placeholder="https://<accountid>.r2.cloudflarestorage.com">
                <small class="form-hint">Requerido para R2 / compatibles. Deja vacío para Amazon S3.</small>
            </div>
            <div class="form-group">
                <label for="storage_public_url" class="form-label">URL pública / CDN</label>
                <input type="text" class="form-input" id="storage_public_url" name="storage_public_url"
                    value="<?= esc($settings['storage_public_url'] ?? '', 'attr') ?>"
                    placeholder="https://cdn.midominio.com">
                <small class="form-hint">Base para servir los archivos públicamente (opcional).</small>
            </div>
        </div>

        <div class="mt-2 flex items-center gap-3">
            <button type="button" class="btn btn-outline js-test-connection" data-service="storage">
                <i class="icon icon-plug"></i> Probar conexión
            </button>
            <span class="js-test-result text-sm" data-for="storage"></span>
        </div>
    </div>
</div>
