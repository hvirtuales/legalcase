<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\MatterModel;

/*
 * Ficha del expediente. Misma estructura que la del lead: arriba lo que hay que saber
 * de un vistazo y las acciones; debajo, las pestañas, cada una por su propia URL.
 *
 * El enlace al lead de origen es importante y va arriba: el expediente no repite la
 * captación (fuente, campaña, scoring, comunicaciones), que sigue viviendo allí.
 *
 * @var array      $matter
 * @var array      $lead
 * @var string     $tab
 * @var array      $stages
 * @var array|null $stage
 * @var array      $team
 * @var array      $timeline
 * @var bool       $canEdit
 * @var bool       $canDelete
 */
$id      = (int) $matter['id'];
$cerrado = MatterModel::isClosed($matter);
$tabs    = [
    'resumen'    => ['icon-speedometer2', 'mtrTabSummary'],
    'requisitos' => ['icon-list-check', 'mtrTabRequirements'],
    'honorarios' => ['icon-cash-stack', 'mtrTabFees'],
    'tareas'     => ['icon-clock-history', 'mtrTabTasks'],
    'timeline'   => ['icon-journal-text', 'mtrTabTimeline'],
];
?>
<div class="card mb-4">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <code class="badge badge-light font-mono"><?= esc($matter['number']) ?></code>
                    <span class="badge <?= Meta::MATTER_STATUS_COLORS[$matter['status']] ?? 'badge-light' ?>">
                        <?= esc(lang('Lfl.' . (Meta::MATTER_STATUSES[$matter['status']] ?? 'msActive'))) ?>
                    </span>
                    <?= Meta::stageBadge($stage) ?>
                </div>
                <h4 class="mt-2 text-xl font-semibold"><?= esc($matter['title'] ?: MatterModel::clientName($matter)) ?></h4>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <span><?= lang('Lfl.mtrClient') ?>: <strong><?= esc(MatterModel::clientName($matter)) ?></strong></span>
                    <span><?= lang('Lfl.mtrLawyer') ?>: <strong><?= esc($matter['lawyer_name'] ?: '—') ?></strong></span>
                    <span><?= lang('Lfl.mtrOpenedAt') ?>: <strong><?= esc(Meta::date($matter['opened_at'], false)) ?></strong></span>
                    <?php if ($matter['stage_entered_at'] && ! $cerrado): ?>
                        <span class="text-muted"><?= esc(lang('Lfl.mtrInStageSince', [Meta::date($matter['stage_entered_at'], false)])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= base_url('leadflow/leads/ficha/' . (int) $lead['id']) ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-person"></i> <?= lang('Lfl.mtrSeeLead') ?>
                </a>
                <?php if ($canEdit && ! $cerrado): ?>
                    <a href="<?= base_url('leadflow/expedientes/editar/' . $id) ?>" class="btn btn-secondary btn-sm">
                        <i class="icon icon-pencil"></i> <?= lang('Lfl.edit') ?>
                    </a>
                <?php endif; ?>
                <?php if ($canEdit && $cerrado): ?>
                    <?php /* Reabrir es POST: cambia el estado del expediente. */ ?>
                    <form action="<?= base_url('leadflow/expedientes/reabrir/' . $id) ?>" method="post"
                        onsubmit="return confirm('<?= esc(lang('Lfl.mtrConfirmReopen'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="leadflow/expedientes/ficha/<?= (int) $id ?>">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="icon icon-arrow-counterclockwise"></i> <?= lang('Lfl.mtrReopen') ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                    <?= view('partials/delete_button', [
                        'action'  => base_url('leadflow/expedientes/eliminar/' . $id),
                        'confirm' => lang('Lfl.confirmDelete'),
                        'label'   => lang('Lfl.delete'),
                    ]) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<nav class="tabs mb-4">
    <?php foreach ($tabs as $k => [$icon, $label]): ?>
        <a href="<?= base_url('leadflow/expedientes/ficha/' . $id . '?tab=' . $k) ?>"
            class="tab-link <?= $tab === $k ? 'active' : '' ?>">
            <i class="icon <?= esc($icon, 'attr') ?>"></i> <?= lang('Lfl.' . $label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?= view('Components\LeadFlow\Views\partials\_errors') ?>

<?= $this->include('Components\LeadFlow\Views\matters\tabs\_' . $tab) ?>
<?= $this->endSection() ?>
