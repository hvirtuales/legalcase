<?php

namespace Components\Pqrsf\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Pqrsf\Libraries\PdfService;
use Components\Pqrsf\Libraries\PqrService;
use Components\Pqrsf\Libraries\QrService;
use Components\Pqrsf\Models\PointModel;
use Components\Pqrsf\Models\RequestModel;

/** Puntos de publicación: el QR que se pega en cada área. */
class Points extends BaseController
{
    private PointModel $model;

    public function __construct()
    {
        $this->model = new PointModel();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    private function own(int $id): ?array
    {
        $companyId = $this->companyId();
        $row       = $companyId ? $this->model->find($id) : null;

        return $row && (int) $row['company_id'] === $companyId ? $row : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Pqrsf\Views\points\index', ['title' => lang('Pqr.points'), 'dt' => null, 'urls' => [], 'counts' => []]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'area', 'notes'])
            ->sortable(['name', 'area', 'visits'])
            ->defaultSort('name', 'asc')
            ->query(static fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        $service = new PqrService();
        $urls    = [];

        foreach ($dt['rows'] as $row) {
            $urls[$row['id']] = $service->pointUrl($row);
        }

        // Cuántas solicitudes ha traído cada punto.
        $rows   = \Config\Database::connect()->table('cmp_pqr_requests')
            ->select('point_id, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('deleted_at IS NULL')
            ->groupBy('point_id')
            ->get()->getResultArray();
        $counts = array_column($rows, 'total', 'point_id');

        return view('Components\Pqrsf\Views\points\index', [
            'title' => lang('Pqr.points'), 'dt' => $dt, 'urls' => $urls, 'counts' => $counts,
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('pqrsf/puntos')->with('error', lang('Pqr.errNoCompany'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store(null, $companyId);
        }

        return view('Components\Pqrsf\Views\points\form', [
            'title' => lang('Pqr.pointsNew'), 'row' => [], 'action' => base_url('pqrsf/puntos/nuevo'),
        ]);
    }

    public function edit($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to('pqrsf/puntos')->with('error', lang('Pqr.errNotFound'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store($row, (int) $row['company_id']);
        }

        return view('Components\Pqrsf\Views\points\form', [
            'title' => lang('Pqr.pointsEdit'), 'row' => $row, 'action' => base_url('pqrsf/puntos/editar/' . (int) $row['id']),
        ]);
    }

    private function store(?array $existing, int $companyId)
    {
        $r    = $this->request;
        $data = [
            'company_id' => $companyId,
            'name'       => trim((string) $r->getPost('name')),
            'area'       => trim((string) $r->getPost('area')),
            'notes'      => trim((string) $r->getPost('notes')),
            'is_active'  => $r->getPost('is_active') ? 1 : 0,
        ];

        if ($existing) {
            $ok = $this->model->update((int) $existing['id'], $data);
        } else {
            // El token se genera aquí y no cambia nunca: ya estará impreso.
            $data['token'] = bin2hex(random_bytes(16));
            $ok            = $this->model->insert($data);
        }

        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('pqrsf/puntos')->with('success', $existing ? lang('Pqr.okUpdated') : lang('Pqr.okCreated2'));
    }

    public function delete($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to('pqrsf/puntos')->with('error', lang('Pqr.errNotFound'));
        }

        // Las solicitudes ya radicadas no se tocan: quedan sin punto (FK SET NULL).
        $this->model->delete((int) $row['id']);

        return redirect()->to('pqrsf/puntos')->with('success', lang('Pqr.okPointDeleted'));
    }

    /** GET pqrsf/puntos/cartel/{id} — el afiche para pegar en el área. */
    public function poster($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to('pqrsf/puntos')->with('error', lang('Pqr.errNotFound'));
        }

        $pdf     = new PdfService();
        $service = new PqrService();
        $url     = $service->pointUrl($row);
        $company = (new \App\Models\CompanyModel())->find((int) $row['company_id']);

        $html = view('Components\Pqrsf\Views\pdf\poster', [
            'point'   => $row,
            'company' => $company,
            'url'     => $url,
            'qr'      => (new QrService())->dataUri($url, 10),
            'logo'    => $pdf->localImage($company['logo'] ?? null),
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . url_title($row['name'], '-', true) . '.pdf"')
            ->setBody($pdf->render($html));
    }
}
