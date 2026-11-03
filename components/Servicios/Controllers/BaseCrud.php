<?php

namespace Components\Servicios\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Model;

/**
 * Base de los tres maestros (clientes, productos, vendedores): comparten el mismo
 * ciclo listar → crear → editar → eliminar, siempre acotado a la empresa activa.
 *
 * Las subclases solo declaran su modelo, su ruta y cómo se leen sus campos.
 */
abstract class BaseCrud extends BaseController
{
    protected Model $model;
    protected string $route;   // p. ej. 'servicios/clientes'
    protected string $viewDir; // p. ej. 'clients'

    protected function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** Registro de la empresa activa, o null (el permiso no basta: hay que ser dueño). */
    protected function own(int $id): ?array
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return null;
        }
        $row = $this->model->find($id);

        return $row && (int) $row['company_id'] === $companyId ? $row : null;
    }

    /** Campos del formulario, ya saneados. */
    abstract protected function fields(int $companyId): array;

    /** Validación extra propia del maestro. @return string|null mensaje de error */
    protected function extraCheck(array $data, ?array $existing): ?string
    {
        return null;
    }

    /** Datos extra que el formulario del maestro necesite (listas, catálogos…). */
    protected function formData(int $companyId, ?array $row = null): array
    {
        return [];
    }

    /** Guardado de lo que no vive en la propia tabla (relaciones, archivos…). */
    protected function afterSave(int $id, int $companyId): void
    {
    }

    abstract public function index();

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to($this->route)->with('error', lang('Quo.errNoCompany'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store(null, $companyId);
        }

        return view('Components\Servicios\Views\\' . $this->viewDir . '\form', [
            'title'  => lang('Quo.' . $this->viewDir . 'New'),
            'row'    => [],
            'action' => base_url($this->route . '/nuevo'),
        ] + $this->formData($companyId));
    }

    public function edit($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to($this->route)->with('error', lang('Quo.errNotFound'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store($row, (int) $row['company_id']);
        }

        return view('Components\Servicios\Views\\' . $this->viewDir . '\form', [
            'title'  => lang('Quo.' . $this->viewDir . 'Edit'),
            'row'    => $row,
            'action' => base_url($this->route . '/editar/' . (int) $row['id']),
        ] + $this->formData((int) $row['company_id'], $row));
    }

    protected function store(?array $existing, int $companyId)
    {
        $data = $this->fields($companyId);

        if ($msg = $this->extraCheck($data, $existing)) {
            return redirect()->back()->withInput()->with('error', $msg);
        }

        $ok = $existing
            ? $this->model->update((int) $existing['id'], $data)
            : $this->model->insert($data);

        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        $this->afterSave((int) ($existing['id'] ?? $this->model->getInsertID()), $companyId);

        return redirect()->to($this->route)
            ->with('success', $existing ? lang('Quo.okUpdated') : lang('Quo.okCreated'));
    }

    public function delete($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to($this->route)->with('error', lang('Quo.errNotFound'));
        }
        $this->model->delete((int) $row['id']);

        return redirect()->to($this->route)->with('success', lang('Quo.okDeleted'));
    }
}
