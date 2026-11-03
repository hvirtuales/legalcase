<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;
use Components\Copropiedad\Models\PersonModel;

$id = (int) $family['id'];
?>

<div class="mb-4 flex flex-wrap items-start justify-between gap-2">
    <div>
        <div class="flex items-center gap-2">
            <i class="icon icon-person-badge text-xl"></i>
            <h4 class="text-xl font-semibold"><?= esc($family['name']) ?></h4>
        </div>
        <?php if ($unit): ?>
            <p class="text-sm text-muted">
                <a class="text-primary hover:underline" href="<?= base_url('copropiedad/inmuebles/ficha/' . (int) $unit['id']) ?>">
                    <?= esc($unit['code']) ?>
                </a>
                · <?= esc(Meta::label(Meta::UNIT_KINDS, $unit['kind'])) ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="flex gap-2">
        <a href="<?= base_url('copropiedad/familias/editar/' . $id) ?>" class="btn btn-secondary btn-sm">
            <i class="icon icon-pencil"></i> <?= lang('Cop.edit') ?>
        </a>
        <a href="<?= base_url('copropiedad/familias') ?>" class="btn btn-ghost btn-sm">
            <i class="icon icon-arrow-left"></i> <?= lang('Cop.back') ?>
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="icon icon-people"></i>
        <h5 class="font-semibold"><?= lang('Cop.familyMembers') ?></h5>
    </div>
    <div class="card-body">
        <?php if (! $members): ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Cop.noPeople') ?></p>
        <?php else: ?>
            <div class="mb-4 overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= lang('Cop.relPickPerson') ?></th>
                            <th><?= lang('Cop.familyRole') ?></th>
                            <th><?= lang('Cop.phone') ?></th>
                            <th class="text-right"><?= lang('Cop.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td>
                                    <a class="font-medium text-primary hover:underline"
                                        href="<?= base_url('copropiedad/personas/ficha/' . (int) $m['id']) ?>">
                                        <?= esc(PersonModel::fullName($m)) ?>
                                    </a>
                                    <?php if ((int) $family['head_person_id'] === (int) $m['id']): ?>
                                        <span class="badge badge-primary"><?= lang('Cop.familyHead') ?></span>
                                    <?php endif; ?>
                                    <div class="text-xs text-muted"><?= esc($m['doc_type'] . ' ' . $m['doc_number']) ?></div>
                                </td>
                                <td><?= esc(Meta::label(Meta::FAMILY_ROLES, $m['family_role'])) ?></td>
                                <td><?= esc($m['phone'] ?: '—') ?></td>
                                <td>
                                    <div class="flex justify-end">
                                        <?php // Quitar del grupo NO da de baja a la persona. ?>
                                        <form action="<?= base_url('copropiedad/familias/quitar/' . $id) ?>" method="post"
                                            onsubmit="return confirm('<?= esc(lang('Cop.memberRemoveConfirm'), 'js') ?>')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="person_id" value="<?= (int) $m['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="<?= esc(lang('Cop.remove'), 'attr') ?>">
                                                <i class="icon icon-x-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php // Solo se ofrecen las personas SIN grupo: mover a alguien de su hogar
              // sin avisar sería peor que no dejar hacerlo. ?>
        <?php if (! $free): ?>
            <p class="text-sm text-muted"><?= lang('Cop.familyNoFree') ?></p>
        <?php else: ?>
            <form action="<?= base_url('copropiedad/familias/miembro/' . $id) ?>" method="post">
                <?= csrf_field() ?>
                <fieldset class="form-section">
                    <legend class="form-section-title"><?= lang('Cop.familyAddMember') ?></legend>

                    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                        <div class="form-group md:col-span-2">
                            <label for="fam-person" class="form-label"><?= lang('Cop.relPickPerson') ?> <span class="text-danger">*</span></label>
                            <select id="fam-person" name="person_id" class="form-select" required>
                                <option value=""><?= lang('Cop.relSelectPerson') ?></option>
                                <?php foreach ($free as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>">
                                        <?= esc(PersonModel::fullName($p) . ' (' . $p['doc_type'] . ' ' . $p['doc_number'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fam-role" class="form-label"><?= lang('Cop.familyRole') ?></label>
                            <select id="fam-role" name="family_role" class="form-select">
                                <?php foreach (Meta::options(Meta::FAMILY_ROLES) as $val => $lbl): ?>
                                    <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-person-plus"></i> <?= lang('Cop.add') ?></button>
                </fieldset>
            </form>
        <?php endif; ?>

        <?php if (! empty($family['notes'])): ?>
            <p class="mt-3 whitespace-pre-line text-sm text-muted"><?= esc($family['notes']) ?></p>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
