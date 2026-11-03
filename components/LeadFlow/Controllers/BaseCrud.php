<?php

namespace Components\LeadFlow\Controllers;

use CodeIgniter\Model;

/**
 * Base de los catálogos simples (fuentes, campañas, servicios): listar → crear →
 * editar → eliminar, siempre acotado a la empresa activa y buscando cada fila con
 * `owned()`. Cambiar el id en la URL o en el POST no llega a la fila de otra firma.
 */
abstract class BaseCrud extends BaseLfl
{
    protected Model $model;
    protected string $route   = '';
    protected string $viewDir = '';
    protected string $langKey = '';

    abstract protected function fields(int $companyId): array;

    protected function formData(int $companyId, ?array $row): array
    {
        return [];
    }

    abstract public function index();

    public function create()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound($this->route);
        }
        if ($this->isPost()) {
            return $this->store(null, $cid);
        }

        return $this->form(lang('Lfl.' . $this->langKey . 'New'), [], base_url($this->route . '/nuevo'), $cid);
    }

    public function edit($id = null)
    {
        $cid = $this->companyId();
        $row = $cid ? $this->model->owned((int) $id, $cid) : null;
        if (! $row) {
            return $this->notFound($this->route);
        }
        if ($this->isPost()) {
            return $this->store($row, $cid);
        }

        return $this->form(lang('Lfl.' . $this->langKey . 'Edit'), $row, base_url($this->route . '/editar/' . (int) $row['id']), $cid);
    }

    private function form(string $title, array $row, string $action, int $cid)
    {
        return $this->render($this->viewDir . '/form', ['title' => $title, 'row' => $row, 'action' => $action] + $this->formData($cid, $row ?: null));
    }

    private function store(?array $existing, int $cid)
    {
        $data = $this->fields($cid) + ['company_id' => $cid];
        $ok   = $existing ? $this->model->update((int) $existing['id'], $data) : $this->model->insert($data);
        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->flash('success', lang($existing ? 'Lfl.okUpdated' : 'Lfl.okCreated'));

        return redirect()->to(base_url($this->route));
    }

    public function delete($id = null)
    {
        $cid = $this->companyId();
        $row = $cid ? $this->model->owned((int) $id, $cid) : null;
        if (! $row) {
            return $this->notFound($this->route);
        }
        $this->model->delete((int) $row['id']);
        $this->flash('success', lang('Lfl.okDeleted'));

        return redirect()->to(base_url($this->route));
    }

    protected function postInt(string $name): ?int
    {
        $v = (int) $this->request->getPost($name);

        return $v > 0 ? $v : null;
    }
}
