<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\LeadModel;

/*
 * La ficha 360 (§11): arriba lo que se necesita saber de un vistazo y las cinco
 * acciones del documento (WhatsApp, Email, Actividad, Consulta, Convertir); debajo,
 * las nueve pestañas. Cada pestaña se carga por su propia URL (`?tab=`), así que la
 * ficha no arrastra de la base lo que no se está mirando.
 */
$id   = (int) $lead['id'];
$wa   = $lead['whatsapp_norm'] ?: $lead['phone_norm'];
$tabs = [
    'resumen' => ['icon-speedometer2', 'tabSummary'], 'timeline' => ['icon-clock-history', 'tabTimeline'],
    'info' => ['icon-person', 'tabInfo'], 'precalificacion' => ['icon-clipboard', 'tabPrequal'],
    'actividades' => ['icon-list-check', 'tabActivities'], 'consultas' => ['icon-headset', 'tabAppointments'],
    'comunicaciones' => ['icon-chat', 'tabComms'], 'documentos' => ['icon-paperclip', 'tabDocuments'],
    'expediente' => ['icon-folder', 'navMatters'],
    'docketwise' => ['icon-plug', 'tabDocketwise'],
];
?>
<div class="card mb-4">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="avatar"><?= esc(mb_strtoupper(mb_substr($lead['first_name'], 0, 1) . mb_substr($lead['last_name'], 0, 1))) ?></span>
                <div>
                    <h4 class="text-xl font-semibold"><?= esc(LeadModel::fullName($lead)) ?></h4>
                    <p class="text-sm text-muted"><?= esc($service['name'] ?? lang('Lfl.noService')) ?></p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span><?= lang('Lfl.score') ?>: <?= Meta::scoreBadge((int) $lead['score'], $lead['priority']) ?></span>
                        <span><?= lang('Lfl.priority') ?>:
                            <span class="badge <?= Meta::color(Meta::PRIORITY_COLORS, $lead['priority']) ?>"><?= esc(Meta::label(Meta::PRIORITIES, $lead['priority'])) ?></span>
                        </span>
                        <span><?= lang('Lfl.stage') ?>: <?= Meta::stageBadge($stage) ?></span>
                        <span><?= lang('Lfl.owner') ?>:
                            <strong><?= esc($lead['assigned_user_id'] ? ($team[(int) $lead['assigned_user_id']] ?? '—') : lang('Lfl.unassigned')) ?></strong>
                        </span>
                        <?php if ($lead['lawyer_user_id']): ?>
                            <span><?= lang('Lfl.lawyer') ?>: <strong><?= esc($team[(int) $lead['lawyer_user_id']] ?? '—') ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($wa): ?>
                    <a href="https://wa.me/<?= esc($wa, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                        <i class="icon icon-whatsapp"></i> <?= lang('Lfl.chWhatsapp') ?>
                    </a>
                <?php endif; ?>
                <?php if ($lead['email']): ?>
                    <a href="<?= base_url('leadflow/leads/ficha/' . $id . '?tab=comunicaciones#email') ?>" class="btn btn-secondary btn-sm">
                        <i class="icon icon-envelope"></i> <?= lang('Lfl.chEmail') ?>
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('leadflow/leads/ficha/' . $id . '?tab=actividades#nueva') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-list-check"></i> <?= lang('Lfl.activity') ?>
                </a>
                <a href="<?= base_url('leadflow/leads/ficha/' . $id . '?tab=consultas#nueva') ?>" class="btn btn-secondary btn-sm">
                    <i class="icon icon-headset"></i> <?= lang('Lfl.appointment') ?>
                </a>
                <?php /* Editar estaba solo dentro de la pestaña «Información»: desde aquí se ve en cualquier pestaña. */ ?>
                <?php if ($canEdit): ?>
                    <a href="<?= base_url('leadflow/leads/editar/' . $id) ?>" class="btn btn-secondary btn-sm">
                        <i class="icon icon-pencil"></i> <?= lang('Lfl.edit') ?>
                    </a>
                <?php endif; ?>
                <?php if ($canConvert && $lead['status'] === 'won'): ?>
                    <a href="<?= base_url('leadflow/convertir/' . $id) ?>" class="btn btn-primary btn-sm">
                        <i class="icon icon-box-arrow-up-right"></i> <?= lang('Lfl.convert') ?>
                    </a>
                <?php elseif ($lead['status'] === 'converted'): ?>
                    <span class="badge badge-primary self-center"><i class="icon icon-check-circle"></i> <?= lang('Lfl.stConverted') ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<nav class="tabs mb-4">
    <?php foreach ($tabs as $key => [$icon, $label]): ?>
        <a href="<?= base_url('leadflow/leads/ficha/' . $id . '?tab=' . $key) ?>" class="tab-link <?= $tab === $key ? 'active' : '' ?>">
            <i class="icon <?= esc($icon, 'attr') ?>"></i> <?= lang('Lfl.' . $label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?= view('Components\LeadFlow\Views\partials\_errors') ?>

<?= $this->include('Components\LeadFlow\Views\leads\tabs\_' . $tab) ?>
<?= $this->endSection() ?>
