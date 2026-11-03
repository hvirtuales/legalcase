<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Empresas del grupo, tal como las ve su administrador.
 *
 * Es el «menú de empresas» del nivel tenant y usa **el mismo listado que
 * `/companies`** (`DataTable` + `partials/datatable`) para que no se note el
 * salto entre una pantalla y otra; lo que cambia es el alcance, no la forma.
 *
 * @var array    $tenant @var array $dt @var array $plans @var array $subMap
 * @var object   $subModel @var int $used @var int|null $left
 * @var bool     $canCreate @var ?string $blocked
 */
?>

<?php if ($blocked): ?>
    <div class="alert alert-warning mb-4">
        <i class="icon icon-exclamation-circle"></i>
        <span><?= esc($blocked) ?></span>
    </div>
<?php endif; ?>

<?php if (!$plans): ?>
    <div class="alert alert-warning mb-4">
        <i class="icon icon-exclamation-circle"></i>
        <span><?= lang('Tenant.noAssignablePlans') ?></span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-buildings"></i>
            <h5 class="font-semibold"><?= lang('Tenant.groupCompanies') ?></h5>
            <span class="badge badge-light"><i class="icon icon-diagram-3"></i> <?= esc($tenant['name']) ?></span>
            <span class="badge badge-secondary">
                <?= (int) $used ?><?= $left === null ? '' : ' / ' . (int) ($used + $left) ?>
            </span>
        </div>
        <?php if ($canCreate): ?>
            <a href="<?= base_url('tenant/empresas/nueva') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Tenant.newCompany') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?= view('partials/datatable', [
            'dt'                => $dt,
            'baseUrl'           => base_url('tenant/empresas'),
            'searchPlaceholder' => lang('Tenant.searchCompanies'),
            'emptyText'         => lang('Tenant.noCompanies'),
            'columns'           => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'sortKey' => 'companies.id'],
                ['key' => 'name', 'label' => lang('Tenant.company'), 'sortable' => true, 'sortKey' => 'companies.name',
                    'render' => fn ($c) => '<strong>' . esc($c['name']) . '</strong>'],
                ['key' => 'nit', 'label' => 'NIT', 'sortable' => true, 'sortKey' => 'companies.nit',
                    'render' => fn ($c) => esc($c['nit'] ?: '—')],
                ['key' => 'plan_name', 'label' => lang('Tenant.plan'),
                    'render' => fn ($c) => esc($c['plan_name'] ?? '—')],
                ['key' => '_sub', 'label' => lang('Tenant.subscription'),
                    'render' => function ($c) use ($subMap, $subModel) {
                        $sub = $subMap[(int) $c['id']] ?? null;
                        if ($sub && $subModel->isValid($sub)) {
                            return '<span class="badge badge-success">' . lang('Tenant.valid') . '</span>';
                        }

                        return $sub
                            ? '<span class="badge badge-danger">' . lang('Tenant.expired') . '</span>'
                            : '<span class="badge badge-secondary">' . lang('Tenant.noSubscription') . '</span>';
                    }],
                ['key' => 'owner_name', 'label' => lang('Tenant.admin'),
                    'render' => fn ($c) => esc(trim((string) ($c['owner_name'] ?? '')) ?: '—')],
                ['key' => 'is_active', 'label' => lang('App.status'), 'sortable' => true, 'sortKey' => 'companies.is_active',
                    'render' => fn ($c) => !empty($c['is_active'])
                        ? '<span class="badge badge-success">' . lang('App.active') . '</span>'
                        : '<span class="badge badge-secondary">' . lang('App.inactive') . '</span>'],
            ],
            'actions'           => function ($c) use ($subMap, $subModel) {
                $id   = (int) $c['id'];
                $html = '<div class="flex justify-end gap-1">';
                $html .= '<a href="' . base_url('tenant/empresas/' . $id . '/editar') . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Tenant.editCompany'), 'attr') . '"><i class="icon icon-pencil"></i></a>';

                // Entrar solo si su suscripción está al día: es lo mismo que
                // haría el selector del encabezado.
                if ($subModel->isValid($subMap[$id] ?? null)) {
                    $html .= '<a href="' . base_url('switch-company/' . $id) . '" class="btn btn-primary btn-sm">'
                        . lang('Tenant.enter') . ' <i class="icon icon-chevron-right"></i></a>';
                }

                return $html . '</div>';
            },
        ]) ?>
    </div>
</div>

<?= $this->endSection() ?>
