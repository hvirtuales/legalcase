<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Contabilidad\Models\CompanyAccountModel;

class CompanyAccounts extends BaseController
{
    private CompanyAccountModel $model;

    public function __construct()
    {
        $this->model = new CompanyAccountModel();
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
            return view('Components\Contabilidad\Views\accounts\index', ['title' => 'Cuentas bancarias', 'dt' => null]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['bank_name', 'account_number', 'account_holder'])
            ->sortable(['bank_name', 'account_type', 'account_number', 'initial_balance', 'is_active'])
            ->defaultSort('bank_name', 'asc')
            ->query(fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        return view('Components\Contabilidad\Views\accounts\index', ['title' => 'Cuentas bancarias', 'dt' => $dt]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('contabilidad/cuentas-bancarias')->with('error', 'Selecciona una empresa activa.');
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save(null, $companyId);
        }

        return view('Components\Contabilidad\Views\accounts\form', ['title' => 'Nueva cuenta bancaria', 'account' => []]);
    }

    public function edit($id)
    {
        $acc = $this->find($id);
        if (!$acc) {
            return redirect()->to('contabilidad/cuentas-bancarias')->with('error', 'Cuenta no encontrada.');
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save((int) $id, (int) $acc['company_id']);
        }

        return view('Components\Contabilidad\Views\accounts\form', ['title' => 'Editar cuenta bancaria', 'account' => $acc]);
    }

    private function save(?int $id, int $companyId)
    {
        $data = [
            'company_id'      => $companyId,
            'bank_name'       => trim((string) $this->request->getPost('bank_name')),
            'account_type'    => $this->request->getPost('account_type') ?: 'Ahorros',
            'account_number'  => trim((string) $this->request->getPost('account_number')),
            'account_holder'  => trim((string) $this->request->getPost('account_holder')),
            'initial_balance' => $this->request->getPost('initial_balance') ?: 0,
            'notes'           => $this->request->getPost('notes'),
            'is_active'       => $this->request->getPost('is_active') ? 1 : 0,
        ];
        $ok = $id ? $this->model->update($id, $data) : $this->model->insert($data);
        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('contabilidad/cuentas-bancarias')->with('success', 'Cuenta bancaria guardada.');
    }

    public function delete($id)
    {
        $acc = $this->find($id);
        if (!$acc) {
            return redirect()->to('contabilidad/cuentas-bancarias')->with('error', 'Cuenta no encontrada.');
        }
        $this->model->delete($id);

        return redirect()->to('contabilidad/cuentas-bancarias')->with('success', 'Cuenta eliminada.');
    }

    private function find($id): ?array
    {
        $acc = $this->model->find($id);

        return ($acc && (int) $acc['company_id'] === $this->companyId()) ? $acc : null;
    }
}
