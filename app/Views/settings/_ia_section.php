<?php
/**
 * Panel: Motor de IA (Claude / Gemini / OpenAI). Proveedor + claves + modelos + prueba.
 * Las claves se guardan cifradas y solo se actualizan si se envía una nueva.
 *
 * Los proveedores salen de `AiClient::PROVIDERS`: añadir uno allí lo hace
 * aparecer aquí sin tocar esta vista.
 *
 * @var array $settings
 */
$providers = \App\Libraries\AiClient::PROVIDERS;
$current   = array_key_exists($settings['ai_provider'] ?? '', $providers) ? $settings['ai_provider'] : 'gemini';

/** Pistas por proveedor: [placeholder de la clave, dónde se obtiene]. */
$hints = [
    'anthropic' => ['sk-ant-...', 'console.anthropic.com → API keys.'],
    'gemini'    => ['AIza...', 'Google AI Studio → API keys.'],
    'openai'    => ['sk-...', 'platform.openai.com → API keys.'],
];
?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-cpu"></i>
        <h6 class="font-semibold">Motor de IA</h6>
    </div>
    <div class="card-body">
        <p class="mb-4 text-sm text-muted">
            <i class="icon icon-info-circle"></i>
            Conecta un proveedor de IA generativa para los asistentes (borrador de respuesta de PQRSF,
            recordatorios de cobro, plan estratégico, etc.).
            Las claves se guardan <strong>cifradas</strong> y nunca se exponen al navegador. Guarda antes de <strong>Probar conexión</strong>.
        </p>

        <div class="form-group w-full md:w-1/2">
            <label for="ai_provider" class="form-label">Proveedor</label>
            <select class="form-select" id="ai_provider" name="ai_provider">
                <?php foreach ($providers as $code => [$label, $defaultModel]): ?>
                    <option value="<?= esc($code, 'attr') ?>" <?= $current === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="form-hint">El proveedor seleccionado es el que se usará en las llamadas de IA.</small>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <?php foreach ($providers as $code => [$label, $defaultModel]): ?>
                <?php
                $has = !empty($settings[$code . '_api_key']);
                [$ph, $where] = $hints[$code] ?? ['', ''];
                ?>
                <fieldset class="rounded-lg border p-3 <?= $current === $code ? 'border-primary' : 'border-line' ?>">
                    <legend class="px-1 text-sm font-semibold">
                        <?= esc($label) ?>
                        <?php if ($current === $code): ?>
                            <span class="badge badge-primary ml-1">En uso</span>
                        <?php endif; ?>
                    </legend>
                    <div class="form-group">
                        <label for="<?= $code ?>_api_key" class="form-label">API Key</label>
                        <div class="input-group">
                            <input type="password" class="form-input" id="<?= $code ?>_api_key" name="<?= $code ?>_api_key"
                                autocomplete="new-password" placeholder="<?= $has ? '•••••••• (configurada)' : esc($ph, 'attr') ?>">
                            <button class="btn btn-outline rounded-l-none" type="button" onclick="togglePassword('<?= $code ?>_api_key')"><i class="icon icon-eye"></i></button>
                        </div>
                        <small class="form-hint"><?= esc($where) ?> Déjalo vacío para conservar la actual.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label for="<?= $code ?>_model" class="form-label">Modelo</label>
                        <input type="text" class="form-input" id="<?= $code ?>_model" name="<?= $code ?>_model"
                            value="<?= esc($settings[$code . '_model'] ?? $defaultModel, 'attr') ?>" placeholder="<?= esc($defaultModel, 'attr') ?>">
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 flex items-center gap-3">
            <button type="button" class="btn btn-outline js-test-connection" data-service="ai">
                <i class="icon icon-plug"></i> Probar conexión
            </button>
            <span class="js-test-result text-sm" data-for="ai"></span>
        </div>
    </div>
</div>
