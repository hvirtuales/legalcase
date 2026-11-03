<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <i class="icon icon-gear"></i>
        <h5 class="font-semibold"><?= lang('App.systemSettings') ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-error">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Navegación por pestañas -->
        <nav class="tabs" role="tablist" aria-label="<?= lang('App.systemSettings') ?>">
            <button type="button" class="tab-link active" data-tab="general">
                <i class="icon icon-info-circle"></i> General
            </button>
            <button type="button" class="tab-link" data-tab="appearance">
                <i class="icon icon-palette"></i> Apariencia
            </button>
            <button type="button" class="tab-link" data-tab="auth">
                <i class="icon icon-shield-lock"></i> Autenticación
            </button>
            <button type="button" class="tab-link" data-tab="email">
                <i class="icon icon-envelope"></i> Correo
            </button>
            <button type="button" class="tab-link" data-tab="storage">
                <i class="icon icon-hdd-network"></i> Almacenamiento
            </button>
            <button type="button" class="tab-link" data-tab="whatsapp">
                <i class="icon icon-whatsapp"></i> WhatsApp
            </button>
            <button type="button" class="tab-link" data-tab="ai">
                <i class="icon icon-cpu"></i> IA
            </button>
            <button type="button" class="tab-link" data-tab="support">
                <i class="icon icon-headset"></i> Soporte
            </button>
        </nav>

        <form action="<?= base_url('settings') ?>" method="post" enctype="multipart/form-data" id="settingsForm">
            <?= csrf_field() ?>

            <!-- ===== General ===== -->
            <section class="tab-panel" data-panel="general">
                <div class="card">
                    <div class="card-header">
                        <i class="icon icon-info-circle"></i>
                        <h6 class="font-semibold"><?= lang('App.generalInformation') ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="app_name" class="form-label"><?= lang('App.applicationName') ?></label>
                            <input type="text" class="form-input" id="app_name" name="app_name"
                                value="<?= old('app_name', $settings['app_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="app_logo" class="form-label">Logo de la Aplicación</label>
                            <?php if (!empty($settings['app_logo'])): ?>
                                <div class="mb-2">
                                    <img src="<?= media_url($settings['app_logo']) ?>" alt="App Logo"
                                        class="max-w-[150px] rounded bg-canvas p-1">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-input" id="app_logo" name="app_logo" data-preview
                                accept="image/jpeg,image/jpg,image/png,image/webp">
                            <small class="form-hint">Se optimizará a un ancho máximo de 300px.</small>
                        </div>

                        <?php
                        /*
                         * Ranura de los componentes para esta pestaña, justo bajo el logo.
                         *
                         * Las vistas que se pintan aquí traen **campos sueltos, nunca un
                         * `<form>`**: esta pantalla entera es un único formulario y uno
                         * anidado es HTML inválido. Lo que envíen lo recoge el `handler`
                         * que cada componente declara en su Manifest.
                         */
                        foreach ((new \App\Libraries\ComponentManager())->settingsSlots('general') as $slot): ?>
                            <?= view($slot['view'], ['settings' => $settings]) ?>
                        <?php endforeach; ?>

                        <div class="form-group">
                            <label for="app_description" class="form-label"><?= lang('App.description') ?></label>
                            <textarea class="form-textarea" id="app_description" name="app_description"
                                rows="3"><?= old('app_description', $settings['app_description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== Apariencia ===== -->
            <section class="tab-panel" data-panel="appearance" hidden>
                <?= view('settings/_theme_section', ['settings' => $settings]) ?>
            </section>

            <!-- ===== Autenticación ===== -->
            <section class="tab-panel" data-panel="auth" hidden>
                <div class="card">
                    <div class="card-header">
                        <i class="icon icon-shield-lock"></i>
                        <h6 class="font-semibold"><?= lang('App.authMethods') ?></h6>
                    </div>
                    <div class="card-body">
                        <label class="form-check mb-3" for="auth_registration_enabled">
                            <input class="form-check-input" type="checkbox" id="auth_registration_enabled"
                                name="auth_registration_enabled" value="1"
                                <?= !empty($settings['public_registration_enabled']) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= lang('App.allowPublicRegistration') ?></span>
                        </label>

                        <label class="form-check mb-3" for="auth_magic_link_enabled">
                            <input class="form-check-input" type="checkbox" id="auth_magic_link_enabled"
                                name="auth_magic_link_enabled" value="1" <?= !empty($settings['auth_magic_link_enabled']) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= lang('App.enableMagicLinks') ?></span>
                        </label>

                        <label class="form-check mb-3" for="auth_oauth_enabled">
                            <input class="form-check-input" type="checkbox" id="auth_oauth_enabled"
                                name="auth_oauth_enabled" value="1" <?= !empty($settings['auth_oauth_enabled']) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= lang('App.enableOAuth') ?></span>
                        </label>

                        <div class="form-group w-full md:w-1/2">
                            <label for="auth_max_attempts" class="form-label"><?= lang('App.maxFailedAttempts') ?></label>
                            <input type="number" class="form-input" id="auth_max_attempts" name="auth_max_attempts"
                                value="<?= old('auth_max_attempts', $settings['auth_max_attempts'] ?? '5') ?>" min="1" max="10">
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== Correo (SMTP + SES) ===== -->
            <section class="tab-panel" data-panel="email" hidden>
                <?= view('settings/_email_section', ['settings' => $settings]) ?>
            </section>

            <!-- ===== Almacenamiento (S3 / R2) ===== -->
            <section class="tab-panel" data-panel="storage" hidden>
                <?= view('settings/_storage_section', ['settings' => $settings]) ?>
            </section>

            <!-- ===== WhatsApp ===== -->
            <section class="tab-panel" data-panel="whatsapp" hidden>
                <?= view('settings/_whatsapp_section', ['settings' => $settings]) ?>
            </section>

            <!-- ===== IA ===== -->
            <section class="tab-panel" data-panel="ai" hidden>
                <?= view('settings/_ia_section', ['settings' => $settings]) ?>
            </section>

            <!-- ===== Soporte ===== -->
            <section class="tab-panel" data-panel="support" hidden>
                <div class="card">
                    <div class="card-header">
                        <i class="icon icon-headset"></i>
                        <h6 class="font-semibold"><?= lang('App.supportContact') ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                            <div class="form-group">
                                <label for="support_phone" class="form-label">Teléfono / WhatsApp</label>
                                <input type="tel" class="form-input" id="support_phone" name="support_phone" data-phone
                                    value="<?= old('support_phone', $settings['support_phone'] ?? '') ?>"
                                    placeholder="300 123 4567">
                                <small class="form-hint">Incluye el código de país sin el signo + para el enlace de WhatsApp.</small>
                            </div>
                            <div class="form-group">
                                <label for="support_email" class="form-label">Correo de Soporte</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="icon icon-envelope"></i></span>
                                    <input type="email" class="form-input" id="support_email" name="support_email"
                                        value="<?= old('support_email', $settings['support_email'] ?? '') ?>"
                                        placeholder="soporte@empresa.com">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Acciones (siempre visibles) -->
            <div class="mt-6 flex gap-2 border-t border-line pt-6">
                <button type="submit" class="btn btn-primary">
                    <i class="icon icon-save"></i>
                    <?= lang('App.saveConfiguration') ?>
                </button>
                <a href="<?= base_url('dashboard') ?>" class="btn btn-secondary">
                    <i class="icon icon-x-circle"></i>
                    <?= lang('App.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = field.parentNode.querySelector('button i');
        if (field.type === "password") {
            field.type = "text";
            icon.classList.replace("icon-eye", "icon-eye-slash");
        } else {
            field.type = "password";
            icon.classList.replace("icon-eye-slash", "icon-eye");
        }
    }

    // ---- Navegación por pestañas ----
    (function () {
        const STORAGE_KEY = 'settingsActiveTab';
        const links = document.querySelectorAll('.tab-link');
        const panels = document.querySelectorAll('.tab-panel');

        function activate(tab) {
            links.forEach(l => l.classList.toggle('active', l.dataset.tab === tab));
            panels.forEach(p => { p.hidden = (p.dataset.panel !== tab); });
            try { sessionStorage.setItem(STORAGE_KEY, tab); } catch (e) {}
        }

        links.forEach(link => link.addEventListener('click', () => activate(link.dataset.tab)));

        // Restaurar última pestaña (p.ej. tras guardar y redirigir)
        let initial = 'general';
        try {
            const saved = sessionStorage.getItem(STORAGE_KEY);
            if (saved && document.querySelector('.tab-link[data-tab="' + saved + '"]')) initial = saved;
        } catch (e) {}
        if (window.location.hash) {
            const h = window.location.hash.slice(1);
            if (document.querySelector('.tab-link[data-tab="' + h + '"]')) initial = h;
        }
        activate(initial);
    })();

    // ---- Probar conexión (S3/R2, WhatsApp, SES) ----
    (function () {
        const csrfName = '<?= csrf_token() ?>';
        const buttons = document.querySelectorAll('.js-test-connection');

        buttons.forEach(btn => btn.addEventListener('click', async () => {
            const service = btn.dataset.service;
            const result = document.querySelector('.js-test-result[data-for="' + service + '"]');
            const original = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="icon icon-arrow-repeat"></i> Probando...';
            if (result) { result.textContent = ''; result.className = 'js-test-result text-sm'; }

            try {
                const form = new FormData();
                // token CSRF actual del formulario
                const csrfInput = document.querySelector('input[name="' + csrfName + '"]');
                if (csrfInput) form.append(csrfName, csrfInput.value);

                const res = await fetch('<?= base_url('settings/test') ?>/' + service, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: form
                });
                const data = await res.json();

                // Refrescar el token CSRF si el servidor devuelve uno nuevo
                if (data.csrf && csrfInput) csrfInput.value = data.csrf;

                if (result) {
                    result.textContent = (data.ok ? '✓ ' : '✗ ') + (data.message || '');
                    result.className = 'js-test-result text-sm ' + (data.ok ? 'text-success' : 'text-danger');
                }
            } catch (e) {
                if (result) {
                    result.textContent = '✗ No se pudo contactar al servidor.';
                    result.className = 'js-test-result text-sm text-danger';
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }));
    })();
</script>
<?= $this->endSection() ?>
