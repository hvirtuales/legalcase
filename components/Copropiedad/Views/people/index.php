<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-people"></i>
            <h5 class="font-semibold"><?= lang('Cop.people') ?></h5>
        </div>
        <?php if ($dt !== null): ?>
            <div class="flex gap-2">
                <a href="<?= base_url('copropiedad/familias') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-person-badge"></i> <?= lang('Cop.families') ?>
                </a>
                <a href="<?= base_url('copropiedad/personas/nuevo') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-person-plus"></i> <?= lang('Cop.peopleNew') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($dt === null): ?>
            <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Cop.peopleIntro') ?></p>

            <form method="get" action="<?= base_url('copropiedad/personas') ?>" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="form-group mb-0">
                    <label for="f-q" class="form-label text-xs"><?= lang('Cop.search') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="icon icon-search"></i></span>
                        <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input"
                            placeholder="<?= esc(lang('Cop.peopleSearch'), 'attr') ?>">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="f-papel" class="form-label text-xs"><?= lang('Cop.peopleRoleFil') ?></label>
                    <select id="f-papel" name="papel" class="form-select">
                        <option value=""><?= lang('Cop.all') ?></option>
                        <?php foreach (Meta::options(Meta::ROLES) as $v => $l): ?>
                            <option value="<?= esc($v, 'attr') ?>" <?= $role === $v ? 'selected' : '' ?>><?= esc($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Cop.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
                        <?php if ($role !== '' || ($dt['q'] ?? '') !== ''): ?>
                            <a href="<?= base_url('copropiedad/personas') ?>" class="btn btn-ghost" title="<?= esc(lang('Cop.clear'), 'attr') ?>"><i class="icon icon-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?= view('partials/datatable', [
                'dt'          => $dt,
                'showSearch'  => false,
                'baseUrl'     => base_url('copropiedad/personas'),
                'extraParams' => ['papel' => $role],
                'emptyText'   => lang('Cop.noPeople'),
                'columns'     => [
                    ['key' => 'last_name', 'label' => lang('Cop.lastName'), 'sortable' => true, 'sortKey' => 'cmp_cop_people.last_name',
                        'render' => static fn ($p) => '<a class="font-semibold text-primary hover:underline" href="'
                            . base_url('copropiedad/personas/ficha/' . (int) $p['id']) . '">' . esc(PersonModel::fullName($p)) . '</a>'
                            . ($p['family_name'] ? '<div class="text-xs text-muted">' . esc($p['family_name']) . '</div>' : '')],
                    ['key' => 'doc_number', 'label' => lang('Cop.docNumber'), 'sortable' => true, 'sortKey' => 'cmp_cop_people.doc_number',
                        'render' => static fn ($p) => esc($p['doc_type'] . ' ' . $p['doc_number'])],
                    // El papel no está en la persona: lo trae `roleMap`, resuelto en
                    // UNA consulta para toda la página.
                    ['key' => 'role', 'label' => lang('Cop.peopleRoleFil'), 'sortable' => false,
                        'render' => static function ($p) use ($roleMap) {
                            $papeles = $roleMap[(int) $p['id']] ?? [];

                            // Sin papel, la celda es el camino para dárselo: llevar a
                            // su ficha, que es donde está el formulario. Un «sin
                            // relación vigente» en gris no decía qué hacer.
                            if (! $papeles) {
                                return '<a class="text-muted hover:underline" href="'
                                    . base_url('copropiedad/personas/ficha/' . (int) $p['id']) . '">'
                                    . esc(lang('Cop.peopleNoRole')) . ' <i class="icon icon-plus-circle"></i></a>';
                            }
                            $out = '';
                            foreach ($papeles as $papel) {
                                $out .= '<span class="badge ' . Meta::color(Meta::ROLE_COLORS, $papel) . '">'
                                    . esc(Meta::label(Meta::ROLES, $papel)) . '</span> ';
                            }

                            return trim($out);
                        }],
                    ['key' => 'phone', 'label' => lang('Cop.phone'), 'sortable' => false,
                        'render' => static fn ($p) => esc($p['phone'] ?: '—')],
                    ['key' => 'email', 'label' => lang('Cop.email'), 'sortable' => true, 'sortKey' => 'cmp_cop_people.email',
                        'render' => static fn ($p) => esc($p['email'] ?: '—')],
                ],
                'actions' => static function ($p) {
                    $id = (int) $p['id'];

                    return '<div class="flex justify-end gap-1">'
                        . '<a href="' . base_url('copropiedad/personas/ficha/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-eye"></i></a>'
                        . '<a href="' . base_url('copropiedad/personas/editar/' . $id) . '" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i></a>'
                        . view('partials/delete_button', [
                            'action'  => base_url('copropiedad/personas/eliminar/' . $id),
                            'confirm' => lang('Cop.personDelConfirm'),
                            'label'   => lang('Cop.delete'),
                        ])
                        . '</div>';
                },
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
