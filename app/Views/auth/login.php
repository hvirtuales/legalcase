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
                <img src="<?= media_url($loginImage) ?>" alt="<?= esc($appName) ?>" class="mx-auto max-h-20">
            <?php else: ?>
                <h1 class="text-2xl font-bold text-primary"><?= esc($appName) ?></h1>
            <?php endif; ?>
        </div>

        <!-- Title -->
        <h2 class="text-center text-xl font-semibold text-ink"><?= lang('Auth.login') ?></h2>
        <p class="mb-6 mt-1 text-center text-sm text-muted"><?= lang('Auth.loginSubtitle') ?></p>

        <!-- Alerts -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error">
                <i class="icon icon-exclamation-circle"></i>
                <span><?= session()->getFlashdata('error') ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <i class="icon icon-check-circle"></i>
                <span><?= session()->getFlashdata('success') ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-error">
                <ul class="list-inside list-disc">
                    <?php foreach (session()->getFlashdata('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="<?= base_url('login') ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username" class="form-label">
                    <i class="icon icon-person"></i>
                    <?= lang('Auth.usernameOrEmail') ?>
                </label>
                <input type="text" class="form-input" id="username" name="username"
                    placeholder="<?= lang('Auth.enterUsernameOrEmail') ?>" value="<?= old('username') ?>" required
                    autofocus>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="icon icon-lock"></i>
                    <?= lang('App.password') ?>
                </label>
                <input type="password" class="form-input" id="password" name="password"
                    placeholder="<?= lang('Auth.enterPassword') ?>" required>
            </div>

            <div class="mb-4 flex items-center justify-between">
                <label class="inline-flex items-center gap-2 text-sm text-muted">
                    <input class="size-4 rounded border-line text-primary focus:ring-primary/20" type="checkbox"
                        id="remember" name="remember">
                    <?= lang('Auth.rememberMe') ?>
                </label>
                <a href="<?= base_url('forgot-password') ?>" class="text-sm font-medium text-primary hover:underline">
                    <?= lang('Auth.forgotPassword') ?>
                </a>
            </div>

            <button type="submit" class="btn btn-primary btn-block mb-4">
                <i class="icon icon-box-arrow-in-right"></i>
                <?= lang('Auth.login') ?>
            </button>

            <div class="text-center text-sm">
                <span class="text-muted"><?= lang('Auth.noAccount') ?></span>
                <a href="<?= base_url('register') ?>" class="font-medium text-primary hover:underline">
                    <?= lang('Auth.registerHere') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
