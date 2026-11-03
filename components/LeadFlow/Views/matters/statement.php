<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\MatterFeeModel;
use Components\LeadFlow\Models\MatterModel;

/*
 * Estado de cuenta, para imprimir o guardar como PDF desde el navegador.
 *
 * Es HTML y no un PDF generado: hacerlo exigiría llevar una copia propia de
 * `PdfService` dentro del componente (§15.1 del contrato, porque un componente no
 * puede depender de código que él mismo añadiera al núcleo) y no desbloquea nada que
 * no desbloquee ya el diálogo de impresión.
 *
 * **No es una factura.** No lleva numeración fiscal, ni impuestos, ni retenciones: es
 * un resumen de lo pactado y lo recibido. Lo dice el pie, para que nadie lo use como
 * lo que no es.
 *
 * @var array $matter
 * @var array $lead
 * @var array $fees
 * @var array $byKind
 * @var array $payments
 * @var array $company
 */
$moneda = (string) ($matter['currency'] ?: 'USD');
$saldo  = MatterModel::balance($matter);
?>
<div class="card">
    <div class="card-header justify-between">
        <div class="flex items-center gap-2">
            <i class="icon icon-receipt"></i>
            <h5 class="font-semibold"><?= lang('Lfl.mtrStatement') ?></h5>
            <code class="badge badge-light font-mono"><?= esc($matter['number']) ?></code>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php /* Imprimir es cosa del navegador: no hay nada que mandar al servidor. */ ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                <i class="icon icon-printer"></i> <?= lang('Lfl.mtrStatementPrint') ?>
            </button>
            <a href="<?= base_url('leadflow/expedientes/ficha/' . (int) $matter['id'] . '?tab=honorarios') ?>"
                class="btn btn-secondary btn-sm"><?= lang('Lfl.back') ?></a>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-muted"><?= lang('Lfl.dirKindFirm') ?></p>
                <p class="font-semibold"><?= esc($company['name'] ?? '—') ?></p>
            </div>
            <div>
                <p class="text-sm text-muted"><?= lang('Lfl.mtrClient') ?></p>
                <p class="font-semibold"><?= esc(MatterModel::clientName($matter)) ?></p>
                <?php if ($matter['client_email']): ?>
                    <p class="text-sm text-muted"><?= esc($matter['client_email']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-sm text-muted"><?= lang('Lfl.mtrService') ?></p>
                <p class="font-medium"><?= esc($matter['service_name'] ?: '—') ?></p>
            </div>
            <div>
                <p class="text-sm text-muted"><?= lang('Lfl.mtrOpenedAt') ?></p>
                <p class="font-medium"><?= esc(Meta::date($matter['opened_at'], false)) ?></p>
            </div>
        </div>

        <h6 class="mb-2 text-sm font-semibold"><?= lang('Lfl.mtrFees') ?></h6>
        <div class="table-card mb-6">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= lang('Lfl.mtrFeeConcept') ?></th>
                        <th><?= lang('Lfl.mtrFeeKind') ?></th>
                        <th class="text-right"><?= lang('Lfl.mtrFeeAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fees as $f): ?>
                        <tr>
                            <td><?= esc($f['concept']) ?></td>
                            <td class="text-sm"><?= lang('Lfl.fk_' . $f['kind']) ?></td>
                            <td class="text-right font-mono"><?= esc(Meta::money((float) $f['amount'], $moneda)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($fees === []): ?>
                        <tr><td colspan="3" class="text-sm text-muted"><?= lang('Lfl.mtrFeeNone') ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h6 class="mb-2 text-sm font-semibold"><?= lang('Lfl.mtrPayments') ?></h6>
        <div class="table-card mb-6">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= lang('Lfl.mtrPaymentDate') ?></th>
                        <th><?= lang('Lfl.mtrPaymentMethod') ?></th>
                        <th><?= lang('Lfl.mtrPaymentRef') ?></th>
                        <th class="text-right"><?= lang('Lfl.mtrFeeAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td class="text-sm"><?= esc(Meta::date($p['paid_at'], false)) ?></td>
                            <td class="text-sm"><?= lang('Lfl.pm_' . $p['method']) ?></td>
                            <td class="text-xs text-muted"><?= esc($p['reference'] ?: '—') ?></td>
                            <td class="text-right font-mono"><?= esc(Meta::money((float) $p['amount'], $moneda)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($payments === []): ?>
                        <tr><td colspan="4" class="text-sm text-muted"><?= lang('Lfl.mtrPaymentNone') ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="ml-auto max-w-sm">
            <dl class="flex flex-col gap-2 text-sm">
                <?php foreach ($byKind as $tipo => $importe): ?>
                    <?php if ($importe <= 0) {
                        continue;
                    } ?>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-muted"><?= lang('Lfl.fk_' . $tipo) ?></dt>
                        <dd class="font-mono"><?= esc(Meta::money($importe, $moneda)) ?></dd>
                    </div>
                <?php endforeach; ?>
                <div class="flex items-baseline justify-between gap-4 <?= array_sum($byKind) > 0 ? "border-t border-line pt-2" : "" ?>">
                    <dt class="font-semibold"><?= lang('Lfl.mtrStatementTotal') ?></dt>
                    <dd class="font-mono font-semibold"><?= esc(Meta::money((float) $matter['fee_total'], $moneda)) ?></dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted"><?= lang('Lfl.mtrPaidTotal') ?></dt>
                    <dd class="font-mono"><?= esc(Meta::money((float) $matter['paid_total'], $moneda)) ?></dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="font-semibold"><?= lang('Lfl.mtrBalance') ?></dt>
                    <dd class="font-mono font-semibold <?= $saldo > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= esc(Meta::money($saldo, $moneda)) ?>
                    </dd>
                </div>
            </dl>
        </div>

        <p class="mt-6 text-xs text-muted"><?= lang('Lfl.mtrFeesHint') ?></p>
    </div>
</div>
<?= $this->endSection() ?>
