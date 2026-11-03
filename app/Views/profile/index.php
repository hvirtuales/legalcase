<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Profile Card -->
    <div class="lg:col-span-1">
        <div class="card h-full">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="avatar avatar-lg mx-auto">
                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                    </div>
                </div>
                <h5 class="mb-1 text-lg font-semibold"><?= esc($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                <p class="mb-3 text-muted">@<?= esc($user['username']) ?></p>

                <div class="mb-3 flex flex-wrap justify-center gap-1">
                    <?php if (!empty($user['roles']) && is_array($user['roles'])): ?>
                        <?php foreach ((array) $user['roles'] as $role): ?>
                            <span class="badge badge-secondary"><?= esc(is_array($role) ? $role['name'] : $role) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge badge-light"><?= lang('App.noRole') ?></span>
                    <?php endif; ?>
                </div>

                <div class="mt-4 list-group text-left">
                    <div class="list-group-item">
                        <span><i class="icon icon-shield-lock"></i> <?= lang('App.auth2FA') ?></span>
                        <?php if ($is2FAEnabled): ?>
                            <span class="badge badge-success"><?= lang('App.active') ?></span>
                        <?php else: ?>
                            <span class="badge badge-warning"><?= lang('App.inactive') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="list-group-item block">
                        <i class="icon icon-clock"></i> <?= lang('App.lastAccess') ?><br>
                        <small class="ml-6 text-muted">
                            <?= $user['last_login_at'] ? date('d/m/Y H:i', strtotime($user['last_login_at'])) : lang('App.never') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <i class="icon icon-person-gear"></i>
                <h5 class="font-semibold"><?= lang('App.editProfile') ?></h5>
            </div>
            <div class="card-body">
                <?php if (session()->getFlashdata('errors')): ?>
                    <div class="alert alert-danger">
                        <ul class="list-inside list-disc">
                            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?= base_url('profile') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                        <div class="form-group">
                            <label for="first_name" class="form-label"><?= lang('App.firstName') ?> *</label>
                            <input type="text" class="form-input" id="first_name" name="first_name"
                                value="<?= old('first_name', $user['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name" class="form-label"><?= lang('App.lastName') ?> *</label>
                            <input type="text" class="form-input" id="last_name" name="last_name"
                                value="<?= old('last_name', $user['last_name']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label"><?= lang('App.email') ?> *</label>
                        <input type="email" class="form-input" id="email" name="email"
                            value="<?= old('email', $user['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Teléfono / WhatsApp</label>
                        <input type="tel" class="form-input" id="phone" name="phone" data-phone
                            value="<?= old('phone', $user['phone'] ?? '') ?>" placeholder="300 123 4567">
                        <small class="form-hint">Con código de país, sin el signo +. Se usa para recuperación por WhatsApp.</small>
                    </div>

                    <div class="form-group">
                        <label for="preferred_language" class="form-label"><?= lang('App.preferredLanguage') ?></label>
                        <select class="form-select" id="preferred_language" name="preferred_language">
                            <option value="es" <?= ($user['preferred_language'] ?? 'es') === 'es' ? 'selected' : '' ?>>Español</option>
                            <option value="en" <?= ($user['preferred_language'] ?? 'es') === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>

                    <hr class="my-6 border-line">

                    <h6 class="mb-3 flex items-center gap-2 font-semibold">
                        <i class="icon icon-palette"></i>
                        <?= lang('App.themeCustomization') ?>
                    </h6>
                    <p class="mb-3 text-sm text-muted"><?= lang('App.themeCustomizationDesc') ?></p>

                    <!-- Color Palette -->
                    <div class="form-group mb-6">
                        <label class="form-label font-semibold"><?= lang('App.colorPalette') ?></label>
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
                            $currentThemeColor = $user['theme_color'] ?? '';
                            foreach ($predefinedColors as $color):
                                ?>
                                <div class="theme-color-circle <?= $color['value'] === $currentThemeColor ? 'active' : '' ?>"
                                    data-color="<?= $color['value'] ?>" style="background-color: <?= $color['value'] ?>;"
                                    title="<?= $color['name'] ?>">
                                </div>
                            <?php endforeach; ?>

                            <label for="profile_custom_color" class="theme-color-circle custom-color-picker" title="Color Personalizado">
                                <i class="icon icon-eyedropper"></i>
                                <input type="color" id="profile_custom_color" class="hidden"
                                    value="<?= $currentThemeColor ?: '#e10e30' ?>">
                            </label>
                        </div>
                        <small class="form-hint"><?= lang('App.colorPaletteDesc') ?></small>
                    </div>

                    <input type="hidden" id="theme_color" name="theme_color" value="<?= $currentThemeColor ?>">

                    <button type="button" id="reset_profile_theme" class="btn btn-outline btn-sm mb-3">
                        <i class="icon icon-arrow-counterclockwise"></i><?= lang('App.useGlobalTheme') ?>
                    </button>

                    <hr class="my-6 border-line">

                    <h6 class="mb-3 font-semibold"><?= lang('App.changePasswordOptional') ?></h6>

                    <div class="form-group">
                        <label for="current_password" class="form-label"><?= lang('App.currentPassword') ?></label>
                        <input type="password" class="form-input" id="current_password" name="current_password">
                        <div class="form-hint"><?= lang('App.currentPasswordHelp') ?></div>
                    </div>

                    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                        <div class="form-group">
                            <label for="password" class="form-label"><?= lang('App.newPassword') ?></label>
                            <input type="password" class="form-input" id="password" name="password" minlength="8">
                            <div class="form-hint"><?= lang('App.passwordMinLength') ?></div>
                        </div>
                        <div class="form-group">
                            <label for="password_confirm" class="form-label"><?= lang('App.confirmNewPassword') ?></label>
                            <input type="password" class="form-input" id="password_confirm" name="password_confirm">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon icon-save"></i>
                            <?= lang('App.saveChanges') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Theme Customizer Script for Profile -->
<script>
    (function () {
        'use strict';
        const colorCircles = document.querySelectorAll('.theme-color-circle');
        const customColorInput = document.getElementById('profile_custom_color');
        const themeColorInput = document.getElementById('theme_color');
        const resetButton = document.getElementById('reset_profile_theme');

        colorCircles.forEach(circle => {
            circle.addEventListener('click', () => {
                colorCircles.forEach(c => c.classList.remove('active'));
                circle.classList.add('active');
                const color = circle.dataset.color;
                if (color) {
                    themeColorInput.value = color;
                    customColorInput.value = color;
                    applyPreview(color);
                }
            });
        });

        if (customColorInput) {
            customColorInput.addEventListener('input', (e) => {
                const color = e.target.value;
                themeColorInput.value = color;
                colorCircles.forEach(c => c.classList.remove('active'));
                applyPreview(color);
            });
        }

        if (resetButton) {
            resetButton.addEventListener('click', (e) => {
                e.preventDefault();
                themeColorInput.value = '';
                customColorInput.value = '#e10e30';
                colorCircles.forEach(c => c.classList.remove('active'));
                resetToDefault();
            });
        }

        function applyPreview(primaryColor) {
            if (window.ThemeCustomizer) window.ThemeCustomizer.applyPreview(primaryColor);
        }
        function resetToDefault() {
            if (window.ThemeCustomizer) window.ThemeCustomizer.resetToDefault();
        }
    })();
</script>

<?= $this->endSection() ?>
