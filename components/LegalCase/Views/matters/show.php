<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Ficha del asunto: arriba lo que hay que saber de un vistazo y las acciones; debajo
 * las pestañas, cada una por su propia URL (?tab=), como la ficha de LeadFlow.
 *
 * @var array $matter @var string $tab @var array $tabs @var array $users @var ?array $client
 * @var ?array $plan @var array $settings @var array $counts
 * @var \Components\LegalCase\Libraries\Access $acc
 */
use Components\LegalCase\Libraries\Meta;
use Components\LegalCase\Models\MatterModel;

$id     = (int) $matter['id'];
$closed = $matter['status'] === 'closed';
$icons  = [
    'resumen' => 'icon-speedometer2', 'procesos' => 'icon-landmark', 'plazos' => 'icon-clock',
    'documentos' => 'icon-folder', 'comunicacion' => 'icon-send', 'mensajes' => 'icon-chat',
    'partes' => 'icon-people', 'bitacora' => 'icon-journal-text',
];
$badges = ['plazos' => $counts['deadlines'], 'mensajes' => $counts['threads'], 'documentos' => $counts['requests'], 'comunicacion' => $counts['drafts']];
$late   = !$closed && Meta::isPast($matter['next_update_due_at']);
?>
<div class="card mb-4">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <code class="badge badge-light font-mono"><?= esc($matter['number']) ?></code>
                    <?= Meta::badge('matter', $matter['status']) ?>
                    <?php if ($matter['visibility'] === 'team'): ?>
                        <span class="badge badge-secondary"><i class="icon icon-lock"></i> <?= lang('Lgc.visibility_team') ?></span>
                    <?php endif; ?>
                    <?php if ((int) ($matter['legal_hold'] ?? 0) === 1): ?>
                        <span class="badge badge-warning"><i class="icon icon-lock-fill"></i> <?= lang('Lgc.legalHold') ?></span>
                    <?php endif; ?>
                    <?php if ($matter['sensitivity'] === 'high'): ?>
                        <span class="badge badge-danger"><i class="icon icon-shield-lock"></i> <?= lang('Lgc.sensitivity_high') ?></span>
                    <?php endif; ?>
                </div>
                <h4 class="mt-2 text-xl font-semibold"><?= esc($matter['title']) ?></h4>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <span><?= lang('Lgc.fClient') ?>: <strong><?= esc($client['name'] ?? lang('Lgc.noClient')) ?></strong></span>
                    <span><?= lang('Lgc.fLawyer') ?>: <strong><?= esc($users[(int) $matter['lead_lawyer_id']] ?? '—') ?></strong></span>
                    <span><?= lang('Lgc.fOpenedAt') ?>: <strong><?= esc(Meta::date($matter['opened_at'], false)) ?></strong></span>
                    <span class="<?= $late ? 'font-semibold text-danger' : 'text-muted' ?>">
                        <i class="icon icon-send"></i> <?= lang('Lgc.fNextUpdate') ?>: <?= esc(Meta::date($matter['next_update_due_at'], false)) ?>
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if (!$closed && ($canWork && $acc->can('lgc.create'))): ?>
                    <a href="<?= base_url('legalcase/asuntos/actualizacion/' . $id) ?>" class="btn btn-primary btn-sm">
                        <i class="icon icon-send"></i> <?= lang('Lgc.updNew') ?>
                    </a>
                <?php endif; ?>
                <?php if (($canWork && $acc->can('lgc.edit'))): ?>
                    <?php if (!$closed): ?>
                        <a href="<?= base_url('legalcase/asuntos/editar/' . $id) ?>" class="btn btn-secondary btn-sm"><i class="icon icon-pencil"></i> <?= lang('Lgc.edit') ?></a>
                        <form method="post" action="<?= base_url('legalcase/asuntos/pausa/' . $id) ?>" class="inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-ghost btn-sm">
                                <i class="icon icon-<?= $matter['status'] === 'on_hold' ? 'play-circle' : 'dash-circle' ?>"></i>
                                <?= lang($matter['status'] === 'on_hold' ? 'Lgc.resume' : 'Lgc.hold') ?>
                            </button>
                        </form>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('dlg-close').showModal()">
                            <i class="icon icon-check-circle"></i> <?= lang('Lgc.close') ?>
                        </button>
                    <?php else: ?>
                        <form method="post" action="<?= base_url('legalcase/asuntos/reabrir/' . $id) ?>" class="inline"
                            onsubmit="return confirm('<?= esc(lang('Lgc.confirmReopen'), 'js') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-arrow-counterclockwise"></i> <?= lang('Lgc.reopen') ?></button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($canWork && $acc->can('lgc.configure')): ?>
                    <form method="post" action="<?= base_url('legalcase/asuntos/bloqueo/' . $id) ?>" class="inline"
                        onsubmit="return confirm('<?= esc(lang((int) ($matter['legal_hold'] ?? 0) === 1 ? 'Lgc.confirmHoldOff' : 'Lgc.confirmHoldOn'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost btn-sm" title="<?= esc(lang('Lgc.legalHoldHint'), 'attr') ?>">
                            <i class="icon icon-lock"></i> <?= lang((int) ($matter['legal_hold'] ?? 0) === 1 ? 'Lgc.holdOff' : 'Lgc.holdOn') ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (($canWork && $acc->can('lgc.delete'))): ?>
                    <?= view('partials/delete_button', [
                        'action'  => base_url('legalcase/asuntos/eliminar/' . $id),
                        'confirm' => lang('Lgc.confirmDeleteMatter', [$matter['number']]),
                        'label'   => lang('Lgc.delete'),
                        'class'   => 'btn btn-danger btn-sm',
                        'icon'    => 'trash',
                        'text'    => '',
                    ]) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$canWork && !$closed): ?>
            <p class="mt-3 flex items-center gap-2 text-sm text-muted"><i class="icon icon-eye"></i> <?= lang($acc->isSupport() ? 'Lgc.readOnlySupport' : 'Lgc.readOnlyNotTeam') ?></p>
        <?php endif; ?>
        <?php if ($closed): ?>
            <p class="mt-3 text-sm text-muted"><?= lang('Lgc.closedOn', [Meta::date($matter['closed_at'], false), lang('Lgc.reason_' . ($matter['close_reason'] ?: 'other'))]) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (!$closed && ($canWork && $acc->can('lgc.edit'))): ?>
    <dialog id="dlg-close" class="m-auto w-full max-w-md rounded-lg border border-line bg-surface p-0 text-ink shadow-lg">
        <form method="post" action="<?= base_url('legalcase/asuntos/cerrar/' . $id) ?>" class="p-5">
            <?= csrf_field() ?>
            <h5 class="mb-2 font-semibold"><?= lang('Lgc.closeTitle') ?></h5>
            <p class="mb-3 text-sm text-muted"><?= lang('Lgc.closeHint') ?></p>
            <label for="close_reason" class="form-label"><?= lang('Lgc.fCloseReason') ?> <span class="text-danger">*</span></label>
            <?php if ($pending): ?>
                <div class="alert alert-warning mb-3">
                    <i class="icon icon-exclamation-circle"></i>
                    <div>
                        <strong><?= lang('Lgc.closePendingTitle') ?></strong>
                        <ul class="mt-1 text-sm">
                            <?php foreach ($pending as $k => $n): ?>
                                <li><?= lang('Lgc.pending_' . $k, [(int) $n]) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="mt-1 text-xs"><?= lang('Lgc.closePendingHint') ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <select id="close_reason" name="close_reason" class="form-select" required>
                <?php foreach (MatterModel::CLOSE_REASONS as $r): ?>
                    <option value="<?= $r ?>"><?= lang('Lgc.reason_' . $r) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($pending): ?>
                <div class="form-check mt-3">
                    <input type="checkbox" id="confirm_pending" name="confirm_pending" value="1" class="form-check-input" required>
                    <label for="confirm_pending" class="form-check-label"><?= lang('Lgc.confirmPending') ?></label>
                </div>
            <?php endif; ?>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()"><?= lang('Lgc.cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= lang('Lgc.close') ?></button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

<nav class="tabs mb-4 overflow-x-auto">
    <?php foreach ($tabs as $k): ?>
        <a href="<?= base_url('legalcase/asuntos/ficha/' . $id . '?tab=' . $k) ?>" class="tab-link whitespace-nowrap <?= $tab === $k ? 'active' : '' ?>">
            <i class="icon <?= $icons[$k] ?>"></i> <?= lang('Lgc.tab_' . $k) ?>
            <?php if (!empty($badges[$k])): ?><span class="badge badge-warning"><?= (int) $badges[$k] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?= view('Components\LegalCase\Views\_errors') ?>

<?= $this->include('Components\LegalCase\Views\matters\tabs\_' . $tab) ?>
<?= $this->endSection() ?>
