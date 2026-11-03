<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Contabilidad\Libraries\AttachmentService;
use Components\Contabilidad\Libraries\CashBoxService;
use Components\Contabilidad\Libraries\PdfService;
use Components\Contabilidad\Libraries\PlanGuard;
use Components\Contabilidad\Libraries\QrService;
use Components\Contabilidad\Libraries\VoucherService;
use Components\Contabilidad\Models\BeneficiaryModel;
use Components\Contabilidad\Models\CashBoxModel;
use Components\Contabilidad\Models\CompanyAccountModel;
use Components\Contabilidad\Models\TaxConfigModel;
use Components\Contabilidad\Models\VoucherEntryModel;
use Components\Contabilidad\Models\VoucherItemModel;
use Components\Contabilidad\Models\VoucherItemTaxModel;
use Components\Contabilidad\Models\VoucherModel;
use Components\Contabilidad\Models\VoucherTypeModel;

class Vouchers extends BaseController
{
    private VoucherModel $vouchers;
    private VoucherService $svc;

    public function __construct()
    {
        $this->vouchers = new VoucherModel();
        $this->svc      = new VoucherService();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** Ítems del comprobante con sus "otros impuestos" adjuntos. */
    private function itemsWithTaxes(int $voucherId): array
    {
        $items = (new VoucherItemModel())->where('voucher_id', $voucherId)->orderBy('sort_order', 'asc')->findAll();
        $tm    = new VoucherItemTaxModel();
        foreach ($items as &$it) {
            $it['taxes'] = $tm->where('item_id', $it['id'])->findAll();
        }
        unset($it);

        return $items;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Contabilidad\Views\vouchers\index', ['title' => 'Comprobantes', 'dt' => null]);
        }
        $this->svc->ensureTypes($companyId);

        $req    = $this->request;
        $typeId = (int) $req->getGet('type_id');
        $status = (string) $req->getGet('status');
        $from   = (string) $req->getGet('from');
        $to     = (string) $req->getGet('to');

        $dt = (new DataTable($this->vouchers, $req))
            ->searchable(['number', 'beneficiary_name', 'beneficiary_id'])
            ->sortable(['number', 'date', 'total_amount', 'status'])
            ->defaultSort('date', 'desc')
            ->query(function ($m) use ($companyId, $typeId, $status, $from, $to) {
                $m->select('cmp_cont_vouchers.*, t.name AS type_name')
                    ->join('cmp_cont_voucher_types t', 't.id = cmp_cont_vouchers.type_id', 'left')
                    ->where('cmp_cont_vouchers.company_id', $companyId);
                if ($typeId) {
                    $m->where('cmp_cont_vouchers.type_id', $typeId);
                }
                if (in_array($status, ['emitido', 'pagado', 'anulado'], true)) {
                    $m->where('cmp_cont_vouchers.status', $status);
                }
                if ($from !== '') {
                    $m->where('cmp_cont_vouchers.date >=', $from);
                }
                if ($to !== '') {
                    $m->where('cmp_cont_vouchers.date <=', $to);
                }
            })
            ->paginate(15);

        return view('Components\Contabilidad\Views\vouchers\index', [
            'title'       => 'Comprobantes',
            'dt'          => $dt,
            'types'       => (new VoucherTypeModel())->where('company_id', $companyId)->findAll(),
            'balance'     => $this->svc->companyBalance($companyId),
            'extraParams' => array_filter(['type_id' => $typeId ?: null, 'status' => $status ?: null, 'from' => $from ?: null, 'to' => $to ?: null]),
            'quota'          => (new PlanGuard())->vouchersLeft($companyId),
            'serviceEnabled' => (new PlanGuard())->serviceEnabled($companyId),
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('contabilidad/comprobantes')->with('error', 'Selecciona una empresa activa.');
        }
        $this->svc->ensureTypes($companyId);

        if ($this->request->getMethod() === 'POST') {
            return $this->save(null, $companyId);
        }

        return view('Components\Contabilidad\Views\vouchers\form', $this->formData($companyId, null));
    }

    public function edit($id)
    {
        $companyId = $this->companyId();
        $v         = $this->vouchers->find($id);
        if (!$v || (int) $v['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/comprobantes')->with('error', 'Comprobante no encontrado.');
        }
        if ($v['status'] !== 'emitido') {
            return redirect()->to('contabilidad/comprobantes/' . $id)->with('error', 'No se puede editar un comprobante ' . $v['status'] . '.');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->save((int) $id, $companyId);
        }

        $data   = $this->formData($companyId, $v);
        $items  = (new VoucherItemModel())->where('voucher_id', $id)->orderBy('sort_order', 'asc')->findAll();
        $itaxes = new VoucherItemTaxModel();
        foreach ($items as &$it) {
            $it['other_taxes'] = array_map('intval', array_column($itaxes->where('item_id', $it['id'])->findAll(), 'tax_config_id'));
        }
        unset($it);
        $data['items']   = $items;
        $data['entries'] = (new VoucherEntryModel())->where('voucher_id', $id)->orderBy('sort_order', 'asc')->findAll();

        return view('Components\Contabilidad\Views\vouchers\form', $data);
    }

    private function save(?int $id, int $companyId)
    {
        $typeId = (int) $this->request->getPost('type_id');
        $type   = (new VoucherTypeModel())->where('company_id', $companyId)->find($typeId);
        if (!$type) {
            return redirect()->back()->withInput()->with('error', 'Tipo de comprobante inválido.');
        }

        // Límites del plan (solo al crear)
        if ($id === null) {
            $guard = new PlanGuard();
            if (!$guard->serviceEnabled($companyId)) {
                return redirect()->to('contabilidad/comprobantes')->with('error', 'El plan de la empresa no incluye el servicio de contabilidad.');
            }
            $left = $guard->vouchersLeft($companyId);
            if ($left !== null && $left <= 0) {
                return redirect()->to('contabilidad/comprobantes')->with('error', 'Alcanzaste el límite de comprobantes de tu plan.');
            }
        }

        $otherDefs = [];
        foreach ((new TaxConfigModel())->where('company_id', $companyId)->where('category', 'other')->where('is_active', 1)->findAll() as $t) {
            $otherDefs[(int) $t['id']] = ['name' => $t['name'], 'rate' => (float) $t['rate'], 'is_additive' => (int) $t['is_additive']];
        }
        $calc = $this->svc->computeTotals($this->request->getPost('items') ?? [], $otherDefs);
        if (empty($calc['items'])) {
            return redirect()->back()->withInput()->with('error', 'Agrega al menos un concepto con valor.');
        }

        // Origen del pago: cuenta bancaria (bank:<id>) o caja menor (cash:<id>)
        $sourceType  = null;
        $sourceId    = null;
        $sourceLabel = null;
        if (preg_match('/^(bank|cash):(\d+)$/', (string) $this->request->getPost('source'), $mm)) {
            if ($mm[1] === 'bank') {
                $acc = (new CompanyAccountModel())->find((int) $mm[2]);
                if ($acc && (int) $acc['company_id'] === $companyId) {
                    $sourceType  = 'bank';
                    $sourceId    = (int) $mm[2];
                    $sourceLabel = trim($acc['bank_name'] . ' ' . ($acc['account_number'] ?? ''));
                }
            } else {
                $box = (new CashBoxModel())->find((int) $mm[2]);
                if ($box && (int) $box['company_id'] === $companyId) {
                    $sourceType  = 'cash';
                    $sourceId    = (int) $mm[2];
                    $sourceLabel = 'Caja: ' . $box['name'];
                }
            }
        }

        // Validación de saldo al emitir Egreso
        if (mb_strtolower($type['name']) === 'egreso') {
            if ($sourceType === 'cash') {
                // §429/§6.5: valida el saldo de esa caja (excluye el movimiento previo de este comprobante al editar)
                $available = (new CashBoxService())->availableBalance($sourceId, $id);
                if ($calc['total_amount'] > $available) {
                    return redirect()->back()->withInput()->with('error',
                        'Saldo insuficiente en la caja seleccionada. Disponible: ' . number_format($available, 2) . ' · Egreso: ' . number_format($calc['total_amount'], 2) . '.');
                }
            } elseif ($id === null) {
                // §5.5: sin caja, valida el balance general de la empresa (solo al crear)
                $balance = $this->svc->companyBalance($companyId);
                if ($calc['total_amount'] > $balance) {
                    return redirect()->back()->withInput()->with('error',
                        'Saldo insuficiente para el egreso. Disponible: ' . number_format($balance, 2) . ' · Egreso: ' . number_format($calc['total_amount'], 2) . '.');
                }
            }
        }

        // Asiento contable: usa el enviado (si cuadra Debe = Haber) o se auto-genera si viene vacío
        $entryRows     = null;
        $manualEntries = $this->request->getPost('entries');
        if (is_array($manualEntries)) {
            $entryRows = [];
            $sumD      = 0.0;
            $sumC      = 0.0;
            $order     = 0;
            foreach ($manualEntries as $en) {
                $code   = trim((string) ($en['account_code'] ?? ''));
                $debit  = round((float) ($en['debit'] ?? 0), 2);
                $credit = round((float) ($en['credit'] ?? 0), 2);
                if ($code === '' || ($debit == 0.0 && $credit == 0.0)) {
                    continue;
                }
                $entryRows[] = ['account_code' => $code, 'account_name' => trim((string) ($en['account_name'] ?? '')) ?: $code, 'debit' => $debit, 'credit' => $credit, 'description' => $en['description'] ?? null, 'sort_order' => $order++];
                $sumD += $debit;
                $sumC += $credit;
            }
            if (empty($entryRows)) {
                $entryRows = null; // vacío → auto-generar
            } elseif (round($sumD, 2) !== round($sumC, 2)) {
                return redirect()->back()->withInput()->with('error', 'El asiento no cuadra: Debe ' . number_format($sumD, 2) . ' ≠ Haber ' . number_format($sumC, 2) . '.');
            }
        }

        $data = [
            'company_id'           => $companyId,
            'type_id'              => $typeId,
            'date'                 => $this->request->getPost('date') ?: date('Y-m-d'),
            'beneficiary_name'     => trim((string) $this->request->getPost('beneficiary_name')),
            'beneficiary_id'       => trim((string) $this->request->getPost('beneficiary_id')),
            'description'          => $this->request->getPost('description'),
            'payment_method'       => $this->request->getPost('payment_method') ?: 'Efectivo',
            'source_account_label' => $sourceLabel,
            'source_type'          => $sourceType,
            'source_id'            => $sourceId,
            'subtotal'             => $calc['subtotal'],
            'total_iva'            => $calc['total_iva'],
            'total_retencion'      => $calc['total_retencion'],
            'total_other_taxes'    => $calc['total_other_taxes'],
            'total_amount'         => $calc['total_amount'],
        ];

        $db      = \Config\Database::connect();
        $items   = new VoucherItemModel();
        $entries = new VoucherEntryModel();

        $db->transStart();
        if ($id === null) {
            $data['number'] = $this->svc->nextNumber($typeId);
            $data['status'] = 'emitido';
            $this->vouchers->insert($data);
            $vid = (int) $this->vouchers->getInsertID();
        } else {
            $vid = $id;
            $this->vouchers->update($id, $data);
            $items->where('voucher_id', $id)->delete();
            $entries->where('voucher_id', $id)->delete();
        }
        $itemTaxes = new VoucherItemTaxModel();
        foreach ($calc['items'] as $it) {
            $taxes = $it['taxes'] ?? [];
            unset($it['taxes']);
            $it['voucher_id'] = $vid;
            $items->insert($it);
            $iid = (int) $items->getInsertID();
            foreach ($taxes as $tx) {
                $tx['item_id'] = $iid;
                $itemTaxes->insert($tx);
            }
        }
        if ($entryRows === null) {
            $entryData = array_merge($data, ['other_additive' => $calc['other_additive'], 'other_subtractive' => $calc['other_subtractive']]);
            $entryRows = $this->svc->generateEntries($entryData, $type['name']);
        }
        foreach ($entryRows as $en) {
            $en['voucher_id'] = $vid;
            $entries->insert($en);
        }
        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'No se pudo guardar el comprobante.');
        }

        // Sincroniza el movimiento de caja si el origen es una caja menor (Ingreso/Egreso)
        $number = $data['number'] ?? ($this->vouchers->find($vid)['number'] ?? ('#' . $vid));
        (new CashBoxService())->syncVoucherMovement($vid, $sourceType, $sourceId, $type['name'], (float) $calc['total_amount'], $data['date'], $number);

        return redirect()->to('contabilidad/comprobantes/' . $vid)
            ->with('success', 'Comprobante ' . ($data['number'] ?? '#' . $vid) . ' guardado.');
    }

    public function show($id)
    {
        $companyId = $this->companyId();
        $v         = $this->vouchers->find($id);
        if (!$v || (int) $v['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/comprobantes')->with('error', 'Comprobante no encontrado.');
        }

        return view('Components\Contabilidad\Views\vouchers\show', [
            'title'   => 'Comprobante ' . $v['number'],
            'v'       => $v,
            'type'    => (new VoucherTypeModel())->find($v['type_id']),
            'items'   => $this->itemsWithTaxes((int) $id),
            'entries' => (new VoucherEntryModel())->where('voucher_id', $id)->orderBy('sort_order', 'asc')->findAll(),
            'company'     => (new \App\Models\CompanyModel())->find($v['company_id']),
            'qr'          => (new QrService())->dataUri(base_url('contabilidad/verificar/' . $v['id'])),
            'attachments' => (new AttachmentService())->listFor('voucher', (int) $id),
        ]);
    }

    public function pdf($id)
    {
        $companyId = $this->companyId();
        $v         = $this->vouchers->find($id);
        if (!$v || (int) $v['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/comprobantes')->with('error', 'Comprobante no encontrado.');
        }
        $entries = (new VoucherEntryModel())->where('voucher_id', $id)->orderBy('sort_order', 'asc')->findAll();

        $html = view('Components\Contabilidad\Views\vouchers\pdf', [
            'v'         => $v,
            'type'      => (new VoucherTypeModel())->find($v['type_id']),
            'items'     => $this->itemsWithTaxes((int) $id),
            'entries'   => $entries,
            'company'   => (new \App\Models\CompanyModel())->find($v['company_id']),
            'verifyUrl' => base_url('contabilidad/verificar/' . $v['id']),
            'qr'        => (new QrService())->dataUri(base_url('contabilidad/verificar/' . $v['id'])),
            'debe'      => array_sum(array_map(static fn ($e) => (float) $e['debit'], $entries)),
            'haber'     => array_sum(array_map(static fn ($e) => (float) $e['credit'], $entries)),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $v['number'] . '.pdf"')
            ->setBody((new PdfService())->render($html));
    }

    public function status($id)
    {
        $companyId = $this->companyId();
        $v         = $this->vouchers->find($id);
        if (!$v || (int) $v['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/comprobantes')->with('error', 'Comprobante no encontrado.');
        }
        $new = (string) $this->request->getPost('status');
        if (!in_array($new, ['emitido', 'pagado', 'anulado'], true)) {
            return redirect()->back()->with('error', 'Estado inválido.');
        }
        if ($new === 'anulado') {
            (new CashBoxService())->removeVoucherMovements((int) $id); // reversa el efecto en la caja
        }
        $this->vouchers->update($id, ['status' => $new]);

        return redirect()->to('contabilidad/comprobantes/' . $id)->with('success', 'Estado actualizado a ' . $new . '.');
    }

    public function delete($id)
    {
        $companyId = $this->companyId();
        $v         = $this->vouchers->find($id);
        if (!$v || (int) $v['company_id'] !== $companyId) {
            return redirect()->to('contabilidad/comprobantes')->with('error', 'Comprobante no encontrado.');
        }
        (new CashBoxService())->removeVoucherMovements((int) $id);
        $this->vouchers->delete($id);

        return redirect()->to('contabilidad/comprobantes')->with('success', 'Comprobante eliminado.');
    }

    /** Datos comunes del formulario (tipos, beneficiarios, medios de pago). */
    private function formData(int $companyId, ?array $voucher): array
    {
        $taxes = (new TaxConfigModel())->where('company_id', $companyId)->where('is_active', 1)->orderBy('sort_order', 'asc')->findAll();

        return [
            'title'          => $voucher ? 'Editar comprobante' : 'Nuevo comprobante',
            'voucher'        => $voucher ?? [],
            'items'          => [],
            'types'          => (new VoucherTypeModel())->where('company_id', $companyId)->findAll(),
            'beneficiaries'  => (new BeneficiaryModel())->where('company_id', $companyId)->orderBy('name', 'asc')->findAll(),
            'paymentMethods' => VoucherService::PAYMENT_METHODS,
            'otherTaxes'     => array_values(array_filter($taxes, static fn ($t) => $t['category'] === 'other')),
            'ivaRates'       => array_values(array_filter($taxes, static fn ($t) => $t['category'] === 'iva')),
            'retRates'       => array_values(array_filter($taxes, static fn ($t) => $t['category'] === 'retencion')),
            'entries'        => [],
            'accountMap'     => $this->svc->accountNames(),
            'paymentMap'     => VoucherService::paymentAccountMap(),
            'accounts'       => (new CompanyAccountModel())->where('company_id', $companyId)->where('is_active', 1)->orderBy('bank_name', 'asc')->findAll(),
            'cashBoxes'      => $this->cashBoxesWithBalance($companyId),
        ];
    }

    /** Cajas menores de la empresa con su saldo actual (para el selector de origen). */
    private function cashBoxesWithBalance(int $companyId): array
    {
        $svc   = new CashBoxService();
        $boxes = (new CashBoxModel())->where('company_id', $companyId)->orderBy('name', 'asc')->findAll();
        foreach ($boxes as &$b) {
            $b['balance'] = $svc->balance((int) $b['id'], $b);
        }
        unset($b);

        return $boxes;
    }
}
