<?php

namespace Components\Servicios\Controllers;

use App\Libraries\DataTable;
use Components\Servicios\Libraries\ProductImporter;
use Components\Servicios\Models\CategoryModel;
use Components\Servicios\Models\ProductModel;
use Components\Servicios\Models\ProductPriceModel;

/** Catálogo de productos y servicios. */
class Products extends BaseCrud
{
    protected string $route   = 'servicios/productos';
    protected string $viewDir = 'products';

    public function __construct()
    {
        $this->model = new ProductModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Servicios\Views\products\index', [
                'title' => lang('Quo.products'), 'dt' => null, 'categories' => [], 'categoryId' => null, 'active' => null,
            ]);
        }

        // Filtros del listado. Vacío = sin filtrar; `?categoria=0` es «sin
        // clasificar» y `?estado=0` es «inactivos» — por eso no vale un `?:`.
        $rawCat     = $this->request->getGet('categoria');
        $categoryId = $rawCat === null || $rawCat === '' ? null : (int) $rawCat;
        $rawState   = $this->request->getGet('estado');
        $active     = $rawState === null || $rawState === '' ? null : (int) $rawState;

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['code', 'name', 'description'])
            ->sortable(['code', 'name', 'unit_price', 'tax_percent'])
            ->defaultSort('code', 'asc')
            ->query(static function ($m) use ($companyId, $categoryId, $active) {
                $m->where('company_id', $companyId);
                if ($categoryId === 0) {
                    $m->where('category_id', null);
                } elseif ($categoryId !== null) {
                    $m->where('category_id', $categoryId);
                }
                if ($active !== null) {
                    $m->where('is_active', $active);
                }

                return $m;
            })
            ->paginate(15);

        return view('Components\Servicios\Views\products\index', [
            'title'      => lang('Quo.products'),
            'dt'         => $dt,
            'categories' => (new CategoryModel())->mapFor($companyId),
            'categoryId' => $categoryId,
            'active'     => $active,
        ]);
    }

    /** El formulario necesita las categorías y los valores ya guardados. */
    protected function formData(int $companyId, ?array $row = null): array
    {
        return [
            'categories' => (new CategoryModel())->optionsFor($companyId),
            'tiers'      => $row ? (new ProductPriceModel())->forProduct((int) $row['id']) : [],
        ];
    }

    /** Los 3 valores del producto viven en su propia tabla. */
    protected function afterSave(int $id, int $companyId): void
    {
        $posted = $this->request->getPost('tiers');
        if (is_array($posted)) {
            (new ProductPriceModel())->saveTiers($id, $posted);
        }
    }

    protected function fields(int $companyId): array
    {
        $r = $this->request;

        $data = [
            'company_id'       => $companyId,
            // Sin categoría se guarda NULL, no 0: la FK es SET NULL.
            'category_id'      => (int) $r->getPost('category_id') ?: null,
            'code'             => trim((string) $r->getPost('code')),
            'name'             => trim((string) $r->getPost('name')),
            'description'      => trim((string) $r->getPost('description')),
            'unit_price'       => (float) $r->getPost('unit_price'),
            'default_quantity' => (float) ($r->getPost('default_quantity') ?: 1),
            'discount_type'    => $r->getPost('discount_type') === '$' ? '$' : '%',
            'discount_value'   => (float) $r->getPost('discount_value'),
            'tax_percent'      => (float) $r->getPost('tax_percent'),
            'details_url'      => trim((string) $r->getPost('details_url')),
            'is_active'        => $r->getPost('is_active') ? 1 : 0,
        ];

        // La foto solo se toca si suben una nueva: editar el resto no la borra.
        $photo = (new \App\Libraries\UploadService())->storeImage($r->getFile('photo'), 'servicios', 'producto');
        if ($photo) {
            $data['photo'] = $photo;
        }

        return $data;
    }

    /** El código es único dentro de la empresa (no globalmente). */
    protected function extraCheck(array $data, ?array $existing): ?string
    {
        /** @var ProductModel $model */
        $model = $this->model;
        if ($model->codeTaken((int) $data['company_id'], $data['code'], $existing['id'] ?? null)) {
            return lang('Quo.errCodeTaken');
        }

        return null;
    }

    /** GET servicios/productos/plantilla — la plantilla del importador. */
    public function template()
    {
        return $this->response
            ->setContentType('text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="plantilla-productos.csv"')
            ->setBody((new ProductImporter())->template());
    }

    /** GET|POST servicios/productos/importar — importación masiva. */
    public function import()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to($this->route)->with('error', lang('Quo.errNoCompany'));
        }

        $result = null;

        if (strtolower($this->request->getMethod()) === 'post') {
            $file = $this->request->getFile('file');

            if (!$file || !$file->isValid()) {
                return redirect()->back()->with('error', lang('Quo.impErrFile'));
            }
            if (!in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
                return redirect()->back()->with('error', lang('Quo.impErrType'));
            }

            $result = (new ProductImporter())->import($file, $companyId);
        }

        return view('Components\Servicios\Views\products\import', [
            'title'      => lang('Quo.import'),
            'result'     => $result,
            'categories' => (new CategoryModel())->mapFor($companyId),
        ]);
    }

    /** POST servicios/productos/rapido — alta desde el cotizador (JSON). */
    public function quickStore()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return $this->response->setJSON(['ok' => false, 'message' => lang('Quo.errNoCompany')]);
        }

        $data = $this->fields($companyId);
        if ($msg = $this->extraCheck($data, null)) {
            return $this->response->setJSON(['ok' => false, 'message' => $msg]);
        }
        if (!$this->model->insert($data)) {
            return $this->response->setJSON(['ok' => false, 'message' => implode(' ', $this->model->errors())]);
        }

        $data['id'] = (int) $this->model->getInsertID();

        return $this->response->setJSON(['ok' => true, 'product' => $data]);
    }
}
