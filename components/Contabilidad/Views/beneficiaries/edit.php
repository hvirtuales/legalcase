<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <i class="icon icon-pencil"></i>
        <h5 class="font-semibold">Editar beneficiario</h5>
    </div>
    <div class="card-body">
        <?= view('Components\Contabilidad\Views\beneficiaries\_form', ['beneficiary' => $beneficiary, 'action' => base_url('contabilidad/beneficiarios/editar/' . $beneficiary['id'])]) ?>
    </div>
</div>

<div class="mt-4">
    <?= view('Components\Contabilidad\Views\partials\attachments', [
        'ownerType' => 'beneficiary', 'ownerId' => $beneficiary['id'], 'attachments' => $attachments ?? [],
        'canEdit' => true, 'return' => base_url('contabilidad/beneficiarios/editar/' . $beneficiary['id']),
    ]) ?>
</div>
<?= $this->endSection() ?>
