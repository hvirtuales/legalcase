<?php

namespace Components\Marketplace\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use App\Libraries\UploadService;
use Components\Marketplace\Libraries\MarketService;
use Components\Marketplace\Libraries\PlanGuard;
use Components\Marketplace\Models\ListingModel;

/** Publicaciones de la empresa activa: alta, edición, estado y baja. */
class Listings extends BaseController
{
    private ListingModel $model;
    private MarketService $market;

    public function __construct()
    {
        $this->model  = new ListingModel();
        $this->market = new MarketService();
    }

    /** Empresa activa (contexto multi-tenant). null = Global. */
    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** Publicación de la empresa activa, o null (el permiso no basta: hay que ser dueño). */
    private function own(int $id): ?array
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return null;
        }
        $row = $this->model->find($id);

        return $row && (int) $row['company_id'] === $companyId ? $row : null;
    }

    public function mine()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Marketplace\Views\mine', ['title' => lang('Mkt.myListings'), 'dt' => null]);
        }

        $status = (string) $this->request->getGet('status');
        $guard  = new PlanGuard();

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['title', 'summary', 'city'])
            ->sortable(['title', 'type', 'status', 'created_at', 'views'])
            ->defaultSort('created_at', 'desc')
            ->query(static function ($m) use ($companyId, $status) {
                $m->where('company_id', $companyId);
                if (in_array($status, ListingModel::STATUSES, true)) {
                    $m->where('status', $status);
                }
            })
            ->paginate(10);

        return view('Components\Marketplace\Views\mine', [
            'title'       => lang('Mkt.myListings'),
            'dt'          => $dt,
            'status'      => $status,
            'extraParams' => array_filter(['status' => $status ?: null]),
            'planEnabled' => $guard->serviceEnabled(),
            'quota'       => $guard->listingsLeft(),
            'newInterest' => $this->market->newInterestCount($companyId),
            // El botón de datos demo solo tiene sentido con el catálogo vacío.
            'showDemo'    => (new \Components\Marketplace\Libraries\DemoData())->isEmpty($companyId),
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errNoCompany'));
        }

        $guard = new PlanGuard();
        if (!$guard->serviceEnabled()) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errPlanDisabled'));
        }
        $left = $guard->listingsLeft();
        if ($left !== null && $left <= 0) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errPlanQuota'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->save(null, $companyId);
        }

        return view('Components\Marketplace\Views\form', [
            'title'      => lang('Mkt.newListing'),
            'listing'    => [],
            'tagsText'   => '',
            'categories' => $this->market->categories(),
            'action'     => base_url('marketplace/nueva'),
        ]);
    }

    public function edit($id = null)
    {
        $listing = $this->own((int) $id);
        if (!$listing) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errListingNotFound'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->save($listing, (int) $listing['company_id']);
        }

        return view('Components\Marketplace\Views\form', [
            'title'      => lang('Mkt.editListing'),
            'listing'    => $listing,
            'tagsText'   => $this->market->tagsText((int) $listing['id']),
            'categories' => $this->market->categories(),
            'action'     => base_url('marketplace/editar/' . (int) $listing['id']),
        ]);
    }

    /** Alta y edición comparten el mismo guardado. */
    private function save(?array $listing, int $companyId)
    {
        $r    = $this->request;
        $data = [
            'company_id'     => $companyId,
            'user_id'        => (int) session()->get('user_id'),
            'type'           => (string) $r->getPost('type'),
            'category_id'    => $r->getPost('category_id') ? (int) $r->getPost('category_id') : null,
            'title'          => trim((string) $r->getPost('title')),
            'summary'        => trim((string) $r->getPost('summary')),
            'description'    => trim((string) $r->getPost('description')),
            'price_type'     => (string) $r->getPost('price_type') ?: 'quote',
            'price_amount'   => (float) str_replace(',', '', (string) $r->getPost('price_amount')),
            'price_currency' => strtoupper(trim((string) $r->getPost('price_currency'))) ?: 'COP',
            'city'           => trim((string) $r->getPost('city')),
            'status'         => (string) $r->getPost('status') ?: 'published',
            'valid_until'    => $r->getPost('valid_until') ?: null,
        ];

        if ($image = (new UploadService())->storeImage($r->getFile('image'), 'marketplace', 'listing')) {
            $data['image'] = $image;
        }

        if ($listing) {
            if (!$this->model->update((int) $listing['id'], $data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }
            $id = (int) $listing['id'];
        } else {
            if (!$this->model->insert($data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }
            $id = (int) $this->model->getInsertID();
        }

        $this->market->syncTags($id, (string) $r->getPost('tags'));

        return redirect()->to('marketplace/mis-publicaciones')
            ->with('success', $listing ? lang('Mkt.okUpdated') : lang('Mkt.okCreated'));
    }

    /** POST marketplace/estado/{id} — publicar, pausar o cerrar. */
    public function changeStatus($id = null)
    {
        $listing = $this->own((int) $id);
        if (!$listing) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errListingNotFound'));
        }
        $status = (string) $this->request->getPost('status');
        if (!in_array($status, ListingModel::STATUSES, true)) {
            return redirect()->back()->with('error', lang('Mkt.errBadStatus'));
        }

        // Volver a publicar consume cupo del plan.
        if ($status === 'published' && $listing['status'] !== 'published') {
            $guard = new PlanGuard();
            if (!$guard->serviceEnabled()) {
                return redirect()->back()->with('error', lang('Mkt.errPlanDisabled'));
            }
            $left = $guard->listingsLeft();
            if ($left !== null && $left <= 0 && !in_array($listing['status'], ['draft', 'paused'], true)) {
                return redirect()->back()->with('error', lang('Mkt.errPlanQuota'));
            }
        }

        $this->model->update((int) $id, ['status' => $status]);

        return redirect()->to('marketplace/mis-publicaciones')->with('success', lang('Mkt.okStatusChanged'));
    }

    /**
     * POST marketplace/demo — llena el catálogo con publicaciones de ejemplo.
     * Solo si la empresa no tiene ninguna todavía (no pisa datos reales) y
     * respetando el servicio y el cupo del plan.
     */
    public function seedDemo()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errNoCompany'));
        }

        $guard = new PlanGuard();
        if (!$guard->serviceEnabled()) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errPlanDisabled'));
        }

        $demo = new \Components\Marketplace\Libraries\DemoData();
        if (!$demo->isEmpty($companyId)) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errDemoNotEmpty'));
        }

        $left = $guard->listingsLeft();
        if ($left !== null && $left < 14) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errDemoQuota'));
        }

        $res = $demo->generate($companyId, (int) session()->get('user_id'));

        return redirect()->to('marketplace/mis-publicaciones')
            ->with('success', lang('Mkt.okDemo', [$res['listings'], $res['interests']]));
    }

    public function delete($id = null)
    {
        $listing = $this->own((int) $id);
        if (!$listing) {
            return redirect()->to('marketplace/mis-publicaciones')->with('error', lang('Mkt.errListingNotFound'));
        }
        $this->model->delete((int) $id);

        return redirect()->to('marketplace/mis-publicaciones')->with('success', lang('Mkt.okDeleted'));
    }
}
