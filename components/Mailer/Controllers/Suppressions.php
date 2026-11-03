<?php

namespace Components\Mailer\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Mailer\Libraries\EmailValidator;
use Components\Mailer\Libraries\SuppressionService;
use Components\Mailer\Models\SuppressionModel;

/** Lista de supresión (bajas y rebotes) por empresa activa. */
class Suppressions extends BaseController
{
    private SuppressionModel $model;

    public function __construct()
    {
        $this->model = new SuppressionModel();
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
            return view('Components\Mailer\Views\suppressions\index', ['title' => lang('Mailer.suppressions'), 'dt' => null]);
        }
        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['email', 'notes'])
            ->sortable(['email', 'reason', 'created_at'])
            ->defaultSort('created_at', 'desc')
            ->query(fn ($m) => $m->where('company_id', $companyId))
            ->paginate(20);

        // Métricas por motivo.
        $stats = ['bounce' => 0, 'complaint' => 0, 'unsubscribe' => 0, 'manual' => 0];
        foreach ($this->model->select('reason, COUNT(*) as c')->where('company_id', $companyId)->groupBy('reason')->findAll() as $r) {
            $stats[$r['reason']] = (int) $r['c'];
        }

        return view('Components\Mailer\Views\suppressions\index', ['title' => lang('Mailer.suppressions'), 'dt' => $dt, 'stats' => $stats]);
    }

    /** Agrega manualmente un correo a la supresión. */
    public function add()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('mailer/supresiones')->with('error', lang('Mailer.mNoCompany'));
        }
        $email = strtolower(trim((string) $this->request->getPost('email')));
        if (!(new EmailValidator())->isValidSyntax($email)) {
            return redirect()->back()->with('error', lang('Mailer.mBadEmail'));
        }
        (new SuppressionService())->suppress($companyId, $email, 'manual', trim((string) $this->request->getPost('notes')) ?: null);

        return redirect()->to('mailer/supresiones')->with('success', lang('Mailer.mSuppAdded'));
    }

    /** Reactivar: quita de la supresión. */
    public function delete($id)
    {
        $companyId = $this->companyId();
        $row       = $this->model->find($id);
        if (!$row || (int) $row['company_id'] !== $companyId) {
            return redirect()->to('mailer/supresiones')->with('error', lang('Mailer.mNotFound'));
        }
        $this->model->delete($id);

        return redirect()->to('mailer/supresiones')->with('success', lang('Mailer.mSuppRemoved'));
    }
}
