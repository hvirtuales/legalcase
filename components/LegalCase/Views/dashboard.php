<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * «Mi día»: cuatro números arriba y, debajo, lo que hay que hacer hoy — comunicación
 * vencida, plazos próximos o por validar, consultas sin responder, aprobaciones y tareas.
 *
 * @var array $metrics @var array $overdue @var array $deadlines @var array $tasks
 * @var array $threads @var array $reviews @var array $numbers @var array $users
 */
use Components\LegalCase\Libraries\Access;
use Components\LegalCase\Libraries\Meta;

$link = fn (int $mid) => view('Components\LegalCase\Views\_matter_link', ['numbers' => $numbers, 'mid' => $mid]);
?>
<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <?php foreach ([
        ['icon-briefcase', 'mActive', $metrics['active'], 'text-primary', 'legalcase/asuntos?estado=open'],
        ['icon-send', 'mOverdueComms', $metrics['overdue'], 'text-danger', 'legalcase/comunicaciones'],
        ['icon-clock', 'mDeadlines', $metrics['deadlines'], 'text-warning', 'legalcase/agenda'],
        ['icon-chat', 'mThreads', $metrics['threads'], 'text-success', 'legalcase/mensajes'],
    ] as [$icon, $label, $value, $tone, $href]): ?>
        <a href="<?= base_url($href) ?>" class="card block transition hover:shadow-md">
            <div class="card-body py-4">
                <div class="flex items-center gap-2 text-xs text-muted"><i class="icon <?= $icon ?> <?= $tone ?>"></i> <?= lang('Lgc.' . $label) ?></div>
                <div class="mt-1 text-2xl font-bold <?= $tone ?>"><?= (int) $value ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php if (Access::current()->can('lgc.create')): ?>
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="<?= base_url('legalcase/asuntos/nuevo') ?>" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i> <?= lang('Lgc.matterNew') ?></a>
        <a href="<?= base_url('legalcase/contactos/nuevo') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-person-plus"></i> <?= lang('Lgc.contactNew') ?></a>
        <a href="<?= base_url('legalcase/agenda') ?>" class="btn btn-ghost btn-sm"><i class="icon icon-clock"></i> <?= lang('Lgc.reviewDeadlines') ?></a>
    </div>
<?php endif; ?>

<div class="grid gap-4 lg:grid-cols-2">
    <div class="card">
        <div class="card-header"><i class="icon icon-send text-danger"></i><h5 class="font-semibold"><?= lang('Lgc.overdueComms') ?></h5></div>
        <div class="card-body">
            <?php if (!$overdue): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noOverdueComms') ?></p>
            <?php else: ?>
                <ul class="divide-y divide-line text-sm">
                    <?php foreach ($overdue as $o): ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <div>
                                <a class="font-semibold text-primary" href="<?= base_url('legalcase/asuntos/ficha/' . (int) $o['matter_id'] . '?tab=comunicacion') ?>"><span class="font-mono"><?= esc($o['number']) ?></span> · <?= esc($o['title']) ?></a>
                                <div class="text-xs text-danger"><?= lang('Lgc.dueSince', [Meta::date($o['next_update_due_at'], false)]) ?> · <?= esc($users[(int) ($o['owner_id'] ?: $o['lead_lawyer_id'])] ?? '—') ?></div>
                            </div>
                            <a class="btn btn-secondary btn-sm" href="<?= base_url('legalcase/asuntos/actualizacion/' . (int) $o['matter_id'] . '?tipo=no_news') ?>"><i class="icon icon-history"></i> <?= lang('Lgc.updNoNews') ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-clock text-warning"></i><h5 class="font-semibold"><?= lang('Lgc.deadlines14') ?></h5></div>
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
        <div class="card-header"><i class="icon icon-chat text-success"></i><h5 class="font-semibold"><?= lang('Lgc.pendingThreads') ?></h5></div>
        <div class="card-body">
            <?php if (!$threads): ?>
                <p class="text-sm text-muted"><?= lang('Lgc.noThreads') ?></p>
            <?php else: ?>
                <ul class="divide-y divide-line text-sm">
                    <?php foreach ($threads as $t): ?>
                        <li class="py-2">
                            <div class="flex flex-wrap items-center gap-2"><?= $link((int) $t['matter_id']) ?>
                                <a class="font-semibold text-primary" href="<?= base_url('legalcase/mensajes/hilo/' . (int) $t['id']) ?>"><?= esc($t['subject']) ?></a></div>
                            <div class="text-xs <?= !$t['first_response_at'] && Meta::isPast($t['first_response_due_at']) ? 'font-semibold text-danger' : 'text-muted' ?>">
                                <?= lang('Lgc.fSlaDue') ?>: <?= esc(Meta::date($t['first_response_due_at'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="icon icon-list-check"></i><h5 class="font-semibold"><?= lang('Lgc.myWork') ?></h5></div>
        <div class="card-body space-y-4">
            <?php if ($reviews): ?>
                <div>
                    <div class="mb-1 text-xs font-semibold uppercase text-muted"><?= lang('Lgc.toApprove') ?></div>
                    <ul class="space-y-1 text-sm">
                        <?php foreach ($reviews as $r): ?>
                            <li><?= $link((int) $r['matter_id']) ?> <a class="text-primary" href="<?= base_url('legalcase/actualizaciones/ver/' . (int) $r['id']) ?>"><?= esc($r['title']) ?></a> <?= Meta::badge('upd', $r['status']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div>
                <div class="mb-1 text-xs font-semibold uppercase text-muted"><?= lang('Lgc.myTasks') ?></div>
                <?php if (!$tasks): ?>
                    <p class="text-sm text-muted"><?= lang('Lgc.noTasks') ?></p>
                <?php else: ?>
                    <ul class="space-y-1 text-sm">
                        <?php foreach ($tasks as $t): ?>
                            <li><?= $link((int) $t['matter_id']) ?> <?= esc($t['title']) ?>
                                <span class="text-xs <?= Meta::isPast($t['due_at']) ? 'text-danger' : 'text-muted' ?>"><?= esc(Meta::date($t['due_at'])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
