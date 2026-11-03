<?php
/**
 * @var array $company Valores actuales (vacío en creación)
 * @var array $tenants Grupos disponibles (solo en el panel global de Empresas)
 */
$company ??= [];
$tenants ??= [];
$v = static fn (string $k, $def = '') => old($k, $company[$k] ?? $def);
?>
<?php if ($tenants): ?>
    <fieldset class="form-section">
        <legend class="form-section-title"><?= lang('Tenant.groupSection') ?></legend>
        <p class="form-section-hint"><?= lang('Tenant.groupSectionHint') ?></p>
        <div class="form-group md:w-1/2">
            <label for="parent_id" class="form-label"><?= lang('Tenant.belongsTo') ?></label>
            <select class="form-select" id="parent_id" name="parent_id">
                <option value=""><?= lang('Tenant.noGroup') ?></option>
                <?php foreach ($tenants as $t): ?>
                    <option value="<?= esc($t['id'], 'attr') ?>" <?= (string) $v('parent_id') === (string) $t['id'] ? 'selected' : '' ?>>
                        <?= esc($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-hint"><?= lang('Tenant.belongsToHint') ?></small>
        </div>
    </fieldset>
<?php endif; ?>
<div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
    <div class="form-group md:col-span-2">
        <label for="name" class="form-label">Nombre de la Empresa *</label>
        <input type="text" class="form-input" id="name" name="name" value="<?= esc($v('name'), 'attr') ?>" required maxlength="150">
    </div>
    <div class="form-group">
        <label for="nit" class="form-label">NIT</label>
        <input type="text" class="form-input" id="nit" name="nit" value="<?= esc($v('nit'), 'attr') ?>" maxlength="40">
    </div>
</div>

<div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
    <div class="form-group">
        <label for="slug" class="form-label">Slug</label>
        <input type="text" class="form-input" id="slug" name="slug" value="<?= esc($v('slug'), 'attr') ?>" maxlength="160" placeholder="se genera del nombre si se deja vacío">
    </div>
    <div class="form-group">
        <label for="website" class="form-label">Sitio web</label>
        <input type="text" class="form-input" id="website" name="website" value="<?= esc($v('website'), 'attr') ?>" maxlength="255" placeholder="https://...">
    </div>
</div>

<div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
    <div class="form-group">
        <label for="contact_email" class="form-label">Email de contacto</label>
        <input type="email" class="form-input" id="contact_email" name="contact_email" value="<?= esc($v('contact_email'), 'attr') ?>" maxlength="100">
    </div>
    <div class="form-group">
        <label for="contact_phone" class="form-label">Teléfono</label>
        <input type="tel" class="form-input" id="contact_phone" name="contact_phone" data-phone value="<?= esc($v('contact_phone'), 'attr') ?>" maxlength="30" placeholder="300 123 4567">
    </div>
    <div class="form-group">
        <label for="whatsapp" class="form-label">WhatsApp</label>
        <input type="tel" class="form-input" id="whatsapp" name="whatsapp" data-phone value="<?= esc($v('whatsapp'), 'attr') ?>" maxlength="40" placeholder="300 123 4567">
    </div>
</div>

<div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
    <div class="form-group">
        <label for="brand_color" class="form-label">Color de marca</label>
        <input type="color" class="h-10 w-16 cursor-pointer rounded-sm border border-line" id="brand_color" name="brand_color" value="<?= esc($v('brand_color', '#e10e30'), 'attr') ?>">
    </div>
    <div class="form-group">
        <label for="logo" class="form-label">Logo</label>
        <?php if (!empty($company['logo'])): ?>
            <div class="mb-2"><img src="<?= media_url($company['logo']) ?>" alt="Logo" class="max-h-16 rounded bg-canvas p-1"></div>
        <?php endif; ?>
        <input type="file" class="form-input" id="logo" name="logo" data-preview
            accept="<?= esc(\App\Libraries\UploadService::IMAGE_TYPES, 'attr') ?>">
        <small class="form-hint">Cuadrado. Máx. 3 MB.</small>
    </div>
    <div class="form-group">
        <label for="banner" class="form-label">Banner de portada</label>
        <?php if (!empty($company['banner'])): ?>
            <div class="mb-2"><img src="<?= media_url($company['banner']) ?>" alt="Banner" class="aspect-[8/3] w-full max-w-xs rounded border border-line object-cover"></div>
        <?php endif; ?>
        <input type="file" class="form-input" id="banner" name="banner" data-preview
            accept="<?= esc(\App\Libraries\UploadService::IMAGE_TYPES, 'attr') ?>">
        <small class="form-hint"><strong>800 × 300 px</strong>. Máx. 3 MB.</small>
    </div>
</div>

<div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
    <div class="form-group md:col-span-2">
        <label for="address" class="form-label">Dirección</label>
        <input type="text" class="form-input" id="address" name="address" value="<?= esc($v('address'), 'attr') ?>" maxlength="255">
    </div>
    <div class="form-group">
        <label for="city" class="form-label">Ciudad</label>
        <input type="text" class="form-input" id="city" name="city" value="<?= esc($v('city'), 'attr') ?>" maxlength="100">
    </div>
</div>

<?php
/*
 * Sector y ubicación.
 *
 * El **sector** es la clasificación con la que se agrupan los directorios y los
 * mapas. Se elige de un catálogo de plataforma (`/sectors`, Super Admin): aquí
 * solo se clasifica, no se inventa vocabulario nuevo.
 *
 * Las **coordenadas** son lo que pone a la empresa en el mapa. Se escriben a
 * mano y no se geocodifican: geocodificar exigiría llamar a un servicio externo
 * en cada guardado, y dejaría el formulario a merced de que ese servicio
 * responda. El campo acepta también un pegado de «lat, lng» —que es como lo
 * copia Google Maps— y el JS de abajo lo reparte en los dos campos.
 */
$sectores = (new \App\Models\SectorModel())->active();
$actual   = (int) ($v('sector_id') ?: 0);
?>
<div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
    <div class="form-group">
        <label for="sector_id" class="form-label"><?= lang('Sector.fSector') ?></label>
        <select class="form-select" id="sector_id" name="sector_id">
            <option value=""><?= lang('Sector.noSector') ?></option>
            <?php foreach ($sectores as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= $actual === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= esc($s['name']) ?>
                </option>
            <?php endforeach; ?>
            <?php
            // Un sector desactivado DESPUÉS de clasificar sigue siendo el de esta
            // empresa: si no se ofreciera, guardar el formulario la desclasificaría
            // sin que nadie lo pidiera.
            if ($actual && !in_array($actual, array_map(static fn ($s) => (int) $s['id'], $sectores), true)) {
                $suyo = (new \App\Models\SectorModel())->withDeleted()->find($actual);
                if ($suyo) {
                    echo '<option value="' . $actual . '" selected>' . esc($suyo['name']) . '</option>';
                }
            }
            ?>
        </select>
        <small class="form-hint"><?= lang('Sector.fSectorHint') ?></small>
    </div>
    <div class="form-group">
        <label for="latitude" class="form-label">Latitud</label>
        <input type="text" class="form-input" id="latitude" name="latitude" inputmode="decimal"
            placeholder="4.6533" value="<?= esc($v('latitude'), 'attr') ?>">
        <small class="form-hint">Puedes pegar aquí «4.6533, -74.0836» y se reparte solo.</small>
    </div>
    <div class="form-group">
        <label for="longitude" class="form-label">Longitud</label>
        <input type="text" class="form-input" id="longitude" name="longitude" inputmode="decimal"
            placeholder="-74.0836" value="<?= esc($v('longitude'), 'attr') ?>">
        <small class="form-hint">Vacías = la empresa no sale en el mapa, pero sí en el listado.</small>
    </div>
</div>

<script>
// Pegar «lat, lng» en el campo de latitud lo reparte en los dos. Es como se
// copia una ubicación de cualquier mapa, y separarlo a mano es justo el paso
// donde se cuela un error de signo.
(function () {
    var lat = document.getElementById('latitude');
    var lng = document.getElementById('longitude');
    if (!lat || !lng) { return; }
    lat.addEventListener('input', function () {
        var m = /^\s*(-?\d{1,3}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/.exec(lat.value);
        if (m) { lat.value = m[1]; lng.value = m[2]; }
    });
}());
</script>

<div class="form-group">
    <label for="description" class="form-label">Descripción</label>
    <textarea class="form-textarea" id="description" name="description" rows="2"><?= esc($v('description')) ?></textarea>
</div>

<?php
// Redes sociales: se muestran en la tarjeta digital y en las fichas públicas.
// Acepta la URL completa o solo el usuario (@mimarca).
$networks = [
    'facebook'  => ['Facebook', 'https://facebook.com/mimarca'],
    'instagram' => ['Instagram', '@mimarca'],
    'tiktok'    => ['TikTok', '@mimarca'],
    'youtube'   => ['YouTube', '@mimarca'],
    'linkedin'  => ['LinkedIn', 'https://linkedin.com/company/mimarca'],
];
?>
<fieldset class="form-section">
    <legend class="form-section-title">Redes sociales</legend>
    <p class="form-section-hint">Opcionales. Se publican en la tarjeta digital. Puedes pegar la URL completa o solo el usuario.</p>
    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
        <?php foreach ($networks as $key => [$label, $ph]): ?>
            <div class="form-group">
                <label for="<?= $key ?>" class="form-label"><?= $label ?></label>
                <input type="text" class="form-input" id="<?= $key ?>" name="<?= $key ?>" maxlength="255"
                    value="<?= esc($v($key), 'attr') ?>" placeholder="<?= esc($ph, 'attr') ?>">
            </div>
        <?php endforeach; ?>
    </div>
</fieldset>

<label class="form-check mb-2" for="is_active">
    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $v('is_active', 1) ? 'checked' : '' ?>>
    <span class="form-check-label">Empresa activa</span>
</label>
