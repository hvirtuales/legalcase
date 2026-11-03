<?= $this->extend('Components\Contabilidad\Views\public\_layout') ?>
<?= $this->section('content') ?>
<?php $old = static fn ($k, $d = '') => old($k, $d); ?>
<form action="<?= base_url('contabilidad/solicitud/' . $company['id']) ?>" method="post" id="prForm" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card mb-4">
        <div class="card-header"><i class="icon icon-person-badge"></i><h5 class="font-semibold">Datos del colaborador</h5></div>
        <div class="card-body">
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                <div class="form-group">
                    <label class="form-label">Tipo doc.</label>
                    <select name="collaborator_id_type" class="form-select">
                        <?php foreach ($idTypes as $t): ?><option value="<?= $t ?>" <?= $old('collaborator_id_type', 'CC') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Número *</label>
                    <input type="text" name="collaborator_id" class="form-input" value="<?= esc($old('collaborator_id'), 'attr') ?>" required>
                </div>
                <div class="form-group md:col-span-2">
                    <label class="form-label">Nombre completo / Razón social *</label>
                    <input type="text" name="collaborator_name" class="form-input" value="<?= esc($old('collaborator_name'), 'attr') ?>" required>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                <div class="form-group"><label class="form-label">Correo</label><input type="email" name="collaborator_email" class="form-input" value="<?= esc($old('collaborator_email'), 'attr') ?>"></div>
                <div class="form-group"><label class="form-label">Teléfono</label><input type="text" name="collaborator_phone" class="form-input" value="<?= esc($old('collaborator_phone'), 'attr') ?>"></div>
                <div class="form-group"><label class="form-label">Ciudad</label><input type="text" name="collaborator_city" class="form-input" value="<?= esc($old('collaborator_city'), 'attr') ?>"></div>
            </div>
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                <div class="form-group"><label class="form-label">Dirección</label><input type="text" name="collaborator_address" class="form-input" value="<?= esc($old('collaborator_address'), 'attr') ?>"></div>
                <div class="form-group"><label class="form-label">Persona de contacto</label><input type="text" name="collaborator_contact" class="form-input" value="<?= esc($old('collaborator_contact'), 'attr') ?>"></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="icon icon-bank"></i><h5 class="font-semibold">Datos bancarios</h5></div>
        <div class="card-body">
            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                <div class="form-group"><label class="form-label">Banco</label><input type="text" name="collaborator_bank" class="form-input" value="<?= esc($old('collaborator_bank'), 'attr') ?>"></div>
                <div class="form-group">
                    <label class="form-label">Tipo de cuenta</label>
                    <select name="collaborator_account_type" class="form-select">
                        <?php foreach ($accountTypes as $t): ?><option value="<?= $t ?>" <?= $old('collaborator_account_type', 'Ahorros') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Número de cuenta</label><input type="text" name="collaborator_account" class="form-input" value="<?= esc($old('collaborator_account'), 'attr') ?>"></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header justify-between">
            <div class="flex items-center gap-2"><i class="icon icon-receipt"></i><h5 class="font-semibold">Conceptos</h5></div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addItem()"><i class="icon icon-plus-circle"></i> Agregar</button>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Periodo (opcional)</label>
                <input type="text" name="period" class="form-input md:w-1/3" value="<?= esc($old('period'), 'attr') ?>" placeholder="Ej: Julio 2026">
            </div>
            <div class="overflow-x-auto">
                <table class="table" id="itemsTable">
                    <thead><tr>
                        <th style="min-width:220px">Descripción</th>
                        <th class="text-right" style="width:140px">Valor</th>
                        <th class="text-right" style="width:90px">IVA %</th>
                        <th class="text-right" style="width:130px">Subtotal</th>
                        <th style="width:40px"></th>
                    </tr></thead>
                    <tbody id="itemsBody"></tbody>
                </table>
            </div>
            <div class="mt-2 flex justify-end">
                <table class="text-sm">
                    <tr><td class="pr-6 text-muted">Subtotal</td><td class="text-right font-mono" id="tSub">0.00</td></tr>
                    <tr><td class="pr-6 text-muted">IVA</td><td class="text-right font-mono" id="tIva">0.00</td></tr>
                    <tr class="border-t border-line"><td class="pr-6 font-semibold">Total</td><td class="text-right font-mono text-lg font-bold" id="tTotal">0.00</td></tr>
                </table>
            </div>
            <p class="mt-1 text-xs text-muted">La retención la define la empresa al revisar tu solicitud.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="icon icon-paperclip"></i><h5 class="font-semibold">Soportes (opcional)</h5></div>
        <div class="card-body">
            <input type="file" name="files[]" multiple class="form-input">
            <p class="mt-1 text-xs text-muted">PDF o imágenes de respaldo. Máx. 5 MB c/u.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="icon icon-shield-check"></i><h5 class="font-semibold">Certificaciones y firma</h5></div>
        <div class="card-body">
            <label class="form-check" for="cert_no_iva"><input class="form-check-input" type="checkbox" id="cert_no_iva" name="cert_no_iva" value="1"><span class="form-check-label">Declaro que <strong>no soy responsable de IVA</strong>.</span></label>
            <label class="form-check mb-3" for="cert_art383"><input class="form-check-input" type="checkbox" id="cert_art383" name="cert_art383" value="1"><span class="form-check-label">Solicito depuración de la base (<strong>Art. 383 E.T.</strong>).</span></label>

            <label class="form-label">Firma</label>
            <div class="rounded-lg border border-line bg-surface" style="touch-action:none">
                <canvas id="sig" width="600" height="180" class="w-full" style="max-width:100%;height:180px"></canvas>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-ghost btn-sm" onclick="clearSig()"><i class="icon icon-x-lg"></i> Limpiar</button>
                <label class="btn btn-secondary btn-sm mb-0"><i class="icon icon-upload"></i> Importar imagen
                    <input type="file" accept="image/*" class="hidden" onchange="importSig(event)">
                </label>
                <span class="text-xs text-muted">Dibuja con el mouse/dedo o importa una imagen.</span>
            </div>
            <input type="hidden" name="signature_data" id="signature_data">
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg"><i class="icon icon-check-circle"></i> Enviar cuenta de cobro</button>
</form>

<script>
    (function () {
        var idx = 0, body = document.getElementById('itemsBody');
        var fmt = function (n) { return (Math.round(n * 100) / 100).toFixed(2); };

        window.addItem = function (d) {
            d = d || {}; var i = idx++;
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" class="form-input" name="items[' + i + '][description]" value="' + (d.description || '').replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="number" step="0.01" class="form-input text-right it-amount" name="items[' + i + '][amount]" value="' + (d.amount || '') + '"></td>' +
                '<td><input type="number" step="0.01" class="form-input text-right it-iva" name="items[' + i + '][iva_rate]" value="' + (d.iva_rate || 0) + '"></td>' +
                '<td class="text-right font-mono it-sub">0.00</td>' +
                '<td><button type="button" class="btn btn-ghost btn-sm text-danger" onclick="this.closest(\'tr\').remove();recalc();"><i class="icon icon-trash"></i></button></td>';
            body.appendChild(tr);
            tr.querySelectorAll('input').forEach(function (x) { x.addEventListener('input', recalc); });
            recalc();
        };
        window.recalc = function () {
            var s = 0, v = 0;
            body.querySelectorAll('tr').forEach(function (tr) {
                var a = parseFloat(tr.querySelector('.it-amount').value) || 0;
                var ir = parseFloat(tr.querySelector('.it-iva').value) || 0;
                var ia = Math.round(a * ir) / 100;
                s += a; v += ia;
                tr.querySelector('.it-sub').textContent = fmt(a + ia);
            });
            document.getElementById('tSub').textContent = fmt(s);
            document.getElementById('tIva').textContent = fmt(v);
            document.getElementById('tTotal').textContent = fmt(s + v);
        };
        addItem();

        // Firma
        var cv = document.getElementById('sig'), ctx = cv.getContext('2d'), drawing = false, dirty = false;
        ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#111';
        function pos(e) { var r = cv.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return { x: (t.clientX - r.left) * (cv.width / r.width), y: (t.clientY - r.top) * (cv.height / r.height) }; }
        function start(e) { drawing = true; dirty = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
        function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
        function end() { drawing = false; }
        cv.addEventListener('mousedown', start); cv.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
        cv.addEventListener('touchstart', start); cv.addEventListener('touchmove', move); cv.addEventListener('touchend', end);
        window.clearSig = function () { ctx.clearRect(0, 0, cv.width, cv.height); dirty = false; };
        window.importSig = function (ev) {
            var f = ev.target.files[0]; if (!f) return;
            var rd = new FileReader();
            rd.onload = function (e) { var img = new Image(); img.onload = function () { ctx.clearRect(0, 0, cv.width, cv.height); ctx.drawImage(img, 0, 0, cv.width, cv.height); dirty = true; }; img.src = e.target.result; };
            rd.readAsDataURL(f);
        };
        document.getElementById('prForm').addEventListener('submit', function () {
            document.getElementById('signature_data').value = dirty ? cv.toDataURL('image/png') : '';
        });
    })();
</script>
<?= $this->endSection() ?>
