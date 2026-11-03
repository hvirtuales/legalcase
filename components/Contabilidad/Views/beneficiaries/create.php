<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-person-plus"></i>
        <h5 class="font-semibold">Nuevo beneficiario</h5>
    </div>
    <div class="card-body">
        <?= view('Components\Contabilidad\Views\beneficiaries\_form', ['beneficiary' => [], 'action' => base_url('contabilidad/beneficiarios/crear')]) ?>
    </div>
</div>
<?= $this->endSection() ?>
