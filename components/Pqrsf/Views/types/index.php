<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * El catálogo de tipos.
 *
 * Son pocos y ordenados a mano, así que aquí no hace falta `DataTable`: una
 * tabla simple con el plazo, el uso y el orden se lee mejor.
 *
 * @var array|null $rows @var array $usage
 */
$base = base_url('pqrsf/tipos');
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-list-check"></i>
            <h5 class="font-semibold"><?= lang('Pqr.types') ?></h5>
        </div>
        <?php if ($rows !== null): ?>
            <div class="flex gap-2">
                <form method="post" action="<?= $base ?>/restaurar"
                    onsubmit="return confirm('<?= esc(lang('Pqr.typesRestoreConfirm'), 'attr') ?>');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm" title="<?= esc(lang('Pqr.typesRestoreHint'), 'attr') ?>">
                        <i class="icon icon-arrow-counterclockwise"></i> <?= lang('Pqr.typesRestore') ?>
                    </button>
                </form>
                <a href="<?= $base ?>/nuevo" class="btn btn-primary btn-sm">
                    <i class="icon icon-plus-circle"></i> <?= lang('Pqr.typesNew') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($rows === null): ?>
            <div class="alert alert-info"><i class="icon icon-info-circle"></i><span><?= lang('Pqr.errNoCompany') ?></span></div>
        <?php else: ?>
            <p class="mb-4 text-sm text-muted"><?= lang('Pqr.typesHint') ?></p>

            <?php if (!$rows): ?>
                <p class="text-sm text-muted"><?= lang('Pqr.noTypes') ?></p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= lang('Pqr.typeOrder') ?></th>
                                <th><?= lang('Pqr.typeName') ?></th>
                                <th><?= lang('Pqr.typeCode') ?></th>
                                <th><?= lang('Pqr.typeDays') ?></th>
                                <th><?= lang('Pqr.typeUsed') ?></th>
                                <th><?= lang('Pqr.fStatus') ?></th>
                                <th class="text-right"><?= lang('Pqr.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $t): ?>
                                <?php $used = (int) ($usage[$t['code']] ?? 0); ?>
                                <tr>
                                    <td class="text-muted"><?= (int) $t['sort_order'] ?></td>
                                    <td class="font-semibold"><?= esc($t['name']) ?></td>
                                    <td><code class="text-xs"><?= esc($t['code']) ?></code></td>
                                    <td><?= lang('Pqr.typeDaysN', [(int) $t['days']]) ?></td>
                                    <td>
                                        <?php if ($used): ?>
                                            <a class="badge badge-info" href="<?= base_url('pqrsf?tipo=' . urlencode($t['code'])) ?>"><?= $used ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($t['is_active'])): ?>
                                            <span class="badge badge-success"><?= lang('Pqr.active') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><?= lang('Pqr.inactive') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <a href="<?= $base ?>/editar/<?= (int) $t['id'] ?>" class="btn btn-secondary btn-sm">
                                                <i class="icon icon-pencil"></i>
                                            </a>
                                            <?php if ($used === 0): ?>
                                                <?= view('partials/delete_button', [
                                                    'action'  => $base . '/eliminar/' . (int) $t['id'],
                                                    'label'   => lang('Pqr.delete'),
                                                    'confirm' => lang('Pqr.typeDeleteConfirm', [$t['name']]),
                                                ]) ?>
                                            <?php else: ?>
                                                <span class="btn btn-ghost btn-sm cursor-not-allowed opacity-40"
                                                    title="<?= esc(lang('Pqr.errTypeInUse', [$used]), 'attr') ?>">
                                                    <i class="icon icon-trash"></i>
                                                </span>
                                            <?php endif; ?>
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
