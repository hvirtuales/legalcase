<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <p class="text-sm text-muted">
        <?= lang('Tenant.companiesCount') ?>: <strong><?= esc($childCount) ?></strong>
    </p>
    <a href="<?= base_url('tenants/empresas/' . $tenant['id']) ?>" class="btn btn-secondary btn-sm">
        <i class="icon icon-buildings"></i> <?= lang('Tenant.groupCompanies') ?>
    </a>
</div>

<form action="<?= base_url('tenants/editar/' . $tenant['id']) ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?= view('tenants/_form', [
        'tenant'        => $tenant,
        'plans'         => $plans,
        'companyPlans'  => $companyPlans,
        'whitelist'     => $whitelist,
        'defaultPlanId' => $defaultPlanId,
        'users'         => $users,
        'subscription'  => $subscription,
        'isNew'         => false,
    ]) ?>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
        <a href="<?= base_url('tenants') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>
