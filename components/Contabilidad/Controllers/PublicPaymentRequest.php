<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use Components\Contabilidad\Libraries\AttachmentService;
use Components\Contabilidad\Libraries\PaymentRequestService;
use Components\Contabilidad\Libraries\PlanGuard;
use Components\Contabilidad\Models\BeneficiaryModel;
use Components\Contabilidad\Models\PaymentRequestModel;

/**
 * Formulario PÚBLICO de cuenta de cobro (sin login), con la marca de la empresa.
 * Ruta: /contabilidad/solicitud/{companyId}
 */
class PublicPaymentRequest extends BaseController
{
    private function company($companyId): ?array
    {
        return (new \App\Models\CompanyModel())->find($companyId);
    }

    public function form($companyId = null)
    {
        $company = $this->company($companyId);
        if (!$company || empty($company['is_active'])) {
            return view('Components\Contabilidad\Views\public\invalid');
        }

        // Pantalla de éxito tras enviar (vía flashdata, sin exponer ids)
        if ($ok = session()->getFlashdata('pr_success')) {
            return view('Components\Contabilidad\Views\public\success', ['company' => $company, 'ok' => $ok]);
        }

        // Límites del plan de la empresa
        $guard = new PlanGuard();
        if (!$guard->serviceEnabled((int) $company['id'])) {
            return view('Components\Contabilidad\Views\public\invalid', ['message' => 'Esta empresa no tiene habilitado el servicio de cuentas de cobro.']);
        }
        $left = $guard->paymentRequestsLeft((int) $company['id']);
        if ($left !== null && $left <= 0) {
            return view('Components\Contabilidad\Views\public\invalid', ['message' => 'Esta empresa alcanzó el límite de cuentas de cobro de su plan.']);
        }

        return view('Components\Contabilidad\Views\public\form', [
            'company'      => $company,
            'idTypes'      => ['CC', 'NIT', 'CE', 'Pasaporte', 'RUT', 'TI', 'Otro'],
            'accountTypes' => ['Ahorros', 'Corriente', 'Nómina', 'Fiduciaria', 'Otro'],
        ]);
    }

    public function submit($companyId = null)
    {
        $company = $this->company($companyId);
        if (!$company || empty($company['is_active'])) {
            return view('Components\Contabilidad\Views\public\invalid');
        }
        $companyId = (int) $company['id'];
        $req       = $this->request;
        $svc       = new PaymentRequestService();

        $guard = new PlanGuard();
        if (!$guard->serviceEnabled($companyId)) {
            return redirect()->back()->withInput()->with('error', 'El servicio de cuentas de cobro no está disponible para esta empresa.');
        }
        $left = $guard->paymentRequestsLeft($companyId);
        if ($left !== null && $left <= 0) {
            return redirect()->back()->withInput()->with('error', 'Se alcanzó el límite de cuentas de cobro del plan.');
        }

        if (trim((string) $req->getPost('collaborator_name')) === '' || trim((string) $req->getPost('collaborator_id')) === '') {
            return redirect()->back()->withInput()->with('error', 'Nombre y número de documento son obligatorios.');
        }

        $calc = $svc->computeItems($req->getPost('items') ?? []);
        if (empty($calc['items'])) {
            return redirect()->back()->withInput()->with('error', 'Agrega al menos un concepto con valor.');
        }

        $collab = [
            'name'         => trim((string) $req->getPost('collaborator_name')),
            'id_type'      => $req->getPost('collaborator_id_type') ?: 'CC',
            'id_number'    => trim((string) $req->getPost('collaborator_id')),
            'email'        => trim((string) $req->getPost('collaborator_email')),
            'phone'        => trim((string) $req->getPost('collaborator_phone')),
            'city'         => trim((string) $req->getPost('collaborator_city')),
            'address'      => trim((string) $req->getPost('collaborator_address')),
            'contact'      => trim((string) $req->getPost('collaborator_contact')),
            'bank'         => trim((string) $req->getPost('collaborator_bank')),
            'account_type' => $req->getPost('collaborator_account_type') ?: 'Ahorros',
            'account'      => trim((string) $req->getPost('collaborator_account')),
        ];

        $sig = (string) $req->getPost('signature_data');

        $reference = $svc->nextReference($companyId);
        $prm       = new PaymentRequestModel();
        $prm->insert([
            'company_id'            => $companyId,
            'reference'             => $reference,
            'collaborator_name'     => $collab['name'],
            'collaborator_id_type'  => $collab['id_type'],
            'collaborator_id'       => $collab['id_number'],
            'collaborator_email'    => $collab['email'],
            'collaborator_phone'    => $collab['phone'],
            'collaborator_city'     => $collab['city'],
            'collaborator_address'  => $collab['address'],
            'collaborator_contact'  => $collab['contact'],
            'collaborator_bank'     => $collab['bank'],
            'collaborator_account_type' => $collab['account_type'],
            'collaborator_account'  => $collab['account'],
            'concept'               => $calc['concept'],
            'period'                => $req->getPost('period'),
            'items_json'            => json_encode($calc['items']),
            'amount'                => $calc['amount'],
            'iva_amount'            => $calc['iva_amount'],
            'total_amount'          => $calc['total'],
            'cert_no_iva'           => $req->getPost('cert_no_iva') ? 1 : 0,
            'cert_art383'           => $req->getPost('cert_art383') ? 1 : 0,
            'signature_data'        => ($sig !== '' && strncmp($sig, 'data:image', 10) === 0) ? $sig : null,
            'signed_at'             => $sig !== '' ? date('Y-m-d H:i:s') : null,
            'status'                => 'pending',
        ]);

        $prId = (int) $prm->getInsertID();

        // Adjuntos (soportes) subidos por el colaborador
        $att = new AttachmentService();
        foreach (($this->request->getFileMultiple('files') ?? []) as $f) {
            if ($f && $f->getError() !== UPLOAD_ERR_NO_FILE) {
                $att->store($f, 'payment_request', $prId, null);
            }
        }

        // Registrar/actualizar el tercero en el directorio de la empresa (§6.8)
        $svc->upsertBeneficiary($companyId, $collab);

        return redirect()->to('contabilidad/solicitud/' . $companyId)
            ->with('pr_success', ['reference' => $reference, 'total' => $calc['total']]);
    }
}
