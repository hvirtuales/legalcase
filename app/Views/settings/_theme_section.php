<!-- Theme Customization Section -->
<div class="card mb-6">
    <div class="card-header">
        <i class="icon icon-palette"></i>
        <h5 class="font-semibold">Personalización de Tema</h5>
    </div>
    <div class="card-body">
        <p class="mb-3 text-sm text-muted">
            <i class="icon icon-info-circle"></i>
            <strong>Configuración Global:</strong> Estos colores se aplicarán a todos los usuarios que no tengan un tema
            personalizado en su perfil.
        </p>

        <!-- Color Palette -->
        <div class="form-group mb-6">
            <label class="form-label font-semibold">Paleta de Colores</label>
            <div class="mb-3 flex flex-wrap gap-2">
                <?php
                $predefinedColors = [
                    ['name' => 'Rojo Corporativo', 'value' => '#e10e30'],
                    ['name' => 'Azul Claro', 'value' => '#60A5FA'],
                    ['name' => 'Gris', 'value' => '#6B7280'],
                    ['name' => 'Cyan', 'value' => '#06B6D4'],
                    ['name' => 'Teal', 'value' => '#14B8A6'],
                    ['name' => 'Azul', 'value' => '#3B82F6'],
                    ['name' => 'Rosa Pálido', 'value' => '#D8B4A0'],
                    ['name' => 'Rosa', 'value' => '#EC4899'],
                    ['name' => 'Amarillo', 'value' => '#EAB308'],
                    ['name' => 'Naranja', 'value' => '#F97316'],
                    ['name' => 'Negro', 'value' => '#1F2937'],
                    ['name' => 'Gris Oscuro', 'value' => '#374151'],
                    ['name' => 'Azul Marino', 'value' => '#1E3A8A'],
                    ['name' => 'Morado', 'value' => '#7C3AED'],
                    ['name' => 'Rojo', 'value' => '#DC2626'],
                    ['name' => 'Verde', 'value' => '#059669'],
                ];
                $currentThemeColor = $settings['theme_color_primary'] ?? '#e10e30';
                foreach ($predefinedColors as $color):
                    ?>
                    <div class="theme-color-circle <?= $color['value'] === $currentThemeColor ? 'active' : '' ?>"
                        data-color="<?= $color['value'] ?>" style="background-color: <?= $color['value'] ?>;"
                        title="<?= $color['name'] ?>">
                    </div>
                <?php endforeach; ?>

                <!-- Custom Color Picker Circle -->
                <label for="theme_custom_color" class="theme-color-circle custom-color-picker" title="Color Personalizado">
                    <i class="icon icon-eyedropper"></i>
                    <input type="color" id="theme_custom_color" class="hidden" value="<?= $currentThemeColor ?>">
                </label>
            </div>
            <small class="form-hint">Selecciona un color de la paleta o usa el selector personalizado</small>
        </div>

        <!-- Hidden input for form submission -->
        <input type="hidden" id="theme_color_primary" name="theme_color_primary" value="<?= $currentThemeColor ?>">

        <!-- Preview Section -->
        <div class="alert alert-info">
            <i class="icon icon-info-circle"></i>
            <span><strong>Vista Previa:</strong> Los cambios se aplicarán en tiempo real. Guarda para aplicar permanentemente.</span>
        </div>

        <!-- Reset Button -->
        <button type="button" id="reset_theme" class="btn btn-outline mb-6">
            <i class="icon icon-arrow-counterclockwise"></i>Restaurar Color Por Defecto
        </button>

        <hr class="my-6 border-line">

        <!-- Login Customization Section -->
        <h6 class="mb-3 flex items-center gap-2 font-semibold">
            <i class="icon icon-image"></i><?= lang('App.loginCustomization') ?>
        </h6>

        <!-- Login Image -->
        <div class="form-group mb-6">
            <label for="login_image" class="form-label"><?= lang('App.loginImageLabel') ?></label>
            <?php if (!empty($settings['login_image'])): ?>
                <div class="mb-2">
                    <img src="<?= media_url($settings['login_image']) ?>" alt="Login Image" class="max-w-[200px] rounded-sm">
                </div>
            <?php endif; ?>
            <input type="file" class="form-input" id="login_image" name="login_image" data-preview
                accept="image/jpeg,image/jpg,image/png,image/webp">
            <small class="form-hint">Formatos: JPG, PNG, WEBP. Se redimensionará automáticamente a 400x400px</small>
        </div>

        <!-- Background Style -->
        <div class="form-group mb-6">
            <label for="login_background" class="form-label"><?= lang('App.backgroundStyleLabel') ?></label>
            <select class="form-select" id="login_background" name="login_background"
                onchange="toggleBackgroundOptions(this.value)">
                <option value="gradient" <?= ($settings['login_background'] ?? 'gradient') === 'gradient' ? 'selected' : '' ?>>Degradado</option>
                <option value="solid" <?= ($settings['login_background'] ?? '') === 'solid' ? 'selected' : '' ?>>Color Sólido</option>
                <option value="image" <?= ($settings['login_background'] ?? '') === 'image' ? 'selected' : '' ?>>Imagen de Fondo</option>
            </select>
        </div>

        <!-- Color Options (for gradient and solid) -->
        <div id="colorOptions" class="<?= in_array($settings['login_background'] ?? 'gradient', ['gradient', 'solid']) ? '' : 'hidden' ?>">
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="bg_color_primary" class="form-label">Color Primario</label>
                    <input type="color" class="h-10 w-16 cursor-pointer rounded-sm border border-line" id="bg_color_primary"
                        name="bg_color_primary" value="<?= $settings['bg_color_primary'] ?? '#e10e30' ?>">
                </div>
                <div class="form-group <?= ($settings['login_background'] ?? 'gradient') === 'gradient' ? '' : 'hidden' ?>" id="secondaryColorDiv">
                    <label for="bg_color_secondary" class="form-label">Color Secundario (para degradado)</label>
                    <input type="color" class="h-10 w-16 cursor-pointer rounded-sm border border-line" id="bg_color_secondary"
                        name="bg_color_secondary" value="<?= $settings['bg_color_secondary'] ?? '#b90b26' ?>">
                </div>
            </div>
        </div>

        <!-- Background Image Option -->
        <div id="imageOption" class="<?= ($settings['login_background'] ?? '') === 'image' ? '' : 'hidden' ?>">
            <div class="form-group">
                <label for="background_image" class="form-label">Imagen de Fondo</label>
                <?php if (!empty($settings['background_image'])): ?>
                    <div class="mb-2">
                        <img src="<?= media_url($settings['background_image']) ?>" alt="Background" class="max-w-[300px] rounded-sm">
                    </div>
                <?php endif; ?>
                <input type="file" class="form-input" id="background_image" name="background_image" data-preview
                    accept="image/jpeg,image/jpg,image/png,image/webp">
                <small class="form-hint">Formatos: JPG, PNG, WEBP. Se redimensionará automáticamente a 1920x1080px</small>
            </div>
        </div>

        <hr class="my-6 border-line">

        <!-- Favicon Section -->
        <h6 class="mb-3 flex items-center gap-2 font-semibold"><i class="icon icon-star"></i>Favicon</h6>
        <div class="form-group">
            <label for="favicon" class="form-label">Favicon (.ico)</label>
            <?php if (!empty($settings['favicon'])): ?>
                <div class="mb-2">
                    <img src="<?= media_url($settings['favicon']) ?>" alt="Favicon" class="size-8">
                </div>
            <?php endif; ?>
            <input type="file" class="form-input" id="favicon" name="favicon" accept=".ico">
            <small class="form-hint">Formato: ICO. Tamaño recomendado: 32x32px o 64x64px</small>
        </div>
    </div>
</div>

<script>
    function toggleBackgroundOptions(value) {
        const colorOptions = document.getElementById('colorOptions');
        const imageOption = document.getElementById('imageOption');
        const secondaryColorDiv = document.getElementById('secondaryColorDiv');

        if (value === 'image') {
            colorOptions.classList.add('hidden');
            imageOption.classList.remove('hidden');
        } else {
            colorOptions.classList.remove('hidden');
            imageOption.classList.add('hidden');
            secondaryColorDiv.classList.toggle('hidden', value !== 'gradient');
        }
    }
</script>

<!-- Load Theme Customizer Script -->
<script src="<?= base_url('js/theme-customizer.js') ?>?v=<?= filemtime(FCPATH . 'js/theme-customizer.js') ?>"></script>
