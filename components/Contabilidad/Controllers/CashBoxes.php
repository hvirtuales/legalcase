<?php

namespace Components\Contabilidad\Controllers;

use App\Controllers\BaseController;
use Components\Contabilidad\Libraries\CashBoxService;
use Components\Contabilidad\Models\CashBoxModel;
use Components\Contabilidad\Models\CashTransactionModel;

class CashBoxes extends BaseController
{
    private CashBoxModel $model;
    private CashBoxService $svc;

    public function __construct()
    {
        $this->model = new CashBoxModel();
        $this->svc   = new CashBoxService();
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
            return view('Components\Contabilidad\Views\cashboxes\index', ['title' => 'Cajas menores', 'boxes' => null]);
        }
        $boxes = $this->model->where('company_id', $companyId)->orderBy('name', 'asc')->findAll();
        foreach ($boxes as &$b) {
            $b['balance'] = $this->svc->balance((int) $b['id'], $b);
        }
        unset($b);

        return view('Components\Contabilidad\Views\cashboxes\index', ['title' => 'Cajas menores', 'boxes' => $boxes]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('contabilidad/cajas')->with('error', 'Selecciona una empresa activa.');
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->saveBox(null, $companyId);
        }

        return view('Components\Contabilidad\Views\cashboxes\form', ['title' => 'Nueva caja menor', 'box' => []]);
    }

    public function edit($id)
    {
        $box = $this->find($id);
        if (!$box) {
            return redirect()->to('contabilidad/cajas')->with('error', 'Caja no encontrada.');
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->saveBox((int) $id, (int) $box['company_id']);
        }

        return view('Components\Contabilidad\Views\cashboxes\form', ['title' => 'Editar caja menor', 'box' => $box]);
    }

    private function saveBox(?int $id, int $companyId)
    {
        $data = [
            'company_id'      => $companyId,
            'name'            => trim((string) $this->request->getPost('name')),
            'description'     => trim((string) $this->request->getPost('description')),
            'initial_balance' => $this->request->getPost('initial_balance') ?: 0,
        ];
        $ok = $id ? $this->model->update($id, $data) : $this->model->insert($data);
        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('contabilidad/cajas')->with('success', 'Caja menor guardada.');
    }

    public function delete($id)
    {
        $box = $this->find($id);
        if (!$box) {
            return redirect()->to('contabilidad/cajas')->with('error', 'Caja no encontrada.');
        }
        $this->model->delete($id); // los movimientos caen por FK CASCADE

        return redirect()->to('contabilidad/cajas')->with('success', 'Caja eliminada.');
    }

    public function show($id)
    {
        $box = $this->find($id);
        if (!$box) {
            return redirect()->to('contabilidad/cajas')->with('error', 'Caja no encontrada.');
        }
        $movements = $this->svc->movements((int) $id);

        return view('Components\Contabilidad\Views\cashboxes\show', [
            'title'     => 'Caja: ' . $box['name'],
            'box'       => $box,
            'movements' => $movements,
            'balance'   => $this->svc->balance((int) $id, $box, $movements),
        ]);
    }

    /** Registra un movimiento (§6.6). */
    public function addMovement($id)
    {
        $box = $this->find($id);
        if (!$box) {
            return redirect()->to('contabilidad/cajas')->with('error', 'Caja no encontrada.');
        }
        $type = (string) $this->request->getPost('type');
        if (!in_array($type, CashBoxService::TYPES, true)) {
            return redirect()->to('contabilidad/cajas/' . $id)->with('error', 'Tipo de movimiento inválido.');
        }
        $prevBal = $this->svc->balance((int) $id, $box);

        if (in_array($type, ['ingreso', 'egreso'], true)) {
            $amount = round((float) $this->request->getPost('amount'), 2);
            if ($amount <= 0) {
                return redirect()->to('contabilidad/cajas/' . $id)->with('error', 'El monto debe ser mayor a cero.');
            }
            if ($type === 'egreso' && $amount > $prevBal) {
                return redirect()->to('contabilidad/cajas/' . $id)->with('error', 'Saldo insuficiente en la caja (disponible: ' . number_format($prevBal, 2) . ').');
            }
            $balanceAfter = $type === 'ingreso' ? $prevBal + $amount : $prevBal - $amount;
        } else { // arqueo | ajuste → el usuario informa el efectivo contado
            $balanceAfter = round((float) $this->request->getPost('balance_after'), 2);
            $amount       = round($balanceAfter - $prevBal, 2); // diferencia (informativa)
        }

        (new CashTransactionModel())->insert([
            'cash_box_id'   => (int) $id,
            'type'          => $type,
            'concept'       => trim((string) $this->request->getPost('concept')),
            'amount'        => $amount,
            'balance_after' => $balanceAfter,
            'date'          => $this->request->getPost('date') ?: date('Y-m-d'),
            'reference'     => trim((string) $this->request->getPost('reference')) ?: null,
        ]);

        return redirect()->to('contabilidad/cajas/' . $id)->with('success', 'Movimiento registrado.');
    }

    public function deleteMovement($id, $txnId)
    {
        $box = $this->find($id);
        $txn = (new CashTransactionModel())->find((int) $txnId);
        if (!$box || !$txn || (int) $txn['cash_box_id'] !== (int) $id) {
            return redirect()->to('contabilidad/cajas')->with('error', 'Movimiento no encontrado.');
        }
        (new CashTransactionModel())->delete((int) $txnId);

        return redirect()->to('contabilidad/cajas/' . $id)->with('success', 'Movimiento eliminado.');
    }

    private function find($id): ?array
    {
        $box = $this->model->find($id);

        return ($box && (int) $box['company_id'] === $this->companyId()) ? $box : null;
    }
}
