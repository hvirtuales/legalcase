<?= $this->extend('install/_layout') ?>

<?= $this->section('content') ?>
<?php $d = static fn ($k, $def = '') => old($k, $db[$k] ?? $def); ?>

<h2 class="mb-1 text-lg font-semibold"><i class="icon icon-hdd-network"></i> Conexión a la base de datos</h2>
<p class="mb-4 text-sm text-muted">Elige el motor y los datos de conexión. Se probará y guardará en el archivo <code>.env</code>.</p>

<form action="<?= base_url('install/database') ?>" method="post">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="driver" class="form-label">Motor de base de datos</label>
        <select class="form-select" id="driver" name="driver" onchange="toggleDriver()">
            <?php foreach ($drivers as $key => $info): ?>
                <option value="<?= $key ?>" data-file="<?= $info['file'] ? '1' : '0' ?>" data-ext="<?= esc($info['ext'], 'attr') ?>"
                    <?= $d('driver', 'MySQLi') === $key ? 'selected' : '' ?>>
                    <?= esc($info['label']) ?><?= $info['recommended'] ? ' (recomendado)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Servidor (MySQL / PostgreSQL) -->
    <div id="serverFields">
        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
            <div class="form-group md:col-span-2">
                <label for="hostname" class="form-label">Host</label>
                <input type="text" class="form-input" id="hostname" name="hostname" value="<?= esc($d('hostname', 'localhost'), 'attr') ?>">
            </div>
            <div class="form-group">
                <label for="port" class="form-label">Puerto</label>
                <input type="text" class="form-input" id="port" name="port" value="<?= esc($d('port'), 'attr') ?>" placeholder="3306">
            </div>
        </div>
        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <div class="form-group">
                <label for="username" class="form-label">Usuario</label>
                <input type="text" class="form-input" id="username" name="username" value="<?= esc($d('username', 'root'), 'attr') ?>">
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-input" id="password" name="password" autocomplete="new-password">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="database" class="form-label"><span id="dbLabel">Nombre de la base de datos</span></label>
        <input type="text" class="form-input" id="database" name="database" value="<?= esc($d('database'), 'attr') ?>"
            placeholder="cuentas_db">
        <small class="form-hint" id="dbHint">Se creará si no existe (si marcas la casilla).</small>
    </div>

    <label class="form-check mb-4" for="create_db" id="createWrap">
        <input class="form-check-input" type="checkbox" id="create_db" name="create_db" value="1" checked>
        <span class="form-check-label">Crear la base de datos si no existe</span>
    </label>

    <div class="flex items-center justify-between gap-2">
        <a href="<?= base_url('install/requirements') ?>" class="btn btn-outline"><i class="icon icon-chevron-left"></i> Atrás</a>
        <button type="submit" class="btn btn-primary"><i class="icon icon-plug"></i> Probar y continuar</button>
    </div>
</form>

<script>
    function toggleDriver() {
        var sel = document.getElementById('driver');
        var opt = sel.options[sel.selectedIndex];
        var isFile = opt.getAttribute('data-file') === '1';
        document.getElementById('serverFields').style.display = isFile ? 'none' : '';
        document.getElementById('createWrap').style.display = isFile ? 'none' : '';
        document.getElementById('dbLabel').textContent = isFile ? 'Ruta del archivo SQLite' : 'Nombre de la base de datos';
        document.getElementById('dbHint').textContent = isFile
            ? 'Ej: writable/database.sqlite (el directorio debe ser escribible).'
            : 'Se creará si no existe (si marcas la casilla).';
        document.getElementById('database').placeholder = isFile ? 'writable/database.sqlite' : 'cuentas_db';
    }
    toggleDriver();
</script>

<?= $this->endSection() ?>
