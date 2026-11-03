<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$voucher = $voucher ?? [];
$isEdit  = !empty($voucher['id']);
$val     = static fn (string $k, $def = '') => old($k, $voucher[$k] ?? $def);
$action  = $isEdit ? base_url('contabilidad/comprobantes/editar/' . $voucher['id']) : base_url('contabilidad/comprobantes/nuevo');
?>

<div class="card">
    <div class="card-header">
        <i class="icon icon-receipt"></i>
        <h5 class="font-semibold"><?= $isEdit ? 'Editar comprobante ' . esc($voucher['number']) : 'Nuevo comprobante' ?></h5>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><i class="icon icon-exclamation-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger"><ul class="list-inside list-disc">
                <?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form action="<?= esc($action, 'attr') ?>" method="post" id="voucherForm">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-4">
                <div class="form-group">
                    <label for="type_id" class="form-label">Tipo *</label>
                    <select class="form-select" id="type_id" name="type_id" <?= $isEdit ? 'disabled' : '' ?> required>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= (string) $val('type_id') === (string) $t['id'] ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit): ?><input type="hidden" name="type_id" value="<?= esc($voucher['type_id'], 'attr') ?>"><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="date" class="form-label">Fecha *</label>
                    <input type="date" class="form-input" id="date" name="date" value="<?= esc($val('date', date('Y-m-d')), 'attr') ?>" required>
                </div>
                <div class="form-group">
                    <label for="payment_method" class="form-label">Medio de pago</label>
                    <select class="form-select" id="payment_method" name="payment_method">
                        <?php foreach ($paymentMethods as $pm): ?>
                            <option value="<?= esc($pm, 'attr') ?>" <?= $val('payment_method', 'Efectivo') === $pm ? 'selected' : '' ?>><?= esc($pm) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="source" class="form-label">Cuenta / Caja (origen)</label>
                    <?php $curSource = old('source', (!empty($voucher['source_type']) && !empty($voucher['source_id'])) ? $voucher['source_type'] . ':' . $voucher['source_id'] : ''); ?>
                    <select class="form-select" id="source" name="source">
                        <option value="">— Ninguna —</option>
                        <?php if (!empty($accounts)): ?>
                            <optgroup label="Cuentas bancarias">
                                <?php foreach ($accounts as $a): ?>
                                    <option value="bank:<?= (int) $a['id'] ?>" data-label="<?= esc(trim($a['bank_name'] . ' ' . ($a['account_number'] ?? '')), 'attr') ?>" <?= $curSource === 'bank:' . $a['id'] ? 'selected' : '' ?>><?= esc($a['bank_name'] . ' — ' . ($a['account_number'] ?: $a['account_type'])) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($cashBoxes)): ?>
                            <optgroup label="Cajas menores">
                                <?php foreach ($cashBoxes as $b): ?>
                                    <option value="cash:<?= (int) $b['id'] ?>" data-label="Caja: <?= esc($b['name'], 'attr') ?>" <?= $curSource === 'cash:' . $b['id'] ? 'selected' : '' ?>><?= esc($b['name']) ?> (saldo <?= number_format((float) $b['balance'], 2) ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <small class="form-hint">En un Egreso desde una caja se valida el saldo y se registra el movimiento.</small>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                <div class="form-group">
                    <label for="benef_select" class="form-label">Beneficiario (directorio)</label>
                    <select class="form-select" id="benef_select">
                        <option value="">— Seleccionar / escribir manual —</option>
                        <?php foreach ($beneficiaries as $b): ?>
                            <option value="<?= $b['id'] ?>" data-name="<?= esc($b['name'], 'attr') ?>" data-doc="<?= esc($b['id_number'], 'attr') ?>">
                                <?= esc($b['name']) ?> (<?= esc($b['id_type'] . ' ' . $b['id_number']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="beneficiary_name" class="form-label">Nombre del beneficiario</label>
                    <input type="text" class="form-input" id="beneficiary_name" name="beneficiary_name" value="<?= esc($val('beneficiary_name'), 'attr') ?>" maxlength="200">
                </div>
                <div class="form-group">
                    <label for="beneficiary_id" class="form-label">Documento</label>
                    <input type="text" class="form-input" id="beneficiary_id" name="beneficiary_id" value="<?= esc($val('beneficiary_id'), 'attr') ?>" maxlength="50">
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Concepto general</label>
                <textarea class="form-textarea" id="description" name="description" rows="2"><?= esc($val('description')) ?></textarea>
            </div>

            <!-- Conceptos / ítems -->
            <div class="mb-2 mt-2 flex items-center justify-between">
                <h6 class="font-semibold text-muted">Conceptos y valores</h6>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addItem()"><i class="icon icon-plus-circle"></i> Agregar concepto</button>
            </div>
            <div class="overflow-x-auto">
                <table class="table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="min-width:200px">Descripción</th>
                            <th class="text-right" style="width:130px">Valor base</th>
                            <th class="text-right" style="width:85px">IVA %</th>
                            <th class="text-right" style="width:85px">Rte %</th>
                            <?php if (!empty($otherTaxes)): ?><th style="min-width:150px">Otros imp.</th><?php endif; ?>
                            <th class="text-right" style="width:130px">Neto</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                </table>
            </div>
            <datalist id="ivaRates"><?php foreach ($ivaRates as $t): ?><option value="<?= (float) $t['rate'] ?>"><?= esc($t['name']) ?></option><?php endforeach; ?></datalist>
            <datalist id="retRates"><?php foreach ($retRates as $t): ?><option value="<?= (float) $t['rate'] ?>"><?= esc($t['name']) ?></option><?php endforeach; ?></datalist>

            <!-- Totales -->
            <div class="mt-2 flex justify-end">
                <table class="text-sm">
                    <tr><td class="pr-6 text-muted">Subtotal</td><td class="text-right font-mono" id="tSubtotal">0.00</td></tr>
                    <tr><td class="pr-6 text-muted">IVA</td><td class="text-right font-mono" id="tIva">0.00</td></tr>
                    <tr><td class="pr-6 text-muted">Retención</td><td class="text-right font-mono">-<span id="tRet">0.00</span></td></tr>
                    <tr><td class="pr-6 text-muted">Otros impuestos</td><td class="text-right font-mono" id="tOther">0.00</td></tr>
                    <tr class="border-t border-line"><td class="pr-6 font-semibold">Total</td><td class="text-right font-mono text-lg font-bold" id="tTotal">0.00</td></tr>
                </table>
            </div>

            <!-- Asiento contable (editable) -->
            <div class="mb-2 mt-4 flex flex-wrap items-center justify-between gap-2">
                <h6 class="font-semibold text-muted">Asiento contable (partida doble)</h6>
                <div class="flex gap-1">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="autogen()"><i class="icon icon-arrow-repeat"></i> Auto-generar</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="clearEntries();recalcBalance()">Limpiar</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="addEntry()"><i class="icon icon-plus-circle"></i> Fila</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table" id="entriesTable">
                    <thead><tr>
                        <th style="width:110px">Cuenta</th>
                        <th style="min-width:170px">Nombre</th>
                        <th class="text-right" style="width:130px">Débito</th>
                        <th class="text-right" style="width:130px">Crédito</th>
                        <th style="min-width:120px">Descripción</th>
                        <th style="width:40px"></th>
                    </tr></thead>
                    <tbody id="entriesBody"></tbody>
                </table>
            </div>
            <datalist id="acctList"><?php foreach ($accountMap as $code => $name): ?><option value="<?= esc($code, 'attr') ?>"><?= esc($name) ?></option><?php endforeach; ?></datalist>
            <div class="mt-1 flex flex-wrap justify-end gap-4 text-sm">
                <span class="text-muted">Débito: <strong class="font-mono" id="eDebe">0.00</strong></span>
                <span class="text-muted">Crédito: <strong class="font-mono" id="eHaber">0.00</strong></span>
                <span id="eBalance" class="font-semibold"></span>
            </div>
            <p class="mt-1 text-xs text-muted"><i class="icon icon-info-circle"></i> Edítalo a mano o usa «Auto-generar». Si lo dejas vacío, se genera automáticamente al guardar. Debe cuadrar (Débito = Crédito).</p>

            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i> Guardar comprobante</button>
                <a href="<?= base_url('contabilidad/comprobantes') ?>" class="btn btn-secondary"><i class="icon icon-x-circle"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var idx = 0;
        var body = document.getElementById('itemsBody');
        var otherTaxes = <?= json_encode(array_map(static fn ($t) => ['id' => (int) $t['id'], 'name' => $t['name'], 'rate' => (float) $t['rate'], 'add' => (int) $t['is_additive']], $otherTaxes ?? [])) ?>;
        var fmt = function (n) { return (Math.round(n * 100) / 100).toFixed(2); };

        window.addItem = function (data) {
            data = data || {};
            var i = idx++;
            var sel = data.other_taxes || [];
            var otherCell = '';
            if (otherTaxes.length) {
                otherTaxes.forEach(function (ot) {
                    var ck = sel.indexOf(ot.id) !== -1 ? 'checked' : '';
                    otherCell += '<label class="mr-2 inline-flex items-center gap-1 text-xs"><input type="checkbox" class="it-other" name="items[' + i + '][other_taxes][]" value="' + ot.id + '" data-rate="' + ot.rate + '" data-add="' + ot.add + '" ' + ck + '> ' + ot.name + '</label>';
                });
            }
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" class="form-input" name="items[' + i + '][description]" value="' + (data.description || '').replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="number" step="0.01" class="form-input text-right it-amount" name="items[' + i + '][amount]" value="' + (data.amount || '') + '"></td>' +
                '<td><input type="number" step="0.01" class="form-input text-right it-iva" list="ivaRates" name="items[' + i + '][iva_rate]" value="' + (data.iva_rate || 0) + '"></td>' +
                '<td><input type="number" step="0.01" class="form-input text-right it-ret" list="retRates" name="items[' + i + '][retencion_rate]" value="' + (data.retencion_rate || 0) + '"></td>' +
                (otherTaxes.length ? '<td>' + otherCell + '</td>' : '') +
                '<td class="text-right font-mono it-net">0.00</td>' +
                '<td><button type="button" class="btn btn-ghost btn-sm text-danger" onclick="this.closest(\'tr\').remove();recalc();"><i class="icon icon-trash"></i></button></td>';
            body.appendChild(tr);
            tr.querySelectorAll('input').forEach(function (inp) { inp.addEventListener('input', recalc); inp.addEventListener('change', recalc); });
            recalc();
        };

        var T = { sub: 0, iva: 0, ret: 0, oAdd: 0, oSub: 0, total: 0 };
        window.recalc = function () {
            var sub = 0, iva = 0, ret = 0, oAdd = 0, oSub = 0;
            body.querySelectorAll('tr').forEach(function (tr) {
                var a = parseFloat(tr.querySelector('.it-amount').value) || 0;
                var ir = parseFloat(tr.querySelector('.it-iva').value) || 0;
                var rr = parseFloat(tr.querySelector('.it-ret').value) || 0;
                var ia = Math.round(a * ir) / 100, ra = Math.round(a * rr) / 100;
                var ro = 0;
                tr.querySelectorAll('.it-other:checked').forEach(function (cb) {
                    var amt = Math.round(a * parseFloat(cb.getAttribute('data-rate'))) / 100;
                    if (cb.getAttribute('data-add') === '1') { oAdd += amt; ro += amt; } else { oSub += amt; ro -= amt; }
                });
                sub += a; iva += ia; ret += ra;
                tr.querySelector('.it-net').textContent = fmt(a + ia - ra + ro);
            });
            var oth = oAdd - oSub;
            T = { sub: sub, iva: iva, ret: ret, oAdd: oAdd, oSub: oSub, total: sub + iva - ret + oth };
            document.getElementById('tSubtotal').textContent = fmt(sub);
            document.getElementById('tIva').textContent = fmt(iva);
            document.getElementById('tRet').textContent = fmt(ret);
            document.getElementById('tOther').textContent = fmt(oth);
            document.getElementById('tTotal').textContent = fmt(T.total);
        };

        var bs = document.getElementById('benef_select');
        if (bs) bs.addEventListener('change', function () {
            var o = this.options[this.selectedIndex];
            if (o.value) {
                document.getElementById('beneficiary_name').value = o.getAttribute('data-name') || '';
                document.getElementById('beneficiary_id').value = o.getAttribute('data-doc') || '';
            }
        });

        var initial = <?= json_encode(array_map(static fn ($it) => [
            'description'    => $it['description'],
            'amount'         => (float) $it['amount'],
            'iva_rate'       => (float) $it['iva_rate'],
            'retencion_rate' => (float) $it['retencion_rate'],
            'other_taxes'    => $it['other_taxes'] ?? [],
        ], $items ?? [])) ?>;
        if (initial.length) { initial.forEach(function (d) { addItem(d); }); } else { addItem(); }

        // ---- Asiento contable (editable) ----
        var ACCOUNTS = <?= json_encode($accountMap ?? []) ?>;
        var PAY_ACCT = <?= json_encode($paymentMap ?? []) ?>;
        var eidx = 0;
        var ebody = document.getElementById('entriesBody');
        var round2 = function (n) { return Math.round(n * 100) / 100; };

        window.addEntry = function (d) {
            d = d || {}; var i = eidx++;
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" class="form-input font-mono en-code" list="acctList" name="entries[' + i + '][account_code]" value="' + (d.account_code || '') + '"></td>' +
                '<td><input type="text" class="form-input en-name" name="entries[' + i + '][account_name]" value="' + String(d.account_name || '').replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="number" step="0.01" class="form-input text-right en-debit" name="entries[' + i + '][debit]" value="' + (d.debit || '') + '"></td>' +
                '<td><input type="number" step="0.01" class="form-input text-right en-credit" name="entries[' + i + '][credit]" value="' + (d.credit || '') + '"></td>' +
                '<td><input type="text" class="form-input" name="entries[' + i + '][description]" value="' + String(d.description || '').replace(/"/g, '&quot;') + '"></td>' +
                '<td><button type="button" class="btn btn-ghost btn-sm text-danger" onclick="this.closest(\'tr\').remove();recalcBalance();"><i class="icon icon-trash"></i></button></td>';
            ebody.appendChild(tr);
            tr.querySelector('.en-code').addEventListener('input', function () { if (ACCOUNTS[this.value]) tr.querySelector('.en-name').value = ACCOUNTS[this.value]; });
            tr.querySelectorAll('input').forEach(function (x) { x.addEventListener('input', recalcBalance); });
        };
        window.clearEntries = function () { ebody.innerHTML = ''; };
        window.recalcBalance = function () {
            var d = 0, c = 0;
            ebody.querySelectorAll('tr').forEach(function (tr) {
                d += parseFloat(tr.querySelector('.en-debit').value) || 0;
                c += parseFloat(tr.querySelector('.en-credit').value) || 0;
            });
            document.getElementById('eDebe').textContent = fmt(d);
            document.getElementById('eHaber').textContent = fmt(c);
            var el = document.getElementById('eBalance');
            if (ebody.children.length === 0) { el.textContent = ''; el.className = 'font-semibold'; return; }
            if (round2(d) === round2(c)) { el.textContent = '✔ Cuadra'; el.className = 'font-semibold text-success'; }
            else { el.textContent = '✗ Descuadra ' + fmt(d - c); el.className = 'font-semibold text-danger'; }
        };
        window.autogen = function () {
            recalc();
            var typeSel = document.getElementById('type_id');
            var typeName = (typeSel.options[typeSel.selectedIndex] || {}).text || '';
            var pay = PAY_ACCT[document.getElementById('payment_method').value] || '1110';
            var payName = null;
            // El origen (cuenta/caja) manda sobre el medio de pago en la línea Caja/Banco
            var srcEl = document.getElementById('source');
            if (srcEl && srcEl.value) {
                var opt = srcEl.options[srcEl.selectedIndex];
                var lbl = (opt && opt.getAttribute('data-label')) || (opt ? opt.text : '');
                if (srcEl.value.indexOf('cash:') === 0) { pay = '1105'; payName = lbl; }
                else if (srcEl.value.indexOf('bank:') === 0) { pay = '1110'; payName = (ACCOUNTS['1110'] || 'Bancos') + ' — ' + lbl; }
            }
            var rows = [];
            function add(code, debit, credit, desc, nameOverride) {
                if (round2(debit) === 0 && round2(credit) === 0) return;
                rows.push({ account_code: code, account_name: nameOverride || ACCOUNTS[code] || code, debit: round2(debit) || '', credit: round2(credit) || '', description: desc });
            }
            if (typeName.trim().toLowerCase() === 'ingreso') {
                add(pay, T.total, 0, 'Recaudo', payName); add('136005', T.ret, 0, 'Retención en la fuente'); add('1355', T.oSub, 0, 'Otras retenciones/anticipos');
                add('4145', 0, T.sub, 'Ingreso'); add('240805', 0, T.iva, 'IVA generado'); add('2368', 0, T.oAdd, 'Otros impuestos por pagar');
            } else {
                add('5110', T.sub, 0, 'Gasto'); add('240810', T.iva, 0, 'IVA descontable'); add('5115', T.oAdd, 0, 'Otros impuestos');
                add(pay, 0, T.total, 'Pago', payName); add('236515', 0, T.ret, 'Retención practicada'); add('2368', 0, T.oSub, 'Otras retenciones por pagar');
            }
            clearEntries(); rows.forEach(function (r) { addEntry(r); }); recalcBalance();
        };

        var initEntries = <?= json_encode(array_values(old('entries') ?: array_map(static fn ($e) => [
            'account_code' => $e['account_code'], 'account_name' => $e['account_name'],
            'debit'        => (float) $e['debit'] ?: '', 'credit' => (float) $e['credit'] ?: '', 'description' => $e['description'],
        ], $entries ?? []))) ?>;
        if (initEntries.length) { initEntries.forEach(function (e) { addEntry(e); }); }
        recalcBalance();
    })();
</script>
<?= $this->endSection() ?>
