<?php

namespace Components\Servicios\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Servicios\Libraries\PlanGuard;
use Components\Servicios\Libraries\QuoteService;
use Components\Servicios\Models\ClientModel;
use Components\Servicios\Models\QuoteModel;
use Components\Servicios\Models\SellerModel;
use Components\Servicios\Models\SettingsModel;

/** Panel de cotizaciones y el cotizador. */
class Quotes extends BaseController
{
    private QuoteService $service;
    private QuoteModel $model;

    public function __construct()
    {
        $this->service = new QuoteService();
        $this->model   = new QuoteModel();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    // ------------------------------------------------------------------ panel

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Servicios\Views\index', ['title' => lang('Quo.quotes'), 'dt' => null]);
        }

        $filters = [
            'period' => (string) $this->request->getGet('period'),
            'seller' => (string) $this->request->getGet('seller'),
            'status' => (string) $this->request->getGet('status'),
        ];

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['number', 'client_name', 'client_nit', 'seller_name'])
            ->sortable(['number', 'issue_date', 'client_name', 'total', 'status'])
            ->defaultSort('id', 'desc')
            ->query(fn ($m) => $this->service->applyFilters($m, $companyId, $filters))
            ->paginate(10);

        $guard = new PlanGuard();

        return view('Components\Servicios\Views\index', [
            'title'       => lang('Quo.quotes'),
            'dt'          => $dt,
            'filters'     => $filters,
            'extraParams' => array_filter($filters),
            'metrics'     => $this->service->metrics($companyId, $filters),
            'sellers'     => (new SellerModel())->activeFor($companyId),
            'currency'    => (new SettingsModel())->forCompany($companyId)['default_currency'],
            'planEnabled' => $guard->serviceEnabled(),
            'quota'       => $guard->quotesLeft(),
            'showDemo'    => $this->model->where('company_id', $companyId)->countAllResults() === 0,
        ]);
    }

    // ------------------------------------------------------------------ cotizador

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errNoCompany'));
        }

        $guard = new PlanGuard();
        if (!$guard->serviceEnabled()) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errPlanDisabled'));
        }
        $left = $guard->quotesLeft();
        if ($left !== null && $left <= 0) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errPlanQuota'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store(null, $companyId);
        }

        $cfg = (new SettingsModel())->forCompany($companyId);

        return view('Components\Servicios\Views\form', $this->formData($companyId, [
            'doc_type'          => $cfg['doc_type'],
            'issue_date'        => date('Y-m-d'),
            'validity_days'     => $cfg['default_validity_days'],
            'currency'          => $cfg['default_currency'],
            'retention_percent' => $cfg['default_retention'],
            'terms_html'        => $cfg['terms_html'],
            'show_detail'       => $cfg['show_detail_default'],
            'status'            => 'pendiente',
        ], [], lang('Quo.newQuote'), base_url('servicios/cotizaciones/nueva')));
    }

    public function edit($id = null)
    {
        $companyId = $this->companyId();
        $quote     = $companyId ? $this->service->find($companyId, (int) $id) : null;
        if (!$quote) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errNotFound'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store($quote, $companyId);
        }

        return view('Components\Servicios\Views\form', $this->formData(
            $companyId,
            $quote,
            $quote['items'],
            lang('Quo.editQuote') . ' ' . $quote['number'],
            base_url('servicios/cotizaciones/editar/' . (int) $quote['id'])
        ));
    }

    /** Datos comunes de la pantalla del cotizador. */
    private function formData(int $companyId, array $quote, array $items, string $title, string $action): array
    {
        return [
            'title'   => $title,
            'action'  => $action,
            'quote'   => $quote,
            'items'   => $items,
            'clients'    => (new ClientModel())->where('company_id', $companyId)->orderBy('name', 'asc')->findAll(),
            'sellers'    => (new SellerModel())->activeFor($companyId),
        ];
    }

    private function store(?array $existing, int $companyId)
    {
        $res = $this->service->save(
            $companyId,
            [
                'doc_type'          => $this->request->getPost('doc_type'),
                'client_id'         => $this->request->getPost('client_id'),
                'seller_id'         => $this->request->getPost('seller_id'),
                'issue_date'        => $this->request->getPost('issue_date'),
                'validity_days'     => $this->request->getPost('validity_days'),
                'payment_form'      => $this->request->getPost('payment_form'),
                'payment_method'    => $this->request->getPost('payment_method'),
                'currency'          => $this->request->getPost('currency'),
                'retention_percent' => $this->request->getPost('retention_percent'),
                'intro_html'        => $this->request->getPost('intro_html'),
                'terms_html'        => $this->request->getPost('terms_html'),
                'show_detail'       => $this->request->getPost('show_detail'),
                'status'            => $this->request->getPost('status'),
            ],
            (array) ($this->request->getPost('items') ?? []),
            $existing
        );

        if (!$res['ok']) {
            return redirect()->back()->withInput()->with('error', $res['message']);
        }

        return redirect()->to('servicios/cotizaciones/ver/' . $res['id'])->with('success', $res['message']);
    }

    // ------------------------------------------------------------------ detalle

    public function show($id = null)
    {
        $companyId = $this->companyId();
        $quote     = $companyId ? $this->service->find($companyId, (int) $id) : null;
        if (!$quote) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errNotFound'));
        }

        $links = new \Components\Servicios\Libraries\PublicLinkService();

        return view('Components\Servicios\Views\show', [
            'title'     => $quote['doc_type'] . ' ' . $quote['number'],
            'quote'     => $quote,
            'company'   => (new \App\Models\CompanyModel())->find($companyId),
            'cfg'       => (new SettingsModel())->forCompany($companyId),
            'publicUrl' => $links->url($quote['public_token'] ?? null),
            'responses' => $links->history((int) $quote['id']),
        ]);
    }

    public function pdf($id = null)
    {
        $companyId = $this->companyId();
        $quote     = $companyId ? $this->service->find($companyId, (int) $id) : null;
        if (!$quote) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errNotFound'));
        }

        $pdf     = new \Components\Servicios\Libraries\PdfService();
        $company = (new \App\Models\CompanyModel())->find($companyId);
        $cfg     = (new SettingsModel())->forCompany($companyId);

        $html = view('Components\Servicios\Views\pdf\document', [
            'quote'   => $quote,
            'company' => $company,
            'cfg'     => $cfg,
            'logo'    => $pdf->localImage($company['logo'] ?? null),
        ]);

        $file = $quote['number'] . '.pdf';

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file . '"')
            ->setBody($pdf->render($html));
    }

    // ------------------------------------------------------------------ enlace público

    /** POST servicios/cotizaciones/compartir/{id} — activa o actualiza el enlace del cliente. */
    public function share($id = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->back()->with('error', lang('Quo.errNoCompany'));
        }

        $links = new \Components\Servicios\Libraries\PublicLinkService();
        $res   = $links->enable(
            $companyId,
            (int) $id,
            (string) $this->request->getPost('password'),
            // Sin marcar «quitar contraseña», una contraseña vacía conserva la actual.
            !$this->request->getPost('remove_password')
        );

        return redirect()->to('servicios/cotizaciones/ver/' . (int) $id)
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** POST servicios/cotizaciones/compartir/{id}/cerrar — corta el acceso del cliente. */
    public function unshare($id = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->back()->with('error', lang('Quo.errNoCompany'));
        }

        $res = (new \Components\Servicios\Libraries\PublicLinkService())->disable($companyId, (int) $id);

        return redirect()->to('servicios/cotizaciones/ver/' . (int) $id)
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function changeStatus($id = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->back()->with('error', lang('Quo.errNoCompany'));
        }
        $res = $this->service->changeStatus($companyId, (int) $id, (string) $this->request->getPost('status'));

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function delete($id = null)
    {
        $companyId = $this->companyId();
        if (!$companyId || !$this->model->where('company_id', $companyId)->find((int) $id)) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errNotFound'));
        }
        $this->model->delete((int) $id);

        return redirect()->to('servicios/cotizaciones')->with('success', lang('Quo.okDeleted'));
    }

    // ------------------------------------------------------------------ demo

    public function seedDemo()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errNoCompany'));
        }
        if (!(new PlanGuard())->serviceEnabled()) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errPlanDisabled'));
        }

        $demo = new \Components\Servicios\Libraries\DemoData();
        if (!$demo->isEmpty($companyId)) {
            return redirect()->to('servicios/cotizaciones')->with('error', lang('Quo.errDemoNotEmpty'));
        }
        $res = $demo->generate($companyId);

        return redirect()->to('servicios/cotizaciones')
            ->with('success', lang('Quo.okDemo', [$res['quotes'], $res['clients'], $res['products']]));
    }
}
