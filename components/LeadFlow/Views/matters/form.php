<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php

/*
 * Edición del expediente.
 *
 * No se editan aquí el número, la etapa ni el estado: el número es inmutable y los
 * otros dos se cambian por sus propias acciones, que validan transiciones y piden
 * motivo. Un formulario que los dejara tocar a mano saltaría esas reglas.
 *
 * Cambiar el abogado responsable SÍ reescribe `lawyer_name` del snapshot: si no, el
 * expediente seguiría imprimiendo el nombre del anterior.
 *
 * @var array $matter
 * @var array $lead
 * @var array $team
 * @var array $services
 */
$id = (int) $matter['id'];
?>
<div class="card">
    <div class="card-header">
        <div class="flex items-center gap-2">
            <i class="icon icon-pencil"></i>
            <h5 class="font-semibold"><?= lang('Lfl.mtrEditTitle') ?></h5>
            <code class="badge badge-light font-mono"><?= esc($matter['number']) ?></code>
        </div>
    </div>
    <div class="card-body">
        <?= view('Components\LeadFlow\Views\partials\_errors') ?>

        <form action="<?= base_url('leadflow/expedientes/editar/' . $id) ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-section">
                <h6 class="form-section-title"><?= lang('Lfl.mtrTitleField') ?></h6>

                <div class="form-group">
                    <label for="title" class="form-label"><?= lang('Lfl.mtrTitleField') ?></label>
                    <input type="text" id="title" name="title" class="form-input" maxlength="160"
                        value="<?= esc($matter['title'] ?? '', 'attr') ?>">
                </div>

                <div class="form-group">
                    <label for="service_id" class="form-label"><?= lang('Lfl.mtrService') ?></label>
                    <select id="service_id" name="service_id" class="form-select">
                        <option value=""><?= lang('Lfl.noService') ?></option>
                        <?php foreach ($services as $sid => $nombre): ?>
                            <option value="<?= (int) $sid ?>" <?= (int) ($matter['service_id'] ?? 0) === (int) $sid ? 'selected' : '' ?>>
                                <?= esc($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="lawyer_user_id" class="form-label"><?= lang('Lfl.mtrLawyer') ?></label>
                    <select id="lawyer_user_id" name="lawyer_user_id" class="form-select" required>
                        <?php foreach ($team as $uid => $nombre): ?>
                            <option value="<?= (int) $uid ?>" <?= (int) ($matter['lawyer_user_id'] ?? 0) === (int) $uid ? 'selected' : '' ?>>
                                <?= esc($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assigned_user_id" class="form-label"><?= lang('Lfl.mtrAssigned') ?></label>
                    <select id="assigned_user_id" name="assigned_user_id" class="form-select">
                        <option value=""><?= lang('Lfl.unassigned') ?></option>
                        <?php foreach ($team as $uid => $nombre): ?>
                            <option value="<?= (int) $uid ?>" <?= (int) ($matter['assigned_user_id'] ?? 0) === (int) $uid ? 'selected' : '' ?>>
                                <?= esc($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="summary" class="form-label"><?= lang('Lfl.mtrSummary') ?></label>
                    <textarea id="summary" name="summary" class="form-input" rows="5"><?= esc($matter['summary'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> <?= lang('Lfl.save') ?></button>
                <a href="<?= base_url('leadflow/expedientes/ficha/' . $id) ?>" class="btn btn-secondary"><?= lang('Lfl.cancel') ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
