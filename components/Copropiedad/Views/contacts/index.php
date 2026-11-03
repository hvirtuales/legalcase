<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php use Components\Copropiedad\Models\PersonModel; ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-telephone"></i>
            <h5 class="font-semibold"><?= lang('Cop.contacts') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <a href="<?= base_url('copropiedad/contactos/nuevo') ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-plus-circle"></i> <?= lang('Cop.contactsNew') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
        <?php else: ?>
            <?= view('partials/datatable', [
                'dt'                => $dt,
                'baseUrl'           => base_url('copropiedad/contactos'),
                'searchPlaceholder' => lang('Cop.contactsSearch'),
                'emptyText'         => lang('Cop.noContacts'),
                'columns'           => [
                    ['key' => 'name', 'label' => lang('Cop.contactName'), 'sortable' => true, 'sortKey' => 'cmp_cop_contacts.name',
                        'render' => static fn ($x) => '<strong>' . esc($x['name']) . '</strong>'
                            . ((int) $x['is_primary'] === 1
                                ? ' <span class="badge badge-primary">' . esc(lang('Cop.contactPrimary')) . '</span>' : '')],
                    ['key' => 'relationship', 'label' => lang('Cop.contactRel'), 'sortable' => true, 'sortKey' => 'cmp_cop_contacts.relationship',
                        'render' => static fn ($x) => esc($x['relationship'] ?: '—')],
                    ['key' => 'phone', 'label' => lang('Cop.contactPhone'), 'sortable' => false,
                        'render' => static fn ($x) => esc($x['phone'])
                            . ($x['alt_phone'] ? '<div class="text-xs text-muted">' . esc($x['alt_phone']) . '</div>' : '')],
                    // A quién cubre: el inmueble, la persona, o los dos.
                    ['key' => 'covers', 'label' => lang('Cop.contactFor'), 'sortable' => false,
                        'render' => static function ($x) {
                            $partes = [];
                            if ($x['unit_code']) {
                                $partes[] = '<a class="text-primary hover:underline" href="'
                                    . base_url('copropiedad/inmuebles/ficha/' . (int) $x['unit_id']) . '">'
                                    . esc(($x['block_name'] ? $x['block_name'] . ' · ' : '') . $x['unit_code']) . '</a>';
                            }
                            if ($x['first_name']) {
                                $partes[] = '<a class="text-primary hover:underline" href="'
                                    . base_url('copropiedad/personas/ficha/' . (int) $x['person_id']) . '">'
                                    . esc(PersonModel::fullName($x)) . '</a>';
                            }

                            return $partes ? implode(' · ', $partes) : '—';
                        }],
                ],
                'actions' => static function ($x) {
                    $id = (int) $x['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('copropiedad/contactos/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action' => base_url('copropiedad/contactos/eliminar/' . $id),
                            'label'  => lang('Cop.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
