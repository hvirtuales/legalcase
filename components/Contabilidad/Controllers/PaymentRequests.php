<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Contabilidad\Libraries\AttachmentService;
use Components\Contabilidad\Libraries\PaymentRequestService;
use Components\Contabilidad\Libraries\PlanGuard;
use Components\Contabilidad\Libraries\PdfService;
use Components\Contabilidad\Libraries\VoucherService;
use Components\Contabilidad\Models\PaymentRequestModel;

class PaymentRequests extends BaseController
{
    private PaymentRequestModel $model;

    public function __construct()
    {
        $this->model = new PaymentRequestModel();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Contabilidad\Views\payment_requests\index', ['title' => 'Cuentas de cobro', 'dt' => null]);
        }

        $req    = $this->request;
        $status = (string) $req->getGet('status');

        $dt = (new DataTable($this->model, $req))
            ->searchable(['reference', 'collaborator_name', 'collaborator_id'])
            ->sortable(['reference', 'created_at', 'total_amount', 'status'])
            ->defaultSort('created_at', 'desc')
            ->query(function ($m) use ($companyId, $status) {
                $m->where('company_id', $companyId);
                if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
                    $m->where('status', $status);
                }
            })
            ->paginate(15);

        // Métricas
        $counts = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($this->model->select('status, COUNT(*) AS n')->where('company_id', $companyId)->groupBy('status')->findAll() as $r) {
            $counts[$r['status']] = (int) $r['n'];
            $counts['total'] += (int) $r['n'];
        }

        return view('Components\Contabilidad\Views\payment_requests\index', [
            'title'       => 'Cuentas de cobro',
            'dt'          => $dt,
            'counts'      => $counts,
            'publicUrl'   => base_url('contabilidad/solicitud/' . $companyId),
            'extraParams' => array_filter(['status' => $status ?: null]),
            'quota'          => (new PlanGuard())->paymentRequestsLeft($companyId),
            'serviceEnabled' => (new PlanGuard())->serviceEnabled($companyId),
        ]);
    }

    public function show($id)
    {
        $pr = $this->find($id);
        if (!$pr) {
            return redirect()->to('contabilidad/cuentas-cobro')->with('error', 'Solicitud no encontrada.');
        }

        return view('Components\Contabilidad\Views\payment_requests\show', [
            'title'       => 'Cuenta de cobro ' . $pr['reference'],
            'pr'          => $pr,
            'items'       => json_decode((string) $pr['items_json'], true) ?: [],
            'attachments' => (new AttachmentService())->listFor('payment_request', (int) $id),
        ]);
    }

    public function pdf($id)
    {
        $pr = $this->find($id);
        if (!$pr) {
            return redirect()->to('contabilidad/cuentas-cobro')->with('error', 'Solicitud no encontrada.');
        }

        $html = view('Components\Contabilidad\Views\payment_requests\pdf', [
            'pr'      => $pr,
            'items'   => json_decode((string) $pr['items_json'], true) ?: [],
            'company' => (new \App\Models\CompanyModel())->find($pr['company_id']),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $pr['reference'] . '.pdf"')
            ->setBody((new PdfService())->render($html));
    }

    public function setRetencion($id)
    {
        $pr = $this->find($id);
        if (!$pr) {
            return redirect()->to('contabilidad/cuentas-cobro')->with('error', 'Solicitud no encontrada.');
        }
        if ($pr['status'] !== 'pending') {
            return redirect()->back()->with('error', 'Solo se puede definir retención mientras está pendiente.');
        }
        $rate = (float) $this->request->getPost('retencion_rate');
        $calc = (new PaymentRequestService())->applyRetencion($pr, $rate);

        $this->model->update($id, [
            'retencion_rate'   => $rate,
            'retencion_amount' => $calc['retencion_amount'],
            'total_amount'     => $calc['total_amount'],
            'items_json'       => json_encode($calc['items']),
        ]);

        return redirect()->to('contabilidad/cuentas-cobro/' . $id)->with('success', 'Retención aplicada (' . $rate . '%).');
    }

    public function approve($id)
    {
        $pr = $this->find($id);
        if (!$pr) {
            return redirect()->to('contabilidad/cuentas-cobro')->with('error', 'Solicitud no encontrada.');
        }

        // Auto-genera el comprobante de Egreso (una sola vez) y lo enlaza (§9.7)
        $voucherId = $pr['voucher_id'] ? (int) $pr['voucher_id'] : null;
        if (!$voucherId) {
            try {
                $svc    = new VoucherService();
                $egreso = null;
                foreach ($svc->ensureTypes((int) $pr['company_id']) as $t) {
                    if ($t['name'] === 'Egreso') {
                        $egreso = $t;
                    }
                }
                if (!$egreso) {
                    return redirect()->back()->with('error', 'No se encontró el tipo de comprobante Egreso.');
                }
                $raw = array_map(static fn ($it) => [
                    'description'    => $it['description'] ?? '',
                    'amount'         => $it['amount'] ?? 0,
                    'iva_rate'       => $it['iva_rate'] ?? 0,
                    'retencion_rate' => $it['retencion_rate'] ?? 0,
                ], json_decode((string) $pr['items_json'], true) ?: []);

                $voucherId = $svc->createVoucher((int) $pr['company_id'], $egreso, [
                    'beneficiary_name'     => $pr['collaborator_name'],
                    'beneficiary_id'       => $pr['collaborator_id'],
                    'description'          => 'Cuenta de cobro ' . $pr['reference'] . ($pr['period'] ? ' · ' . $pr['period'] : ''),
                    'payment_method'       => 'Transferencia bancaria',
                    'source_account_label' => trim($pr['collaborator_bank'] . ' ' . $pr['collaborator_account']) ?: null,
                ], $raw);
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'No se pudo generar el comprobante de Egreso: ' . $e->getMessage());
            }
        }

        $this->model->update($id, [
            'status'      => 'approved',
            'admin_notes' => $this->request->getPost('admin_notes'),
            'reviewed_by' => session()->get('username'),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'voucher_id'  => $voucherId,
        ]);

        return redirect()->to('contabilidad/cuentas-cobro/' . $id)->with('success', 'Solicitud aprobada. Comprobante de Egreso generado.');
    }

    public function reject($id)
    {
        return $this->review($id, 'rejected', 'rechazada');
    }

    private function review($id, string $status, string $label)
    {
        $pr = $this->find($id);
        if (!$pr) {
            return redirect()->to('contabilidad/cuentas-cobro')->with('error', 'Solicitud no encontrada.');
        }
        $this->model->update($id, [
            'status'      => $status,
            'admin_notes' => $this->request->getPost('admin_notes'),
            'reviewed_by' => session()->get('username'),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('contabilidad/cuentas-cobro/' . $id)->with('success', 'Solicitud ' . $label . '.');
    }

    private function find($id): ?array
    {
        $pr = $this->model->find($id);

        return ($pr && (int) $pr['company_id'] === $this->companyId()) ? $pr : null;
    }
}
