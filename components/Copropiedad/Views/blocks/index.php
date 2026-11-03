<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\Copropiedad\Libraries\Meta;

?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-diagram-3"></i>
            <h5 class="font-semibold"><?= lang('Cop.blocks') ?></h5>
        </div>
        <?php if ($blocks !== null): ?>
            <div class="flex gap-2">
                <a href="<?= base_url('copropiedad/inmuebles') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-buildings"></i> <?= lang('Cop.units') ?>
                </a>
                <a href="<?= base_url('copropiedad/torres/nuevo') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> <?= lang('Cop.blocksNew') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($blocks === null): ?>
            <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Cop.blocksIntro') ?></p>

            <?php if (! $blocks): ?>
                <p class="text-sm text-muted"><?= lang('Cop.noBlocks') ?></p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Cop.blockName') ?></th>
                                <th><?= lang('Cop.blockKind') ?></th>
                                <th><?= lang('Cop.blockFloors') ?></th>
                                <th><?= lang('Cop.blockUnits') ?></th>
                                <th><?= lang('Cop.blockOrder') ?></th>
                                <th class="text-right"><?= lang('Cop.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocks as $b): ?>
                                <?php $n = (int) ($counts[(int) $b['id']] ?? 0); ?>
                                <tr>
                                    <td>
                                        <strong><?= esc($b['name']) ?></strong>
                                        <?php if (! (int) $b['is_active']): ?>
                                            <span class="badge badge-secondary"><?= lang('Cop.inactive') ?></span>
                                        <?php endif; ?>
                                        <?php if ($b['code']): ?>
                                            <div class="text-xs text-muted"><?= esc($b['code']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc(Meta::label(Meta::BLOCK_KINDS, $b['kind'])) ?></td>
                                    <td><?= $b['floors'] !== null ? (int) $b['floors'] : '—' ?></td>
                                    <td>
                                        <?php // Enlace al listado ya filtrado por esta torre:
                                              // es lo que se quiere hacer al ver el número. ?>
                                        <a class="text-primary hover:underline"
                                            href="<?= base_url('copropiedad/inmuebles?torre=' . (int) $b['id']) ?>"><?= (int) $n ?></a>
                                    </td>
                                    <td><?= (int) $b['sort_order'] ?></td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <a href="<?= base_url('copropiedad/torres/editar/' . (int) $b['id']) ?>" class="btn btn-secondary btn-sm">
                                                <i class="icon icon-pencil"></i>
                                            </a>
                                            <?= view('partials/delete_button', [
                                                'action'  => base_url('copropiedad/torres/eliminar/' . (int) $b['id']),
                                                'confirm' => lang('Cop.blockDelConfirm'),
                                                'label'   => lang('Cop.delete'),
                                            ]) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
