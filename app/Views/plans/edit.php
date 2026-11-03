<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-pencil-square"></i>
        <h5 class="font-semibold">Editar Plan</h5>
    </div>
    <div class="card-body">
        <?= view('plans/_form', ['plan' => $plan, 'action' => base_url('plans/edit/' . $plan['id'])]) ?>
    </div>
</div>
<?= $this->endSection() ?>
