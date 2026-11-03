<?php
/** @var array $beneficiary  Valores actuales (vacío en creación) */
$beneficiary ??= [];
$val = static fn (string $k, $def = '') => old($k, $beneficiary[$k] ?? $def);
$idTypes      = ['NIT', 'CC', 'CE', 'Pasaporte', 'RUT', 'TI', 'Otro'];
$accountTypes = ['Ahorros', 'Corriente', 'Nómina', 'Fiduciaria', 'Otro'];
$isEdit       = !empty($beneficiary['id']);
?>
<?php if (session()->getFlashdata('errors')): ?>
    <div class="alert alert-danger">
        <ul class="list-inside list-disc">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <h6 class="mb-2 font-semibold text-muted">Identificación</h6>
    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
        <div class="form-group md:col-span-2">
            <label for="name" class="form-label">Nombre / Razón social *</label>
            <input type="text" class="form-input" id="name" name="name" value="<?= esc($val('name'), 'attr') ?>" required maxlength="200">
        </div>
        <div class="form-group">
            <label for="id_type" class="form-label">Tipo de documento</label>
            <select class="form-select" id="id_type" name="id_type">
                <?php foreach ($idTypes as $t): ?>
                    <option value="<?= $t ?>" <?= $val('id_type', 'NIT') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="id_number" class="form-label">Número</label>
            <input type="text" class="form-input" id="id_number" name="id_number" value="<?= esc($val('id_number'), 'attr') ?>" maxlength="50">
        </div>
    </div>

    <h6 class="mb-2 mt-2 font-semibold text-muted">Contacto</h6>
    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
        <div class="form-group">
            <label for="email" class="form-label">Correo</label>
            <input type="email" class="form-input" id="email" name="email" value="<?= esc($val('email'), 'attr') ?>" maxlength="190">
        </div>
        <div class="form-group">
            <label for="phone" class="form-label">Teléfono</label>
            <input type="text" class="form-input" id="phone" name="phone" value="<?= esc($val('phone'), 'attr') ?>" maxlength="50">
        </div>
        <div class="form-group">
            <label for="city" class="form-label">Ciudad</label>
            <input type="text" class="form-input" id="city" name="city" value="<?= esc($val('city'), 'attr') ?>" maxlength="100">
        </div>
    </div>
    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
        <div class="form-group">
            <label for="address" class="form-label">Dirección</label>
            <input type="text" class="form-input" id="address" name="address" value="<?= esc($val('address'), 'attr') ?>" maxlength="255">
        </div>
        <div class="form-group">
            <label for="contact_name" class="form-label">Persona de contacto</label>
            <input type="text" class="form-input" id="contact_name" name="contact_name" value="<?= esc($val('contact_name'), 'attr') ?>" maxlength="150">
        </div>
    </div>

    <h6 class="mb-2 mt-2 font-semibold text-muted">Datos bancarios</h6>
    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
        <div class="form-group">
            <label for="bank_name" class="form-label">Banco</label>
            <input type="text" class="form-input" id="bank_name" name="bank_name" value="<?= esc($val('bank_name'), 'attr') ?>" maxlength="120">
        </div>
        <div class="form-group">
            <label for="bank_account_type" class="form-label">Tipo de cuenta</label>
            <select class="form-select" id="bank_account_type" name="bank_account_type">
                <?php foreach ($accountTypes as $t): ?>
                    <option value="<?= $t ?>" <?= $val('bank_account_type', 'Ahorros') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="bank_account_number" class="form-label">Número de cuenta</label>
            <input type="text" class="form-input" id="bank_account_number" name="bank_account_number" value="<?= esc($val('bank_account_number'), 'attr') ?>" maxlength="50">
        </div>
    </div>

    <div class="form-group">
        <label for="notes" class="form-label">Notas internas</label>
        <textarea class="form-textarea" id="notes" name="notes" rows="2"><?= esc($val('notes')) ?></textarea>
    </div>

    <?php if (!$isEdit): ?>
        <h6 class="mb-2 mt-2 font-semibold text-muted">Documentos adjuntos</h6>
        <div class="form-group">
            <label for="documents" class="form-label">RUT, cédula u otros (PDF o imagen)</label>
            <input type="file" class="form-input" id="documents" name="documents[]" multiple accept="application/pdf,image/*">
            <small class="form-hint">Opcional. Puedes subir varios; máx. 5 MB c/u. Luego podrás gestionarlos y agregar más.</small>
        </div>
    <?php endif; ?>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar</button>
        <a href="<?= base_url('contabilidad/beneficiarios') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
    </div>
</form>
