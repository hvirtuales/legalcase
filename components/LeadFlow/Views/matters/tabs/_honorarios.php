<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\MatterFeeModel;
use Components\LeadFlow\Models\MatterModel;
use Components\LeadFlow\Models\MatterPaymentModel;

/*
 * Lo pactado y lo recibido del expediente.
 *
 * El desglose por tipo va arriba y separado del total porque es la distinción que le
 * importa al cliente: de 3.500, cuánto son honorarios de la firma y cuánto tasas del
 * gobierno que solo pasan por ella.
 *
 * @var array $matter
 * @var array $fees
 * @var array $byKind
 * @var array $payments
 * @var bool  $canEdit
 */
$id      = (int) $matter['id'];
$volver  = 'leadflow/expedientes/ficha/' . $id . '?tab=honorarios';
$moneda  = (string) ($matter['currency'] ?: 'USD');
$cerrado = MatterModel::isClosed($matter);
$saldo   = MatterModel::balance($matter);
?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-[2fr_1fr]">
    <div class="flex flex-col gap-4">
        <?php // ── Lo pactado ──────────────────────────────────────────────────── ?>
        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2">
                    <i class="icon icon-cash-stack"></i>
                    <h6 class="font-semibold"><?= lang('Lfl.mtrFees') ?></h6>
                </div>
                <a href="<?= base_url('leadflow/expedientes/estado-cuenta/' . $id) ?>"
                    class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                    <i class="icon icon-receipt"></i> <?= lang('Lfl.mtrStatement') ?>
                </a>
            </div>
            <div class="card-body">
                <p class="mb-4 text-sm text-muted"><?= lang('Lfl.mtrFeesHint') ?></p>

                <?php if ($fees === []): ?>
                    <p class="rounded border border-line p-4 text-sm text-muted"><?= lang('Lfl.mtrFeeNone') ?></p>
                <?php else: ?>
                    <div class="table-card">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= lang('Lfl.mtrFeeConcept') ?></th>
                                    <th><?= lang('Lfl.mtrFeeKind') ?></th>
                                    <th><?= lang('Lfl.mtrFeeDue') ?></th>
                                    <th class="text-right"><?= lang('Lfl.mtrFeeAmount') ?></th>
                                    <?php if ($canEdit && ! $cerrado): ?><th></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fees as $f): ?>
                                    <tr>
                                        <td>
                                            <span class="font-medium"><?= esc($f['concept']) ?></span>
                                            <?php if ($f['note']): ?>
                                                <div class="text-xs text-muted"><?= esc($f['note']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $f['kind'] === MatterFeeModel::INCOME ? 'badge-primary' : 'badge-light' ?>">
                                                <?= lang('Lfl.fk_' . $f['kind']) ?>
                                            </span>
                                        </td>
                                        <td class="text-sm text-muted"><?= esc(Meta::date($f['due_at'], false)) ?></td>
                                        <td class="text-right font-mono"><?= esc(Meta::money((float) $f['amount'], $moneda)) ?></td>
                                        <?php if ($canEdit && ! $cerrado): ?>
                                            <td class="text-right">
                                                <?= view('partials/delete_button', [
                                                    'action'  => base_url('leadflow/expedientes/honorario/eliminar/' . (int) $f['id']),
                                                    'confirm' => lang('Lfl.confirmDelete'),
                                                    'label'   => lang('Lfl.delete'),
                                                ]) ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($canEdit && ! $cerrado): ?>
                    <div class="form-section mt-4">
                        <h6 class="form-section-title"><?= lang('Lfl.mtrFeeAdd') ?></h6>
                        <form action="<?= base_url('leadflow/expedientes/honorario/' . $id) ?>" method="post"
                            class="flex flex-wrap items-end gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">

                            <div class="form-group mb-0 flex-1" style="min-width:180px">
                                <label for="fee-concept" class="form-label text-xs"><?= lang('Lfl.mtrFeeConcept') ?></label>
                                <input type="text" id="fee-concept" name="concept" class="form-input" maxlength="160" required>
                            </div>
                            <div class="form-group mb-0">
                                <label for="fee-kind" class="form-label text-xs"><?= lang('Lfl.mtrFeeKind') ?></label>
                                <select id="fee-kind" name="kind" class="form-select">
                                    <?php foreach (MatterFeeModel::KINDS as $k): ?>
                                        <option value="<?= esc($k, 'attr') ?>"><?= lang('Lfl.fk_' . $k) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label for="fee-amount" class="form-label text-xs"><?= lang('Lfl.mtrFeeAmount') ?></label>
                                <input type="number" step="0.01" min="0" id="fee-amount" name="amount" class="form-input"
                                    style="max-width:130px" required>
                            </div>
                            <div class="form-group mb-0">
                                <label for="fee-due" class="form-label text-xs"><?= lang('Lfl.mtrFeeDue') ?></label>
                                <input type="date" id="fee-due" name="due_date" class="form-input">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php // ── Lo recibido ─────────────────────────────────────────────────── ?>
        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <i class="icon icon-wallet"></i>
                    <h6 class="font-semibold"><?= lang('Lfl.mtrPayments') ?></h6>
                </div>
            </div>
            <div class="card-body">
                <?php if ($payments === []): ?>
                    <p class="rounded border border-line p-4 text-sm text-muted"><?= lang('Lfl.mtrPaymentNone') ?></p>
                <?php else: ?>
                    <div class="table-card">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= lang('Lfl.mtrPaymentDate') ?></th>
                                    <th><?= lang('Lfl.mtrPaymentMethod') ?></th>
                                    <th><?= lang('Lfl.mtrPaymentRef') ?></th>
                                    <th class="text-right"><?= lang('Lfl.mtrFeeAmount') ?></th>
                                    <?php if ($canEdit && ! $cerrado): ?><th></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td class="text-sm"><?= esc(Meta::date($p['paid_at'], false)) ?></td>
                                        <td class="text-sm"><?= lang('Lfl.pm_' . $p['method']) ?></td>
                                        <td class="text-xs text-muted"><?= esc($p['reference'] ?: '—') ?></td>
                                        <td class="text-right font-mono"><?= esc(Meta::money((float) $p['amount'], $moneda)) ?></td>
                                        <?php if ($canEdit && ! $cerrado): ?>
                                            <td class="text-right">
                                                <?= view('partials/delete_button', [
                                                    'action'  => base_url('leadflow/expedientes/pago/eliminar/' . (int) $p['id']),
                                                    'confirm' => lang('Lfl.confirmDelete'),
                                                    'label'   => lang('Lfl.delete'),
                                                ]) ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($canEdit && ! $cerrado): ?>
                    <div class="form-section mt-4">
                        <h6 class="form-section-title"><?= lang('Lfl.mtrPaymentAdd') ?></h6>
                        <form action="<?= base_url('leadflow/expedientes/pago/' . $id) ?>" method="post"
                            class="flex flex-wrap items-end gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= esc($volver, 'attr') ?>">

                            <div class="form-group mb-0">
                                <label for="pay-amount" class="form-label text-xs"><?= lang('Lfl.mtrFeeAmount') ?></label>
                                <input type="number" step="0.01" min="0.01" id="pay-amount" name="amount" class="form-input"
                                    style="max-width:130px" required>
                            </div>
                            <div class="form-group mb-0">
                                <label for="pay-date" class="form-label text-xs"><?= lang('Lfl.mtrPaymentDate') ?></label>
                                <input type="date" id="pay-date" name="paid_date" class="form-input"
                                    value="<?= esc(date('Y-m-d'), 'attr') ?>">
                            </div>
                            <div class="form-group mb-0">
                                <label for="pay-method" class="form-label text-xs"><?= lang('Lfl.mtrPaymentMethod') ?></label>
                                <select id="pay-method" name="method" class="form-select">
                                    <?php foreach (MatterPaymentModel::METHODS as $m): ?>
                                        <option value="<?= esc($m, 'attr') ?>" <?= $m === 'transfer' ? 'selected' : '' ?>>
                                            <?= lang('Lfl.pm_' . $m) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label for="pay-fee" class="form-label text-xs"><?= lang('Lfl.mtrPaymentAgainst') ?></label>
                                <select id="pay-fee" name="fee_id" class="form-select">
                                    <option value=""><?= lang('Lfl.mtrPaymentOnAccount') ?></option>
                                    <?php foreach ($fees as $f): ?>
                                        <option value="<?= (int) $f['id'] ?>"><?= esc($f['concept']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label for="pay-ref" class="form-label text-xs"><?= lang('Lfl.mtrPaymentRef') ?></label>
                                <input type="text" id="pay-ref" name="reference" class="form-input" maxlength="80"
                                    style="max-width:150px">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon icon-plus-circle"></i></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php // ── El resumen, con el desglose que le importa al cliente ──────────── ?>
    <div class="card self-start">
        <div class="card-header">
            <div class="flex items-center gap-2">
                <i class="icon icon-receipt"></i>
                <h6 class="font-semibold"><?= lang('Lfl.mtrStatementTotal') ?></h6>
            </div>
        </div>
        <div class="card-body">
            <dl class="flex flex-col gap-2 text-sm">
                <?php foreach ($byKind as $tipo => $importe): ?>
                    <?php if ($importe <= 0) {
                        continue;
                    } ?>
                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-muted"><?= lang('Lfl.fk_' . $tipo) ?></dt>
                        <dd class="font-mono"><?= esc(Meta::money($importe, $moneda)) ?></dd>
                    </div>
                <?php endforeach; ?>

                <div class="mt-2 flex items-baseline justify-between gap-2 <?= array_sum($byKind) > 0 ? "border-t border-line pt-2" : "" ?>">
                    <dt class="font-semibold"><?= lang('Lfl.mtrFeeTotal') ?></dt>
                    <dd class="font-mono font-semibold"><?= esc(Meta::money((float) $matter['fee_total'], $moneda)) ?></dd>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted"><?= lang('Lfl.mtrPaidTotal') ?></dt>
                    <dd class="font-mono"><?= esc(Meta::money((float) $matter['paid_total'], $moneda)) ?></dd>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <dt class="font-semibold"><?= lang('Lfl.mtrBalance') ?></dt>
                    <dd class="font-mono font-semibold <?= $saldo > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= esc(Meta::money($saldo, $moneda)) ?>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>
