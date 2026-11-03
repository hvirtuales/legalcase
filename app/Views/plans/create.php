<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-box-seam"></i>
        <h5 class="font-semibold">Crear Plan</h5>
    </div>
    <div class="card-body">
        <?= view('plans/_form', ['plan' => [], 'action' => base_url('plans/create')]) ?>
    </div>
</div>
<?= $this->endSection() ?>
