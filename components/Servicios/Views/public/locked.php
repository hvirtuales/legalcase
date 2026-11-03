<?= $this->extend('Components\Servicios\Views\public\_layout') ?>

<?= $this->section('content') ?>
<?php /** @var string $token @var string|null $error */ ?>
<div class="mx-auto max-w-md">
    <div class="card">
        <div class="card-header">
            <i class="icon icon-lock"></i>
            <h5 class="font-semibold"><?= lang('Quo.pubLockedTitle') ?></h5>
        </div>
        <div class="card-body">
            <p class="mb-4 text-sm text-muted"><?= lang('Quo.pubLockedHint') ?></p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><i class="icon icon-exclamation-circle"></i><span><?= esc($error) ?></span></div>
            <?php endif; ?>

            <form action="<?= base_url('servicios/p/' . $token . '/acceso') ?>" method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="password" class="form-label"><?= lang('Quo.pubPassword') ?></label>
                    <input type="password" id="password" name="password" class="form-input" required autofocus autocomplete="off">
                </div>
                <button class="btn btn-primary w-full"><i class="icon icon-box-arrow-in-right"></i> <?= lang('Quo.pubOpen') ?></button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
