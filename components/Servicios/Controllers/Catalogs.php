<?php

namespace Components\Servicios\Controllers;

use App\Libraries\DataTable;
use Components\Servicios\Libraries\CatalogService;
use Components\Servicios\Models\CatalogModel;
use Components\Servicios\Models\CatalogProductModel;
use Components\Servicios\Models\CatalogSellerModel;
use Components\Servicios\Models\ProductModel;
use Components\Servicios\Models\QuoteModel;
use Components\Servicios\Models\SellerModel;

/** Catálogos digitales: la vitrina que se comparte con los clientes. */
class Catalogs extends BaseCrud
{
    protected string $route   = 'servicios/catalogo-digital';
    protected string $viewDir = 'catalogs';

    public function __construct()
    {
        $this->model = new CatalogModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Servicios\Views\catalogs\index', ['title' => lang('Quo.catalogs'), 'dt' => null, 'counts' => []]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'headline'])
            ->sortable(['name', 'is_published'])
            ->defaultSort('name', 'asc')
            ->query(static fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        return view('Components\Servicios\Views\catalogs\index', [
            'title'  => lang('Quo.catalogs'),
            'dt'     => $dt,
            'counts' => $this->counts($companyId),
        ]);
    }

    /** Productos, vendedores y pedidos de cada catálogo, para el listado. */
    private function counts(int $companyId): array
    {
        $db  = \Config\Database::connect();
        $ids = array_column($this->model->where('company_id', $companyId)->findAll(), 'id');
        $out = ['products' => [], 'sellers' => [], 'orders' => []];

        if (!$ids) {
            return $out;
        }

        foreach ([['cmp_quo_catalog_products', 'products'], ['cmp_quo_catalog_sellers', 'sellers']] as [$table, $key]) {
            $rows = $db->table($table)->select('catalog_id, COUNT(*) AS total')
                ->whereIn('catalog_id', $ids)->groupBy('catalog_id')->get()->getResultArray();
            $out[$key] = array_column($rows, 'total', 'catalog_id');
        }

        $rows = $db->table('cmp_quo_quotes')->select('catalog_id, COUNT(*) AS total')
            ->whereIn('catalog_id', $ids)->where('deleted_at IS NULL')->groupBy('catalog_id')->get()->getResultArray();
        $out['orders'] = array_column($rows, 'total', 'catalog_id');

        return $out;
    }

    protected function formData(int $companyId, ?array $row = null): array
    {
        $catalogId = $row ? (int) $row['id'] : 0;

        return [
            'products'  => (new ProductModel())->where('company_id', $companyId)->where('is_active', 1)->orderBy('name', 'asc')->findAll(),
            'sellers'   => (new SellerModel())->activeFor($companyId),
            'picked'    => $catalogId ? (new CatalogProductModel())->idsFor($catalogId) : [],
            'assigned'  => $catalogId ? (new CatalogSellerModel())->withSeller($catalogId) : [],
            'catalogId' => $catalogId,
        ];
    }

    protected function fields(int $companyId): array
    {
        $r    = $this->request;
        $tier = (int) $r->getPost('price_tier');

        return [
            'company_id'   => $companyId,
            'name'         => trim((string) $r->getPost('name')),
            'headline'     => trim((string) $r->getPost('headline')),
            'intro'        => trim((string) $r->getPost('intro')),
            'price_tier'   => in_array($tier, [1, 2, 3], true) ? $tier : null,
            'show_prices'  => $r->getPost('show_prices') ? 1 : 0,
            'is_published' => $r->getPost('is_published') ? 1 : 0,
        ];
    }

    /** Productos y vendedores viven en sus tablas puente. */
    protected function afterSave(int $id, int $companyId): void
    {
        (new CatalogProductModel())->sync($id, (array) $this->request->getPost('products'));
        (new CatalogSellerModel())->sync($id, (array) $this->request->getPost('sellers'));
    }

    /** Enlaces del catálogo (uno por vendedor) y los pedidos que ha traído. */
    public function links($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to($this->route)->with('error', lang('Quo.errNotFound'));
        }

        $service = new CatalogService();
        $links   = [];

        foreach ((new CatalogSellerModel())->withSeller((int) $row['id']) as $a) {
            $a['url'] = $service->publicUrl($a);
            $links[]  = $a;
        }

        return view('Components\Servicios\Views\catalogs\links', [
            'title'   => $row['name'],
            'catalog' => $row,
            'links'   => $links,
            'orders'  => (new QuoteModel())
                ->where('company_id', $row['company_id'])
                ->where('catalog_id', $row['id'])
                ->orderBy('id', 'desc')
                ->findAll(20),
        ]);
    }
}
