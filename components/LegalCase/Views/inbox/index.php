<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Bandeja de consultas de clientes. Por defecto muestra lo abierto; «Vencidas» = sin
 * primera respuesta y con la hora objetivo superada.
 *
 * @var array $dt @var array $filters @var array $users
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\ThreadModel;

$base = base_url('legalcase/mensajes');
?>
<div class="card">
    <div class="card-header"><i class="icon icon-chat"></i><h5 class="font-semibold"><?= lang('Lgc.inbox') ?></h5></div>
    <div class="card-body">
        <form method="get" action="<?= $base ?>" class="mb-4 flex flex-wrap items-end gap-2">
            <div class="form-group mb-0">
                <label for="f-q" class="form-label text-xs"><?= lang('Lgc.search') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="icon icon-search"></i></span>
                    <input type="text" id="f-q" name="q" value="<?= esc($dt['q'] ?? '', 'attr') ?>" class="form-input">
                </div>
            </div>
            <div class="form-group mb-0">
                <label for="f-estado" class="form-label text-xs"><?= lang('Lgc.fStatus') ?></label>
                <select id="f-estado" name="estado" class="form-select" onchange="this.form.submit()">
                    <option value=""><?= lang('Lgc.openOnes') ?></option>
                    <?php foreach (ThreadModel::STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= $filters['estado'] === $s ? 'selected' : '' ?>><?= lang('Lgc.thread_' . $s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                <label class="btn <?= $filters['vencidas'] === '1' ? 'btn-danger' : 'btn-ghost' ?> cursor-pointer">
                    <input type="checkbox" name="vencidas" value="1" class="hidden" onchange="this.form.submit()" <?= $filters['vencidas'] === '1' ? 'checked' : '' ?>>
                    <i class="icon icon-clock"></i> <?= lang('Lgc.onlyLate') ?>
                </label>
            </div>
            <div class="form-group mb-0">
                <label class="form-label invisible select-none text-xs" aria-hidden="true">.</label>
                <button type="submit" class="btn btn-secondary" title="<?= esc(lang('Lgc.filter'), 'attr') ?>"><i class="icon icon-funnel"></i></button>
            </div>
        </form>

        <?= view('partials/datatable', [
            'dt'          => $dt,
            'showSearch'  => false,
            'baseUrl'     => $base,
            'extraParams' => $filters,
            'emptyText'   => lang('Lgc.noThreads'),
            'columns'     => [
                ['key' => 'subject', 'label' => lang('Lgc.fSubject'), 'sortable' => false,
                    'render' => static fn ($r) => '<a class="font-semibold text-primary" href="' . base_url('legalcase/mensajes/hilo/' . (int) $r['id']) . '">' . esc($r['subject']) . '</a>'
                        . '<div class="text-xs text-muted"><span class="font-mono">' . esc($r['matter_number']) . '</span> · ' . esc($r['matter_title']) . '</div>'],
                ['key' => 'assigned_to', 'label' => lang('Lgc.fAssignee'), 'sortable' => false,
                    'render' => static fn ($r) => esc($users[(int) $r['assigned_to']] ?? lang('Lgc.unassigned'))],
                ['key' => 'first_response_due_at', 'label' => lang('Lgc.fSlaDue'), 'sortable' => true,
                    'render' => static fn ($r) => $r['first_response_at']
                        ? '<span class="text-xs text-muted">' . esc(lang('Lgc.answeredAt', [Meta::date($r['first_response_at'])])) . '</span>'
                        : '<span class="' . (Meta::isPast($r['first_response_due_at']) ? 'font-semibold text-danger' : 'text-muted') . '">' . esc(Meta::date($r['first_response_due_at'])) . '</span>'],
                ['key' => 'last_message_at', 'label' => lang('Lgc.fLastMessage'), 'sortable' => true,
                    'render' => static fn ($r) => esc(Meta::date($r['last_message_at']))],
                ['key' => 'status', 'label' => lang('Lgc.fStatus'), 'sortable' => true,
                    'render' => static fn ($r) => Meta::badge('thread', $r['status'])],
            ],
            'actions' => static fn ($r) => '<div class="flex justify-end"><a href="' . base_url('legalcase/mensajes/hilo/' . (int) $r['id']) . '" class="btn btn-secondary btn-sm"><i class="icon icon-eye"></i></a></div>',
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
