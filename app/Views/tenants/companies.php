<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Empresas ya colgadas -->
    <div class="card lg:col-span-2">
        <div class="card-header justify-between">
            <div class="flex items-center gap-2">
                <i class="icon icon-buildings"></i>
                <h5 class="font-semibold"><?= esc($tenant['name']) ?></h5>
            </div>
            <span class="badge <?= $left !== null && $left <= 0 ? 'badge-danger' : 'badge-secondary' ?>">
                <?= $left === null ? esc(lang('Tenant.unlimited')) : esc(lang('Tenant.quotaLeft', [(int) $left])) ?>
            </span>
        </div>
        <div class="card-body">
            <?php if (!$children): ?>
                <p class="text-sm text-muted"><?= lang('Tenant.noCompanies') ?></p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Tenant.company') ?></th>
                                <th><?= lang('Tenant.plan') ?></th>
                                <th class="text-right"><?= lang('Tenant.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($children as $c): ?>
                                <?php
                                $planName = null;
                                foreach ($plans as $p) {
                                    if ((int) $p['id'] === (int) ($c['plan_id'] ?? 0)) {
                                        $planName = $p['name'];
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= esc($c['name']) ?></strong>
                                        <?php if (!empty($c['nit'])): ?>
                                            <div class="text-xs text-muted"><?= esc($c['nit']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc($planName ?? lang('Tenant.noPlan')) ?></td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <a href="<?= base_url('companies/edit/' . $c['id']) ?>" class="btn btn-secondary btn-sm">
                                                <i class="icon icon-pencil"></i>
                                            </a>
                                            <?= view('partials/delete_button', [
                                                'action'  => base_url('tenants/empresas/' . $tenant['id'] . '/quitar/' . $c['id']),
                                                'confirm' => lang('Tenant.confirmDetach'),
                                                'icon'    => 'icon-x-lg',
                                                'label'   => lang('Tenant.detach'),
                                            ]) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Agregar una empresa suelta -->
    <div class="card">
        <div class="card-header">
            <i class="icon icon-plus-circle"></i>
            <h6 class="font-semibold"><?= lang('Tenant.addCompany') ?></h6>
        </div>
        <div class="card-body">
            <?php if ($left !== null && $left <= 0): ?>
                <div class="alert alert-warning">
                    <i class="icon icon-exclamation-circle"></i>
                    <span><?= lang('Tenant.quotaFull') ?></span>
                </div>
            <?php elseif (!$available): ?>
                <p class="text-sm text-muted"><?= lang('Tenant.noAvailable') ?></p>
            <?php else: ?>
                <form action="<?= base_url('tenants/empresas/' . $tenant['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="company_id" class="form-label"><?= lang('Tenant.availableCompanies') ?></label>
                        <select class="form-select" id="company_id" name="company_id" required>
                            <option value="">—</option>
                            <?php foreach ($available as $c): ?>
                                <option value="<?= esc($c['id'], 'attr') ?>"><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="icon icon-plus-circle"></i> <?= lang('Tenant.addCompany') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="mt-6">
    <a href="<?= base_url('tenants') ?>" class="btn btn-secondary"><i class="icon icon-arrow-left"></i> <?= lang('Tenant.tenants') ?></a>
</div>

<?= $this->endSection() ?>
