<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-person-badge"></i>
            <h5 class="font-semibold"><?= lang('Cop.families') ?></h5>
        </div>
        <?php if ($families !== null): ?>
            <div class="flex gap-2">
                <a href="<?= base_url('copropiedad/personas') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-people"></i> <?= lang('Cop.people') ?>
                </a>
                <a href="<?= base_url('copropiedad/familias/nuevo') ?>" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> <?= lang('Cop.familiesNew') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($families === null): ?>
            <?= view('Components\Copropiedad\Views\partials\_nocompany') ?>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Cop.familiesIntro') ?></p>

            <?php if (! $families): ?>
                <p class="text-sm text-muted"><?= lang('Cop.noFamilies') ?></p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Cop.familyName') ?></th>
                                <th><?= lang('Cop.familyUnit') ?></th>
                                <th><?= lang('Cop.familyMembers') ?></th>
                                <th class="text-right"><?= lang('Cop.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($families as $f): ?>
                                <tr>
                                    <td>
                                        <a class="font-semibold text-primary hover:underline"
                                            href="<?= base_url('copropiedad/familias/ficha/' . (int) $f['id']) ?>"><?= esc($f['name']) ?></a>
                                    </td>
                                    <td>
                                        <?php if ($f['unit_code']): ?>
                                            <a class="text-primary hover:underline"
                                                href="<?= base_url('copropiedad/inmuebles/ficha/' . (int) $f['unit_id']) ?>">
                                                <?= esc(($f['block_name'] ? $f['block_name'] . ' · ' : '') . $f['unit_code']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int) ($counts[(int) $f['id']] ?? 0) ?></td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <a href="<?= base_url('copropiedad/familias/ficha/' . (int) $f['id']) ?>" class="btn btn-secondary btn-sm">
                                                <i class="icon icon-eye"></i>
                                            </a>
                                            <a href="<?= base_url('copropiedad/familias/editar/' . (int) $f['id']) ?>" class="btn btn-secondary btn-sm">
                                                <i class="icon icon-pencil"></i>
                                            </a>
                                            <?= view('partials/delete_button', [
                                                'action'  => base_url('copropiedad/familias/eliminar/' . (int) $f['id']),
                                                'confirm' => lang('Cop.familyDelConfirm'),
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
