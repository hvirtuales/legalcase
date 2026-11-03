<?= $this->extend('Components\Servicios\Views\public\_layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto max-w-md text-center">
    <div class="card">
        <div class="card-body py-10">
            <i class="icon icon-x-circle text-4xl text-danger"></i>
            <h5 class="mt-3 font-semibold"><?= lang('Quo.pubInvalidTitle') ?></h5>
            <p class="mt-2 text-sm text-muted"><?= lang('Quo.pubInvalidHint') ?></p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
