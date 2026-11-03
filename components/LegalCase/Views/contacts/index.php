<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Listado de contactos. El papel (cliente, contraparte…) no está aquí: lo da cada asunto.
 *
 * @var array $dt
 */
use Components\LegalCase\Models\ContactModel;

$canCreate = (new \App\Models\UserModel())->hasPermission((int) session('user_id'), 'lgc.create');
$canEdit   = (new \App\Models\UserModel())->hasPermission((int) session('user_id'), 'lgc.edit');
$canDelete = (new \App\Models\UserModel())->hasPermission((int) session('user_id'), 'lgc.delete');
?>
<div class="card">
    <div class="card-header flex-wrap justify-between gap-2">
        <div class="flex items-center gap-2">
            <i class="icon icon-people"></i>
            <h5 class="font-semibold"><?= lang('Lgc.contacts') ?></h5>
        </div>
        <?php if ($canCreate): ?>
            <a href="<?= base_url('legalcase/contactos/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-person-plus"></i> <?= lang('Lgc.contactNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?= view('partials/datatable', [
            'dt'        => $dt,
            'baseUrl'   => base_url('legalcase/contactos'),
            'emptyText' => lang('Lgc.noContacts'),
            'columns'   => [
                ['key' => 'name', 'label' => lang('Lgc.fName'), 'sortable' => true,
                    'render' => static fn ($r) => '<div class="font-semibold">' . esc($r['name']) . '</div>'
                        . '<div class="text-xs text-muted">' . esc(trim($r['doc_type'] . ' ' . $r['doc_number'])) . '</div>'],
                ['key' => 'kind', 'label' => lang('Lgc.fKind'), 'sortable' => true,
                    'render' => static fn ($r) => '<span class="badge badge-light">' . esc(lang('Lgc.kind_' . $r['kind'])) . '</span>'],
                ['key' => 'email', 'label' => lang('Lgc.fEmail'), 'sortable' => false,
                    'render' => static fn ($r) => esc($r['email'] ?: '—')],
                ['key' => 'phone', 'label' => lang('Lgc.fPhone'), 'sortable' => false,
                    'render' => static fn ($r) => esc($r['phone'] ?: '—')],
                ['key' => 'consent_status', 'label' => lang('Lgc.fConsentStatus'), 'sortable' => false,
                    'render' => static fn ($r) => '<span class="badge ' . (['granted' => 'badge-success', 'revoked' => 'badge-danger'][$r['consent_status'] ?? 'none'] ?? 'badge-warning') . '">' . esc(lang('Lgc.consent_' . ($r['consent_status'] ?: 'none'))) . '</span>'],
                ['key' => 'city', 'label' => lang('Lgc.fCity'), 'sortable' => false,
                    'render' => static fn ($r) => esc(trim($r['city'] . ' ' . $r['country']))],
            ],
            'actions' => static function ($r) use ($canEdit, $canDelete) {
                $out = '<div class="flex justify-end gap-1">';
                if ($canEdit) {
                    $out .= '<a href="' . base_url('legalcase/contactos/editar/' . (int) $r['id']) . '" class="btn btn-secondary btn-sm" title="' . esc(lang('Lgc.edit'), 'attr') . '"><i class="icon icon-pencil"></i></a>';
                }
                if ($canDelete) {
                    $out .= view('partials/delete_button', [
                        'action'  => base_url('legalcase/contactos/eliminar/' . (int) $r['id']),
                        'confirm' => lang('Lgc.confirmDeleteContact'),
                        'label'   => lang('Lgc.delete'),
                        'class' => 'btn btn-danger btn-sm',
                        'icon' => 'trash',
                        'text' => '',
                    ]);
                }

                return $out . '</div>';
            },
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
