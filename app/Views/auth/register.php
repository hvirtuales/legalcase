<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-body">
        <!-- Logo -->
        <div class="mb-6 text-center">
            <?php
            $settingModel = new \App\Models\AppSettingModel();
            $loginImage = $settingModel->getSetting('login_image');
            $appName = $settingModel->getAppName();

            if ($loginImage): ?>
                <img src="<?= base_url($loginImage) ?>" alt="<?= esc($appName) ?>" class="mx-auto max-h-20">
            <?php else: ?>
                <h1 class="text-2xl font-bold text-primary"><?= esc($appName) ?></h1>
            <?php endif; ?>
        </div>

        <!-- Title -->
        <h2 class="text-center text-xl font-semibold text-ink"><?= lang('Auth.createAccount') ?></h2>
        <p class="mb-6 mt-1 text-center text-sm text-muted"><?= lang('Auth.registerSubtitle') ?></p>

        <!-- Alerts -->
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-error">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form action="<?= base_url('register') ?>" method="post">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group">
                    <label for="first_name" class="form-label"><?= lang('App.firstName') ?></label>
                    <input type="text" class="form-input" id="first_name" name="first_name"
                        placeholder="<?= lang('App.firstName') ?>" value="<?= old('first_name') ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name" class="form-label"><?= lang('App.lastName') ?></label>
                    <input type="text" class="form-input" id="last_name" name="last_name"
                        placeholder="<?= lang('App.lastName') ?>" value="<?= old('last_name') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">
                    <i class="icon icon-person"></i>
                    <?= lang('App.username') ?>
                </label>
                <input type="text" class="form-input" id="username" name="username"
                    placeholder="<?= lang('App.username') ?>" value="<?= old('username') ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="icon icon-envelope"></i>
                    <?= lang('App.email') ?>
                </label>
                <input type="email" class="form-input" id="email" name="email" placeholder="<?= lang('App.email') ?>"
                    value="<?= old('email') ?>" required>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">
                    <i class="icon icon-whatsapp"></i>
                    Teléfono / WhatsApp
                </label>
                <input type="tel" class="form-input" id="phone" name="phone" data-phone
                    value="<?= old('phone') ?>" placeholder="300 123 4567">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="icon icon-lock"></i>
                    <?= lang('App.password') ?>
                </label>
                <input type="password" class="form-input" id="password" name="password"
                    placeholder="<?= lang('App.password') ?>" required>
                <small class="form-hint">Debe tener al menos 8 caracteres</small>
            </div>

            <div class="form-group">
                <label for="password_confirm" class="form-label">
                    <i class="icon icon-lock-fill"></i>
                    <?= lang('App.confirmPassword') ?>
                </label>
                <input type="password" class="form-input" id="password_confirm" name="password_confirm"
                    placeholder="<?= lang('App.confirmPassword') ?>" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block mb-4">
                <i class="icon icon-person-plus"></i>
                <?= lang('Auth.createAccount') ?>
            </button>

            <div class="text-center text-sm">
                <span class="text-muted"><?= lang('Auth.alreadyHaveAccount') ?></span>
                <a href="<?= base_url('login') ?>" class="font-medium text-primary hover:underline">
                    <?= lang('Auth.loginHere') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
