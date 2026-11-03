<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Ficha de la propia fila de `companies`. La usan DOS pantallas con distinto
 * alcance y la misma forma: «Mi empresa» (`/mi-empresa`) y «Mi grupo»
 * (`/tenant/datos`), que edita la fila-tenant. De ahí `$base`: el prefijo de
 * URL de quien la esté usando, que gobierna las pestañas y el destino del
 * formulario.
 *
 * @var array  $company
 * @var string $base     prefijo de URL («mi-empresa» | «tenant»)
 * @var string $heading  rótulo de la tarjeta
 * @var string $tab      pestaña activa
 */
$base    = $base ?? 'mi-empresa';
$v = static fn (string $k, $def = '') => old($k, $company[$k] ?? $def); ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-buildings"></i>
        <h5 class="font-semibold"><?= esc($heading ?? 'Mi empresa') ?></h5>
    </div>
    <div class="card-body">
        <!-- Pestañas -->
        <div class="mb-4 flex flex-wrap gap-1 border-b border-line">
            <a href="<?= base_url($base) ?>" class="border-b-2 px-4 py-2 text-sm <?= $tab === 'datos' ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">Datos</a>
            <a href="<?= base_url($base . '/canales') ?>" class="border-b-2 px-4 py-2 text-sm <?= $tab === 'canales' ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted hover:text-ink' ?>">Canales de comunicación</a>
        </div>

        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger"><ul class="list-inside list-disc"><?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form action="<?= base_url($base) ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- 1. Identificación -->
            <fieldset class="form-section">
                <legend class="form-section-title">Identificación</legend>
                <p class="form-section-hint">Cómo se llama la empresa y con qué se identifica ante terceros.</p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group md:col-span-2">
                        <label for="name" class="form-label">Razón social / Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" value="<?= esc($v('name'), 'attr') ?>" required maxlength="150">
                    </div>
                    <div class="form-group">
                        <label for="nit" class="form-label">NIT</label>
                        <input type="text" id="nit" name="nit" class="form-input" value="<?= esc($v('nit'), 'attr') ?>" maxlength="40">
                    </div>
                </div>
                <div class="form-group mb-2">
                    <label for="description" class="form-label">Descripción</label>
                    <textarea id="description" name="description" class="form-textarea" rows="2"><?= esc($v('description')) ?></textarea>
                </div>
            </fieldset>

            <!-- 2. Contacto -->
            <fieldset class="form-section">
                <legend class="form-section-title">Contacto y ubicación</legend>
                <p class="form-section-hint">Datos por los que pueden comunicarse con la empresa.</p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="website" class="form-label">Sitio web</label>
                        <input type="text" id="website" name="website" class="form-input" value="<?= esc($v('website'), 'attr') ?>" maxlength="255" placeholder="https://…">
                    </div>
                    <div class="form-group">
                        <label for="address" class="form-label">Dirección</label>
                        <input type="text" id="address" name="address" class="form-input" value="<?= esc($v('address'), 'attr') ?>" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="city" class="form-label">Ciudad</label>
                        <input type="text" id="city" name="city" class="form-input" value="<?= esc($v('city'), 'attr') ?>" maxlength="100">
                        <small class="form-hint">Aparece en el pie de página de tus documentos.</small>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <div class="form-group mb-2">
                        <label for="contact_email" class="form-label">Correo de contacto</label>
                        <input type="email" id="contact_email" name="contact_email" class="form-input" value="<?= esc($v('contact_email'), 'attr') ?>" maxlength="100">
                    </div>
                    <div class="form-group mb-2">
                        <label for="contact_phone" class="form-label">Teléfono</label>
                        <input type="text" id="contact_phone" name="contact_phone" class="form-input" value="<?= esc($v('contact_phone'), 'attr') ?>" maxlength="30">
                    </div>
                    <div class="form-group mb-2">
                        <label for="whatsapp" class="form-label">WhatsApp</label>
                        <input type="text" id="whatsapp" name="whatsapp" class="form-input" value="<?= esc($v('whatsapp'), 'attr') ?>" maxlength="40" placeholder="573001234567">
                    </div>
                </div>
            </fieldset>

            <!-- 3. Marca -->
            <fieldset class="form-section">
                <legend class="form-section-title">Marca</legend>
                <p class="form-section-hint">Imágenes y color con los que aparece la empresa en documentos, tarjetas y fichas.</p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <div class="form-group">
                        <label for="logo" class="form-label">Logo</label>
                        <?php if (!empty($company['logo'])): ?>
                            <div class="mb-2"><img src="<?= media_url($company['logo']) ?>" alt="Logo" class="max-h-16 rounded bg-canvas p-1"></div>
                        <?php endif; ?>
                        <input type="file" class="form-input" id="logo" name="logo" data-preview
                            accept="<?= esc(\App\Libraries\UploadService::IMAGE_TYPES, 'attr') ?>">
                        <small class="form-hint">Cuadrado, PNG o WebP con fondo transparente. Máx. 3 MB.</small>
                    </div>
                    <div class="form-group">
                        <label for="banner" class="form-label">Banner de portada</label>
                        <?php if (!empty($company['banner'])): ?>
                            <div class="mb-2"><img src="<?= media_url($company['banner']) ?>" alt="Banner" class="aspect-[8/3] w-full max-w-sm rounded border border-line object-cover"></div>
                        <?php endif; ?>
                        <input type="file" class="form-input" id="banner" name="banner" data-preview
                            accept="<?= esc(\App\Libraries\UploadService::IMAGE_TYPES, 'attr') ?>">
                        <small class="form-hint"><strong>800 × 300 px</strong> recomendado. Se recorta centrado si la proporción no coincide. Máx. 3 MB.</small>
                    </div>
                </div>

                <div class="form-group mb-2 md:w-1/4">
                    <label for="brand_color" class="form-label">Color de marca</label>
                    <input type="color" id="brand_color" name="brand_color" class="form-input h-10 p-1" value="<?= esc($v('brand_color', '#2563eb'), 'attr') ?>">
                </div>
            </fieldset>

            <!-- 4. Redes sociales -->
            <?php
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
                <p class="form-section-hint">Opcionales. Se publican en la tarjeta digital de tus vendedores. Puedes pegar la URL completa o solo el usuario.</p>

                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <?php foreach ($networks as $key => [$label, $ph]): ?>
                        <div class="form-group">
                            <label for="<?= $key ?>" class="form-label"><?= $label ?></label>
                            <input type="text" id="<?= $key ?>" name="<?= $key ?>" class="form-input" maxlength="255"
                                value="<?= esc($v($key), 'attr') ?>" placeholder="<?= esc($ph, 'attr') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
                <a href="<?= base_url('dashboard') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Volver</a>
            </div>
            <p class="mt-3 text-xs text-muted">El plan, el dueño y el estado de la empresa los gestiona el administrador de la plataforma.</p>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
