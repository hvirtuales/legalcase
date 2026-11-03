<?php

namespace Components\Servicios\Controllers;

use App\Libraries\DataTable;
use Components\Servicios\Models\CategoryModel;
use Components\Servicios\Models\ProductModel;

/** Categorías con las que se clasifica el catálogo de productos. */
class Categories extends BaseCrud
{
    protected string $route   = 'servicios/categorias';
    protected string $viewDir = 'categories';

    public function __construct()
    {
        $this->model = new CategoryModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Servicios\Views\categories\index', ['title' => lang('Quo.categories'), 'dt' => null, 'counts' => []]);
        }

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'description'])
            ->sortable(['name', 'sort_order'])
            ->defaultSort('sort_order', 'asc')
            ->query(static fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        /** @var CategoryModel $model */
        $model = $this->model;

        return view('Components\Servicios\Views\categories\index', [
            'title'  => lang('Quo.categories'),
            'dt'     => $dt,
            'counts' => $model->productCounts($companyId),
        ]);
    }

    protected function fields(int $companyId): array
    {
        $r = $this->request;

        return [
            'company_id'  => $companyId,
            'name'        => trim((string) $r->getPost('name')),
            'description' => trim((string) $r->getPost('description')),
            'sort_order'  => (int) $r->getPost('sort_order'),
            'is_active'   => $r->getPost('is_active') ? 1 : 0,
        ];
    }

    /** El nombre no se repite dentro de la empresa (ignorando mayúsculas). */
    protected function extraCheck(array $data, ?array $existing): ?string
    {
        /** @var CategoryModel $model */
        $model = $this->model;

        return $model->nameTaken((int) $data['company_id'], $data['name'], $existing['id'] ?? null)
            ? lang('Quo.errCategoryTaken')
            : null;
    }

    /**
     * Al borrar una categoría los productos NO se borran: se quedan sin
     * clasificar. La FK ya es `SET NULL`, pero se desclasifican explícitamente
     * para que el soft-delete de la categoría también los libere.
     */
    public function delete($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to($this->route)->with('error', lang('Quo.errNotFound'));
        }

        (new ProductModel())
            ->where('company_id', $row['company_id'])
            ->where('category_id', $row['id'])
            ->set('category_id', null)
            ->update();

        $this->model->delete((int) $row['id']);

        return redirect()->to($this->route)->with('success', lang('Quo.okCategoryDeleted'));
    }

    /** POST servicios/categorias/rapido — alta desde el formulario de producto (JSON). */
    public function quickStore()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return $this->response->setJSON(['ok' => false, 'message' => lang('Quo.errNoCompany')]);
        }

        $data = $this->fields($companyId);
        if ($data['name'] === '') {
            return $this->response->setJSON(['ok' => false, 'message' => lang('Quo.errCategoryName')]);
        }
        if ($msg = $this->extraCheck($data, null)) {
            return $this->response->setJSON(['ok' => false, 'message' => $msg]);
        }
        if (!$this->model->insert($data)) {
            return $this->response->setJSON(['ok' => false, 'message' => implode(' ', $this->model->errors())]);
        }

        return $this->response->setJSON([
            'ok'       => true,
            'category' => ['id' => (int) $this->model->getInsertID(), 'name' => $data['name']],
        ]);
    }
}
