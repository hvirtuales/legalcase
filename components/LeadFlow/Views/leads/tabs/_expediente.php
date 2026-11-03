<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\MatterModel;

/*
 * El expediente, visto desde el lead.
 *
 * Dos estados y nada más: o el lead ya tiene expediente —y entonces esto es un
 * resumen con enlace, no una segunda ficha— o todavía no, y aquí está el botón para
 * abrirlo. La gestión del caso vive en `/leadflow/expedientes`, no duplicada aquí.
 *
 * El botón lleva `lfl.convert`, igual que la ruta: abrir expediente ES convertir el
 * lead en cliente. Si el lead no está ganado, el servicio lo rechazará; aquí se dice
 * antes para no mandar a nadie a una pantalla que va a negarse.
 *
 * @var array      $lead
 * @var array|null $matter
 * @var bool       $canOpen
 */
$lid     = (int) $lead['id'];
$ganado  = in_array((string) ($lead['status'] ?? ''), ['won', 'converted'], true);
$moneda  = 'USD';
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-folder"></i>
            <?php /* Singular: un lead tiene un expediente, no varios. El plural está
                     en el menú y en el listado, que sí los agrupan. */ ?>
            <h6 class="font-semibold"><?= lang('Lfl.mtrOne') ?></h6>
        </div>
        <?php if ($matter !== null): ?>
            <a href="<?= base_url('leadflow/expedientes/ficha/' . (int) $matter['id']) ?>" class="btn btn-primary btn-sm">
                <i class="icon icon-box-arrow-up-right"></i> <?= lang('Lfl.mtrSeeMatter') ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($matter !== null): ?>
            <?php $moneda = (string) ($matter['currency'] ?: 'USD'); ?>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <code class="badge badge-light font-mono"><?= esc($matter['number']) ?></code>
                <span class="badge <?= Meta::MATTER_STATUS_COLORS[$matter['status']] ?? 'badge-light' ?>">
                    <?= esc(lang('Lfl.' . (Meta::MATTER_STATUSES[$matter['status']] ?? 'msActive'))) ?>
                </span>
                <strong><?= esc($matter['title'] ?: MatterModel::clientName($matter)) ?></strong>
            </div>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                <?php foreach ([
                    'mtrLawyer'    => $matter['lawyer_name'] ?: '—',
                    'mtrOpenedAt'  => Meta::date($matter['opened_at'], false),
                    'mtrFeeTotal'  => Meta::money((float) $matter['fee_total'], $moneda),
                    'mtrPaidTotal' => Meta::money((float) $matter['paid_total'], $moneda),
                    'mtrBalance'   => Meta::money(MatterModel::balance($matter), $moneda),
                    'mtrDocs'      => (int) $matter['docs_received'] . ' / ' . (int) $matter['docs_required'],
                ] as $clave => $valor): ?>
                    <div>
                        <dt class="text-muted"><?= lang('Lfl.' . $clave) ?></dt>
                        <dd class="font-medium"><?= esc($valor) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

        <?php else: ?>
            <div class="rounded border border-line p-6 text-center">
                <p class="mb-1 font-semibold"><?= lang('Lfl.mtrNoMatter') ?></p>
                <p class="mb-4 text-sm text-muted"><?= lang('Lfl.mtrNoMatterHint') ?></p>

                <?php if (! $ganado): ?>
                    <p class="text-sm text-muted"><?= lang('Lfl.mtrNotWon') ?></p>
                <?php elseif ($canOpen): ?>
                    <a href="<?= base_url('leadflow/expedientes/abrir/' . $lid) ?>" class="btn btn-primary btn-sm">
                        <i class="icon icon-folder"></i> <?= lang('Lfl.mtrOpen') ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
