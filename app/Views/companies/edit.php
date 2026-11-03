<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<form action="<?= base_url('companies/edit/' . $company['id']) ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger">
            <ul class="list-inside list-disc">
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Datos de la empresa -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="icon icon-buildings"></i>
            <h6 class="font-semibold">Datos de la Empresa</h6>
        </div>
        <div class="card-body">
            <?= view('companies/_fields', ['company' => $company, 'tenants' => $tenants ?? []]) ?>
        </div>
    </div>

    <!-- Administrador (dueño) -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="icon icon-person-gear"></i>
            <h6 class="font-semibold">Administrador (dueño)</h6>
        </div>
        <div class="card-body">
            <div class="form-group md:w-2/3">
                <label for="owner_id" class="form-label">Administrador de la empresa</label>
                <select class="form-select" id="owner_id" name="owner_id">
                    <option value="">— Sin administrador —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= esc($u['id'], 'attr') ?>" <?= (string) old('owner_id', $company['owner_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= esc(trim($u['first_name'] . ' ' . $u['last_name'])) ?> (<?= esc($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint">Se enlazará a la empresa y recibirá el rol Admin.</small>
            </div>
        </div>
    </div>

    <!-- Plan / Suscripción -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="icon icon-box-seam"></i>
            <h6 class="font-semibold">Plan y Suscripción</h6>
        </div>
        <div class="card-body">
            <?= view('companies/_subscription', ['plans' => $plans, 'subscription' => $subscription]) ?>
        </div>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
        <a href="<?= base_url('companies') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>
