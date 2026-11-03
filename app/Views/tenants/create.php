<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<form action="<?= base_url('tenants/crear') ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?= view('tenants/_form', [
        'tenant'       => [],
        'plans'        => $plans,
        'companyPlans' => $companyPlans,
        'whitelist'    => [],
        'defaultPlanId' => null,
        'users'        => $users,
        'subscription' => null,
        'isNew'        => true,
    ]) ?>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Tenant.createTenant') ?></button>
        <a href="<?= base_url('tenants') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function toggleTenantAdminMode() {
        const isNew = document.getElementById('admin_mode_new').checked;
        document.getElementById('tenantAdminNew').classList.toggle('hidden', !isNew);
        document.getElementById('tenantAdminExisting').classList.toggle('hidden', isNew);
    }
    toggleTenantAdminMode();
</script>
<?= $this->endSection() ?>
