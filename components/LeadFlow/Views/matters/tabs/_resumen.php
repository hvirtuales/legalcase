<?php

use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Models\MatterModel;

/*
 * Resumen del expediente: los datos congelados, el dinero, el cambio de etapa y el
 * cierre. La línea de tiempo va recortada aquí y completa en su pestaña, usando el
 * MISMO parcial que la ficha del lead — el historial del caso es la continuación del
 * comercial, no una lista aparte.
 *
 * @var array      $matter
 * @var array      $lead
 * @var array      $stages
 * @var array|null $stage
 * @var array      $timeline
 * @var bool       $canEdit
 */
$id      = (int) $matter['id'];
$cerrado = MatterModel::isClosed($matter);
$moneda  = (string) ($matter['currency'] ?: 'USD');
?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-[2fr_1fr]">
    <div class="flex flex-col gap-4">
        <?php // ── Los datos del caso, tal como se congelaron ──────────────────── ?>
        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <i class="icon icon-folder"></i>
                    <h6 class="font-semibold"><?= lang('Lfl.mtrTabSummary') ?></h6>
                </div>
            </div>
            <div class="card-body">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <?php
                    $filas = [
                        'mtrService'    => $matter['service_name'] ?: '—',
                        'mtrMatterType' => $matter['matter_type_name'] ?: '—',
                        'mtrLawyer'     => $matter['lawyer_name'] ?: '—',
                        'mtrAssigned'   => $matter['assigned_user_id'] ? ($team[(int) $matter['assigned_user_id']] ?? '—') : '—',
                        'mtrClient'     => MatterModel::clientName($matter),
                        'mtrOpenedAt'   => Meta::date($matter['opened_at']),
                    ];
                    if ($cerrado) {
                        $filas['mtrClosedAt']    = Meta::date($matter['closed_at']);
                        $filas['mtrCloseReason'] = $matter['close_reason']
                            ? lang('Lfl.' . (Meta::CLOSE_REASONS[$matter['close_reason']] ?? 'crOther'))
                            : '—';
                    }
                    foreach ($filas as $clave => $valor): ?>
                        <div>
                            <dt class="text-muted"><?= lang('Lfl.' . $clave) ?></dt>
                            <dd class="font-medium"><?= esc($valor) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <?php if ($cerrado && $matter['close_note']): ?>
                    <div class="form-section mt-4">
                        <h6 class="form-section-title"><?= lang('Lfl.mtrCloseNote') ?></h6>
                        <p class="text-sm"><?= nl2br(esc($matter['close_note'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($matter['summary']): ?>
                    <div class="form-section mt-4">
                        <h6 class="form-section-title"><?= lang('Lfl.mtrSummary') ?></h6>
                        <p class="text-sm"><?= nl2br(esc($matter['summary'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        /*
         * El estado del expediente, de `MatterSteps`: la MISMA fuente que consultan
         * las guardas de radicación y cierre. Si aquí aparece un aviso, es porque el
         * servicio lo tendría en cuenta; no es una lista decorativa aparte.
         *
         * Se pinta con `partials/_diagnostic`, el parcial que ya existe para el
         * diagnóstico de la conexión con Docketwise.
         */
        ?>
        <?php // El parcial ya trae su propia tarjeta con cabecera: no se envuelve. ?>
        <?= view('Components\LeadFlow\Views\partials\_diagnostic', [
            'diag' => \Components\LeadFlow\Libraries\MatterSteps::toDiagnostic($steps),
        ]) ?>

        <?php // ── Historial, recortado. Mismo parcial que la ficha del lead. ─── ?>
        <div class="card">
            <div class="card-header justify-between">
                <div class="flex items-center gap-2">
                    <i class="icon icon-clock-history"></i>
                    <h6 class="font-semibold"><?= lang('Lfl.mtrTabTimeline') ?></h6>
                </div>
                <a href="<?= base_url('leadflow/expedientes/ficha/' . $id . '?tab=timeline') ?>" class="btn btn-ghost btn-sm">
                    <?= lang('Lfl.viewAll') ?> <i class="icon icon-chevron-right"></i>
                </a>
            </div>
            <div class="card-body">
                <?= view('Components\LeadFlow\Views\leads\tabs\_timeline_list', ['timeline' => $timeline]) ?>
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-4">
        <?php // ── El dinero. Los totales los recalcula el servicio. ───────────── ?>
        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <i class="icon icon-cash-stack"></i>
                    <h6 class="font-semibold"><?= lang('Lfl.mtrFees') ?></h6>
                </div>
            </div>
            <div class="card-body">
                <dl class="flex flex-col gap-2 text-sm">
                    <?php foreach ([
                        'mtrFeeTotal'  => (float) $matter['fee_total'],
                        'mtrPaidTotal' => (float) $matter['paid_total'],
                        'mtrBalance'   => MatterModel::balance($matter),
                    ] as $clave => $valor): ?>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-muted"><?= lang('Lfl.' . $clave) ?></dt>
                            <dd class="font-mono font-semibold <?= $clave === 'mtrBalance' && $valor > 0 ? 'text-danger' : '' ?>">
                                <?= esc(Meta::money($valor, $moneda)) ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <p class="form-hint mt-2"><?= lang('Lfl.mtrDocs') ?>:
                    <strong><?= (int) $matter['docs_received'] ?> / <?= (int) $matter['docs_required'] ?></strong>
                </p>
            </div>
        </div>

        <?php // ── Cambiar etapa. Las terminales no están aquí: cerrar es su propio paso. ?>
        <?php if ($canEdit && ! $cerrado): ?>
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center gap-2">
                        <i class="icon icon-arrow-repeat"></i>
                        <h6 class="font-semibold"><?= lang('Lfl.mtrMoveStage') ?></h6>
                    </div>
                </div>
                <div class="card-body">
                    <form action="<?= base_url('leadflow/expedientes/etapa/' . $id) ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="leadflow/expedientes/ficha/<?= (int) $id ?>">
                        <div class="form-group">
                            <label for="stage_id" class="form-label"><?= lang('Lfl.mtrStage') ?></label>
                            <select id="stage_id" name="stage_id" class="form-select" required>
                                <?php foreach ($stages as $s): ?>
                                    <?php if (in_array($s['kind'], ['closed', 'cancelled'], true)) {
                                        continue;
                                    } ?>
                                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($matter['stage_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                        <?= esc($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="form-hint"><?= lang('Lfl.mtrStageHint') ?></p>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><?= lang('Lfl.save') ?></button>
                    </form>
                </div>
            </div>

            <?php // ── Cerrar: exige motivo, por eso es su propio formulario. ──── ?>
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center gap-2">
                        <i class="icon icon-check-circle"></i>
                        <h6 class="font-semibold"><?= lang('Lfl.mtrClose') ?></h6>
                    </div>
                </div>
                <div class="card-body">
                    <form action="<?= base_url('leadflow/expedientes/cerrar/' . $id) ?>" method="post"
                        onsubmit="return confirm('<?= esc(lang('Lfl.mtrConfirmClose'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="leadflow/expedientes/ficha/<?= (int) $id ?>">
                        <div class="form-group">
                            <label for="close_reason" class="form-label"><?= lang('Lfl.mtrCloseReason') ?></label>
                            <select id="close_reason" name="close_reason" class="form-select" required>
                                <?php foreach (Meta::CLOSE_REASONS as $valor => $clave): ?>
                                    <option value="<?= esc($valor, 'attr') ?>"><?= lang('Lfl.' . $clave) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="close_note" class="form-label"><?= lang('Lfl.mtrCloseNote') ?></label>
                            <input type="text" id="close_note" name="close_note" class="form-input" maxlength="255">
                            <p class="form-hint"><?= lang('Lfl.mtrCloseHint') ?></p>
                        </div>
                        <?php /* Primario y no `btn-danger`: cerrar no es destruir —se puede
                                 reabrir— y el rojo ya es del botón de borrar, que sí lo es.
                                 Dos rojos compitiendo enseñan a ignorar el color. */ ?>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="icon icon-check-circle"></i> <?= lang('Lfl.mtrClose') ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
