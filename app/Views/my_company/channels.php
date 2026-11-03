<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php // Misma vista para «Mi empresa» y «Mi grupo»: cambia $base, no la forma.
$base = $base ?? 'mi-empresa'; ?>
<?php $v = static fn (string $k, $def = '') => esc($config[$k] ?? $def, 'attr'); ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-buildings"></i>
        <h5 class="font-semibold">Mi empresa</h5>
    </div>
    <div class="card-body">
        <!-- Pestañas -->
        <div class="mb-4 flex flex-wrap gap-1 border-b border-line">
            <a href="<?= base_url($base) ?>" class="border-b-2 px-4 py-2 text-sm <?= $tab === 'datos' ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">Datos</a>
            <a href="<?= base_url($base . '/canales') ?>" class="border-b-2 px-4 py-2 text-sm <?= $tab === 'canales' ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">Canales de comunicación</a>
        </div>

        <p class="mb-4 text-sm text-muted"><i class="icon icon-info-circle"></i> Credenciales de esta empresa para el envío de notificaciones/recordatorios de <strong>todos los módulos</strong>. Los secretos se guardan <strong>cifrados</strong> y no se muestran.</p>

        <nav class="tabs" role="tablist">
            <button type="button" class="tab-link active" data-tab="smtp"><i class="icon icon-envelope"></i> SMTP</button>
            <button type="button" class="tab-link" data-tab="whatsapp"><i class="icon icon-whatsapp"></i> WhatsApp</button>
            <button type="button" class="tab-link" data-tab="sms"><i class="icon icon-cash-stack"></i> SMS</button>
        </nav>

        <form action="<?= base_url($base . '/canales') ?>" method="post" id="chForm">
            <?= csrf_field() ?>

            <section class="tab-panel" data-panel="smtp">
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group md:col-span-2"><label class="form-label">Host SMTP</label><input type="text" name="smtp_host" class="form-input" value="<?= $v('smtp_host') ?>" placeholder="smtp.gmail.com"></div>
                    <div class="form-group"><label class="form-label">Puerto</label><input type="number" name="smtp_port" class="form-input" value="<?= $v('smtp_port') ?>" placeholder="587"></div>
                </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group"><label class="form-label">Usuario</label><input type="text" name="smtp_user" class="form-input" value="<?= $v('smtp_user') ?>" autocomplete="off"></div>
                    <div class="form-group"><label class="form-label">Contraseña</label><input type="password" name="smtp_pass" class="form-input" autocomplete="new-password" placeholder="<?= !empty($secretSet['smtp_pass']) ? '•••••••• (configurada)' : '' ?>"></div>
                    <div class="form-group"><label class="form-label">Seguridad</label>
                        <select name="smtp_security" class="form-select">
                            <?php foreach (['starttls' => 'STARTTLS', 'ssl' => 'SSL', 'none' => 'Ninguna'] as $k => $lbl): ?><option value="<?= $k ?>" <?= ($config['smtp_security'] ?? 'starttls') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group"><label class="form-label">Nombre remitente</label><input type="text" name="smtp_sender_name" class="form-input" value="<?= $v('smtp_sender_name') ?>"></div>
                    <div class="form-group"><label class="form-label">Correo remitente</label><input type="email" name="smtp_sender_email" class="form-input" value="<?= $v('smtp_sender_email') ?>"></div>
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <button type="button" class="btn btn-outline js-test" data-service="smtp"><i class="icon icon-plug"></i> Probar conexión</button>
                    <span class="js-test-result text-sm" data-for="smtp"></span>
                </div>
            </section>

            <section class="tab-panel" data-panel="whatsapp" hidden>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group"><label class="form-label">Proveedor</label>
                        <select name="wa_provider" class="form-select">
                            <?php foreach (['meta' => 'Meta Cloud API', 'twilio' => 'Twilio', 'other' => 'Otro'] as $k => $lbl): ?><option value="<?= $k ?>" <?= ($config['wa_provider'] ?? 'meta') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Phone Number ID</label><input type="text" name="wa_phone_id" class="form-input" value="<?= $v('wa_phone_id') ?>"></div>
                    <div class="form-group"><label class="form-label">Endpoint (opcional)</label><input type="text" name="wa_endpoint" class="form-input" value="<?= $v('wa_endpoint') ?>" placeholder="https://graph.facebook.com/v21.0"></div>
                </div>
                <div class="form-group"><label class="form-label">Token</label><input type="password" name="wa_token" class="form-input" autocomplete="new-password" placeholder="<?= !empty($secretSet['wa_token']) ? '•••••••• (configurado)' : 'EAAG...' ?>"></div>
                <div class="mt-2 flex items-center gap-3">
                    <button type="button" class="btn btn-outline js-test" data-service="whatsapp"><i class="icon icon-plug"></i> Probar conexión</button>
                    <span class="js-test-result text-sm" data-for="whatsapp"></span>
                </div>
            </section>

            <section class="tab-panel" data-panel="sms" hidden>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group"><label class="form-label">Proveedor</label>
                        <select name="sms_provider" class="form-select">
                            <?php foreach (['twilio' => 'Twilio', 'infobip' => 'Infobip', 'other' => 'Otro'] as $k => $lbl): ?><option value="<?= $k ?>" <?= ($config['sms_provider'] ?? 'twilio') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Account SID</label><input type="text" name="sms_account_sid" class="form-input" value="<?= $v('sms_account_sid') ?>"></div>
                    <div class="form-group"><label class="form-label">Remitente (Sender ID)</label><input type="text" name="sms_sender_id" class="form-input" value="<?= $v('sms_sender_id') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">URL base / Endpoint (solo Infobip)</label><input type="text" name="sms_endpoint" class="form-input" value="<?= $v('sms_endpoint') ?>" placeholder="https://xxxxx.api.infobip.com">
                    <small class="form-hint">Twilio no la usa (host fijo). En Infobip pon tu <em>Base URL</em>; la <strong>API Key</strong> va en «Auth Token».</small></div>
                <div class="form-group"><label class="form-label">Auth Token / API Key</label><input type="password" name="sms_auth_token" class="form-input" autocomplete="new-password" placeholder="<?= !empty($secretSet['sms_auth_token']) ? '•••••••• (configurado)' : '' ?>"></div>
                <div class="mt-2 flex items-center gap-3">
                    <button type="button" class="btn btn-outline js-test" data-service="sms"><i class="icon icon-plug"></i> Probar conexión</button>
                    <span class="js-test-result text-sm" data-for="sms"></span>
                </div>
            </section>

            <div class="mt-6 flex gap-2 border-t border-line pt-4">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
                <a href="<?= base_url($base) ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Volver</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const links = document.querySelectorAll('.tab-link'), panels = document.querySelectorAll('.tab-panel');
        links.forEach((l) => l.addEventListener('click', () => {
            links.forEach((x) => x.classList.remove('active'));
            l.classList.add('active');
            panels.forEach((p) => { p.hidden = p.dataset.panel !== l.dataset.tab; });
        }));
        const CSRF = <?= json_encode(csrf_token()) ?>;
        let hash = <?= json_encode(csrf_hash()) ?>;
        document.querySelectorAll('.js-test').forEach((btn) => btn.addEventListener('click', async () => {
            const svc = btn.dataset.service, out = document.querySelector('.js-test-result[data-for="' + svc + '"]');
            out.textContent = 'Probando…'; out.className = 'js-test-result text-sm text-muted'; btn.disabled = true;
            try {
                const body = new URLSearchParams(); body.set(CSRF, hash);
                const res = await fetch(<?= json_encode(base_url($base . '/canales/test')) ?> + '/' + svc, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body });
                const data = await res.json();
                if (data.csrf) hash = data.csrf;
                out.textContent = data.message || (data.ok ? 'OK' : 'Error');
                out.className = 'js-test-result text-sm ' + (data.ok ? 'text-success' : 'text-danger');
            } catch (e) { out.textContent = 'Error de red.'; out.className = 'js-test-result text-sm text-danger'; }
            finally { btn.disabled = false; }
        }));
    })();
</script>
<?= $this->endSection() ?>
