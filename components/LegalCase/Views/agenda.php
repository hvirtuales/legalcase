<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Agenda: plazos abiertos y tareas pendientes de los asuntos visibles, por fecha.
 *
 * @var array $deadlines @var array $tasks @var bool $mine @var array $numbers @var array $users
 */
use Components\LegalCase\Libraries\Access;
use Components\LegalCase\Libraries\Meta;

$link    = fn (int $mid) => view('Components\LegalCase\Views\_matter_link', ['numbers' => $numbers, 'mid' => $mid]);
$canEdit = Access::current()->can('lgc.edit');
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <div class="alert alert-info mb-0"><i class="icon icon-info-circle"></i><span><?= lang('Lgc.deadlineDisclaimer') ?></span></div>
    <a href="<?= base_url('legalcase/agenda' . ($mine ? '' : '?mios=1')) ?>" class="btn <?= $mine ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><i class="icon icon-person"></i> <?= lang('Lgc.onlyMine') ?></a>
</div>
<div class="grid gap-4 lg:grid-cols-2">
    <div class="card">
        <div class="card-header"><i class="icon icon-clock"></i><h5 class="font-semibold"><?= lang('Lgc.deadlines') ?></h5></div>
        <div class="card-body">
            <?php if (!$deadlines): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noDeadlines') ?></p>
            <?php else: ?>
                <ul class="divide-y divide-line text-sm">
                    <?php foreach ($deadlines as $d): ?>
                        <li class="py-2">
                            <div class="flex flex-wrap items-center gap-2"><?= $link((int) $d['matter_id']) ?> <span class="font-semibold"><?= esc($d['title']) ?></span>
                                <?= (int) $d['is_critical'] ? '<span class="badge badge-danger">' . lang('Lgc.critical') . '</span>' : '' ?> <?= Meta::badge('deadline', $d['status']) ?></div>
                            <div class="text-xs <?= Meta::isPast($d['due_at']) ? 'font-semibold text-danger' : 'text-muted' ?>"><?= esc(Meta::date($d['due_at'])) ?> · <?= esc($users[(int) $d['assigned_to']] ?? '—') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><i class="icon icon-list-check"></i><h5 class="font-semibold"><?= lang('Lgc.tasks') ?></h5></div>
        <div class="card-body">
            <?php if (!$tasks): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noTasks') ?></p>
            <?php else: ?>
                <ul class="divide-y divide-line text-sm">
                    <?php foreach ($tasks as $t): ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <div>
                                <div class="flex flex-wrap items-center gap-2"><?= $link((int) $t['matter_id']) ?> <span class="font-semibold"><?= esc($t['title']) ?></span>
                                    <?php if ($t['kind'] !== 'task'): ?><span class="badge badge-info"><?= lang('Lgc.tkind_' . $t['kind']) ?></span><?php endif; ?></div>
                                <div class="text-xs <?= Meta::isPast($t['due_at']) ? 'text-danger' : 'text-muted' ?>"><?= esc(Meta::date($t['due_at'])) ?> · <?= esc($users[(int) $t['assigned_to']] ?? '—') ?></div>
                            </div>
                            <?php if ($canEdit): ?>
                                <form method="post" action="<?= base_url('legalcase/tareas/estado/' . (int) $t['id']) ?>"><?= csrf_field() ?>
                                    <input type="hidden" name="status" value="done"><input type="hidden" name="return_to" value="agenda">
                                    <button class="btn btn-ghost btn-sm" title="<?= esc(lang('Lgc.markDone'), 'attr') ?>"><i class="icon icon-check-circle"></i></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
