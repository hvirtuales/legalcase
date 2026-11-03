<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php
$endpoints = [
    ['GET', 'ping', '—', 'Comprobación de vida (requiere token válido).'],
    ['GET', 'me', '—', 'Identidad, empresa, roles y permisos del token.'],
    ['GET', 'company', '—', 'Empresa del token, con su plan y suscripción.'],
    ['GET', 'users', 'users.view', 'Usuarios miembros de la empresa del token.'],
    ['GET', 'users/{id}', 'users.view', 'Detalle (sólo si es miembro de la empresa).'],
    ['POST', 'users', 'users.create', 'Crea un usuario y lo vincula a la empresa.'],
    ['PUT', 'users/{id}', 'users.edit', 'Actualiza (sólo miembros de la empresa).'],
    ['DELETE', 'users/{id}', 'users.delete', 'Desvincula al usuario de la empresa (no lo borra global).'],
    ['GET', 'companies', 'companies.view', 'Lista de empresas.'],
    ['POST', 'companies', 'companies.create', 'Crea una empresa.'],
    ['PUT', 'companies/{id}', 'companies.edit', 'Actualiza una empresa.'],
    ['DELETE', 'companies/{id}', 'companies.delete', 'Elimina una empresa.'],
    ['GET', 'plans', 'plans.view', 'Lista de planes.'],
    ['POST', 'plans', 'plans.create', 'Crea un plan.'],
    ['PUT', 'plans/{id}', 'plans.edit', 'Actualiza un plan.'],
    ['DELETE', 'plans/{id}', 'plans.delete', 'Elimina un plan.'],
];
$badge = ['GET' => 'badge-info', 'POST' => 'badge-success', 'PUT' => 'badge-warning', 'PATCH' => 'badge-warning', 'DELETE' => 'badge-danger'];
?>

<!-- Autenticación -->
<div class="card mb-6">
    <div class="card-header"><i class="icon icon-shield-lock"></i><h6 class="font-semibold">Autenticación</h6></div>
    <div class="card-body">
        <p class="mb-3 text-sm text-muted">
            La API usa <strong>JWT</strong>. Genera un token en
            <a href="<?= base_url('apitokens') ?>" class="text-primary hover:underline">Tokens de API</a>
            y envíalo en cada petición en la cabecera:
        </p>
        <pre class="overflow-x-auto rounded-sm bg-canvas p-3 text-xs"><code>Authorization: Bearer &lt;tu_token&gt;
Content-Type: application/json</code></pre>
        <p class="mt-3 text-sm text-muted">
            El token concede los <strong>permisos de los roles</strong> con los que se creó y está
            <strong>acotado a una empresa</strong>: los endpoints de <code>companies</code> sólo operan sobre esa
            empresa, y si su suscripción está <strong>vencida</strong> el token se rechaza (403). Base de la API:
        </p>
        <pre class="overflow-x-auto rounded-sm bg-canvas p-3 text-xs"><code><?= esc($baseUrl) ?></code></pre>
        <p class="mt-3 text-sm text-muted">Ejemplo:</p>
        <pre class="overflow-x-auto rounded-sm bg-canvas p-3 text-xs"><code>curl -H "Authorization: Bearer &lt;token&gt;" <?= esc($baseUrl) ?>/me</code></pre>
    </div>
</div>

<!-- Endpoints -->
<div class="card mb-6">
    <div class="card-header"><i class="icon icon-list-columns-reverse"></i><h6 class="font-semibold">Endpoints</h6></div>
    <div class="card-body">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Método</th><th>Ruta</th><th>Permiso</th><th>Descripción</th></tr></thead>
                <tbody>
                    <?php foreach ($endpoints as [$method, $path, $perm, $desc]): ?>
                        <tr>
                            <td><span class="badge <?= $badge[$method] ?? 'badge-secondary' ?>"><?= $method ?></span></td>
                            <td class="font-mono text-xs">/api/v1/<?= esc($path) ?></td>
                            <td class="text-muted"><?= $perm === '—' ? '—' : '<code>' . esc($perm) . '</code>' ?></td>
                            <td class="text-muted"><?= esc($desc) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Playground -->
<div class="card">
    <div class="card-header"><i class="icon icon-play-circle"></i><h6 class="font-semibold">Ambiente de pruebas</h6></div>
    <div class="card-body">
        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
            <div class="form-group">
                <label class="form-label">Método</label>
                <select id="pgMethod" class="form-select">
                    <option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
                </select>
            </div>
            <div class="form-group md:col-span-3">
                <label class="form-label">Endpoint</label>
                <div class="input-group">
                    <span class="input-group-text text-xs"><?= esc($baseUrl) ?>/</span>
                    <input type="text" id="pgPath" class="form-input" value="me" placeholder="users, companies/1, ...">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Token (JWT)</label>
            <input type="text" id="pgToken" class="form-input font-mono text-xs" placeholder="Pega aquí el token generado">
            <small class="form-hint">Por seguridad el token no se guarda; pégalo desde donde lo copiaste al crearlo.</small>
        </div>

        <div class="form-group" id="pgBodyWrap">
            <label class="form-label">Body (JSON)</label>
            <textarea id="pgBody" rows="4" class="form-textarea font-mono text-xs" placeholder='{"name": "Ejemplo"}'></textarea>
        </div>

        <button type="button" class="btn btn-primary" onclick="pgSend()"><i class="icon icon-send"></i> Enviar</button>

        <div class="mt-4 hidden" id="pgResultWrap">
            <div class="mb-2 flex items-center gap-2">
                <span class="form-label mb-0">Respuesta</span>
                <span id="pgStatus" class="badge"></span>
            </div>
            <pre class="max-h-96 overflow-auto rounded-sm bg-canvas p-3 text-xs"><code id="pgResult"></code></pre>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const PG_BASE = <?= json_encode($baseUrl) ?>;

    async function pgSend() {
        const method = document.getElementById('pgMethod').value;
        const path = document.getElementById('pgPath').value.replace(/^\/+/, '');
        const token = document.getElementById('pgToken').value.trim();
        const body = document.getElementById('pgBody').value.trim();
        const wrap = document.getElementById('pgResultWrap');
        const statusEl = document.getElementById('pgStatus');
        const resultEl = document.getElementById('pgResult');

        wrap.classList.remove('hidden');
        statusEl.textContent = '...'; statusEl.className = 'badge badge-secondary';
        resultEl.textContent = 'Enviando…';

        const opts = {
            method,
            headers: { 'Accept': 'application/json' }
        };
        if (token) opts.headers['Authorization'] = 'Bearer ' + token;
        if (!['GET', 'DELETE'].includes(method) && body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = body;
        }

        try {
            const res = await fetch(PG_BASE + '/' + path, opts);
            const text = await res.text();
            let pretty = text;
            try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
            statusEl.textContent = res.status + ' ' + res.statusText;
            statusEl.className = 'badge ' + (res.ok ? 'badge-success' : 'badge-danger');
            resultEl.textContent = pretty;
        } catch (e) {
            statusEl.textContent = 'Error'; statusEl.className = 'badge badge-danger';
            resultEl.textContent = 'No se pudo conectar: ' + e.message;
        }
    }

    // Oculta el body en métodos sin cuerpo
    document.getElementById('pgMethod').addEventListener('change', function () {
        document.getElementById('pgBodyWrap').style.display = ['GET', 'DELETE'].includes(this.value) ? 'none' : '';
    });
</script>
<?= $this->endSection() ?>
