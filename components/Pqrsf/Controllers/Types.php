<?php

namespace Components\Pqrsf\Controllers;

use App\Controllers\BaseController;
use Components\Pqrsf\Models\TypeModel;

/**
 * Catálogo de tipos de solicitud, por empresa.
 *
 * Cada tipo lleva su propio plazo de respuesta: es lo que decide el `due_at` de
 * lo que se radique con él. Desactivar un tipo lo retira del formulario público
 * sin tocar el histórico; borrarlo solo se permite si nadie lo usó.
 */
class Types extends BaseController
{
    private TypeModel $model;

    public function __construct()
    {
        $this->model = new TypeModel();
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
            return view('Components\Pqrsf\Views\types\index', ['title' => lang('Pqr.types'), 'rows' => null, 'usage' => []]);
        }

        return view('Components\Pqrsf\Views\types\index', [
            'title' => lang('Pqr.types'),
            'rows'  => $this->model->forCompany($companyId),
            'usage' => $this->model->usageFor($companyId),
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('pqrsf/tipos')->with('error', lang('Pqr.errNoCompany'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store(null, $companyId);
        }

        return view('Components\Pqrsf\Views\types\form', [
            'title' => lang('Pqr.typesNew'), 'row' => [], 'used' => 0,
            'action' => base_url('pqrsf/tipos/nuevo'),
        ]);
    }

    public function edit($id = null)
    {
        $companyId = $this->companyId();
        $row       = $companyId ? $this->model->own($companyId, (int) $id) : null;
        if (!$row) {
            return redirect()->to('pqrsf/tipos')->with('error', lang('Pqr.errNotFound'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store($row, $companyId);
        }

        return view('Components\Pqrsf\Views\types\form', [
            'title'  => lang('Pqr.typesEdit'),
            'row'    => $row,
            'used'   => (int) ($this->model->usageFor($companyId)[$row['code']] ?? 0),
            'action' => base_url('pqrsf/tipos/editar/' . (int) $row['id']),
        ]);
    }

    private function store(?array $existing, int $companyId)
    {
        $r    = $this->request;
        $name = trim((string) $r->getPost('name'));

        // El código identifica al tipo en lo ya radicado: se fija al crearlo y no
        // se vuelve a tocar. Cambiarlo dejaría solicitudes apuntando a la nada.
        $code = $existing
            ? (string) $existing['code']
            : $this->slug((string) ($r->getPost('code') ?: $name));

        if ($code === '') {
            return redirect()->back()->withInput()->with('errors', [lang('Pqr.errTypeCode')]);
        }
        if (!$existing && $this->model->codeTaken($companyId, $code)) {
            return redirect()->back()->withInput()->with('errors', [lang('Pqr.errTypeTaken', [$code])]);
        }

        $data = [
            'company_id' => $companyId,
            'code'       => $code,
            'name'       => $name,
            'days'       => max(1, (int) $r->getPost('days')),
            'sort_order' => max(0, (int) $r->getPost('sort_order')),
            'is_active'  => $r->getPost('is_active') ? 1 : 0,
        ];

        $ok = $existing
            ? $this->model->update((int) $existing['id'], $data)
            : $this->model->insert($data);

        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('pqrsf/tipos')
            ->with('success', $existing ? lang('Pqr.okTypeUpdated') : lang('Pqr.okTypeCreated'));
    }

    public function delete($id = null)
    {
        $companyId = $this->companyId();
        $row       = $companyId ? $this->model->own($companyId, (int) $id) : null;
        if (!$row) {
            return redirect()->to('pqrsf/tipos')->with('error', lang('Pqr.errNotFound'));
        }

        // Borrar un tipo en uso dejaría solicitudes con un código sin nombre. Para
        // dejar de ofrecerlo está el interruptor de activo.
        $used = (int) ($this->model->usageFor($companyId)[$row['code']] ?? 0);
        if ($used > 0) {
            return redirect()->to('pqrsf/tipos')->with('error', lang('Pqr.errTypeInUse', [$used]));
        }

        $this->model->delete((int) $row['id']);

        return redirect()->to('pqrsf/tipos')->with('success', lang('Pqr.okTypeDeleted'));
    }

    /** POST pqrsf/tipos/restaurar — vuelve a poner los cinco de siempre. */
    public function restore()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('pqrsf/tipos')->with('error', lang('Pqr.errNoCompany'));
        }

        $made = $this->model->seed($companyId);

        return redirect()->to('pqrsf/tipos')->with('success', lang('Pqr.okTypesSeeded', [$made]));
    }

    /** Código a partir del nombre: sin acentos, minúsculas y con guiones. */
    private function slug(string $text): string
    {
        $text = strtr(trim($text), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);

        return mb_substr(trim(strtolower((string) $text), '-'), 0, 20);
    }
}
