<?php

namespace Components\Copropiedad\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Model;
use Components\Copropiedad\Libraries\ImageOptimizer;

/**
 * Base de los maestros del componente (inmuebles, torres, personas, familias,
 * vehículos, mascotas, contactos, autorizaciones).
 *
 * Comparten exactamente el mismo ciclo —listar → crear → editar → eliminar, siempre
 * acotado a la empresa activa— así que vive una vez. Las subclases declaran su
 * modelo, su ruta y cómo se leen sus campos.
 *
 * **Lo que esta base garantiza y no se puede olvidar en una subclase:** que no se
 * entra sin empresa activa, y que **toda fila se busca con `owned()`**, es decir
 * comprobando que sea de esa empresa. Cambiar el id en la URL o en el POST para
 * editar el registro de otro conjunto no llega a ninguna parte, y hay prueba de ello.
 */
abstract class BaseCrud extends BaseController
{
    protected Model $model;

    /** Prefijo de ruta, p. ej. `copropiedad/inmuebles`. */
    protected string $route = '';

    /** Carpeta de vistas dentro de `Views/`, p. ej. `units`. */
    protected string $viewDir = '';

    /** Raíz de las claves de idioma: `Cop.<key>New`, `Cop.<key>Edit`. */
    protected string $langKey = '';

    protected function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** La fila, solo si es de la empresa activa. */
    protected function own(int $id): ?array
    {
        $companyId = $this->companyId();

        return $companyId ? $this->model->owned($id, $companyId) : null;
    }

    /** Campos del formulario, ya saneados. */
    abstract protected function fields(int $companyId): array;

    /** Comprobación propia del maestro. @return string|null mensaje de error */
    protected function extraCheck(array $data, ?array $existing): ?string
    {
        return null;
    }

    /** Datos extra que el formulario necesite (catálogos, selectores…). */
    protected function formData(int $companyId, ?array $row = null): array
    {
        return [];
    }

    /** Lo que hay que guardar fuera de la propia tabla. */
    protected function afterSave(int $id, int $companyId): void
    {
    }

    /**
     * A dónde ir después de **crear** (no de editar), si no es al listado.
     *
     * Existe porque en algunos maestros el alta no termina al guardar: crear una
     * persona sin decir dónde vive deja media ficha hecha, y devolver al listado
     * obliga a buscarla otra vez para acabar. Devolver la ruta de su ficha pone el
     * siguiente paso delante.
     */
    protected function afterCreateRoute(int $id): ?string
    {
        return null;
    }

    abstract public function index();

    public function create()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNoCompany'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store(null, $companyId);
        }

        return $this->form(lang('Cop.' . $this->langKey . 'New'), [], base_url($this->route . '/nuevo'), $companyId);
    }

    public function edit($id = null)
    {
        $row = $this->own((int) $id);
        if (! $row) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNotFound'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->store($row, (int) $row['company_id']);
        }

        return $this->form(
            lang('Cop.' . $this->langKey . 'Edit'),
            $row,
            base_url($this->route . '/editar/' . (int) $row['id']),
            (int) $row['company_id']
        );
    }

    private function form(string $title, array $row, string $action, int $companyId)
    {
        return view('Components\Copropiedad\Views\\' . $this->viewDir . '\form', [
            'title'  => $title,
            'row'    => $row,
            'action' => $action,
        ] + $this->formData($companyId, $row ?: null));
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

        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        $id = (int) ($existing['id'] ?? $this->model->getInsertID());
        $this->afterSave($id, $companyId);

        $destino = $existing ? null : $this->afterCreateRoute($id);

        return redirect()->to($destino ?? $this->route)
            ->with('success', $existing ? lang('Cop.okUpdated') : lang('Cop.okCreated'));
    }

    public function delete($id = null)
    {
        $row = $this->own((int) $id);
        if (! $row) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNotFound'));
        }
        $this->model->delete((int) $row['id']);

        return redirect()->to($this->route)->with('success', lang('Cop.okDeleted'));
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sube una foto y devuelve la referencia a guardar, o `null` si no vino ninguna.
     *
     * **Se llama a `storeImage()` con TRES argumentos a propósito** (contrato §15.2):
     * es la firma que tiene cualquier núcleo. Los que estrenaron un cuarto parámetro
     * para el ancho máximo también aceptan tres, así que la llamada vale en todos —y
     * el recorte lo hace después este componente con **su propia copia** del
     * optimizador, que es lo que le permite viajar (§15.1).
     */
    protected function uploadPhoto(string $field, string $folder, string $prefix): ?string
    {
        $file = $this->request->getFile($field);
        if (! $file || ! $file->isValid()) {
            return null;
        }

        $url = (new \App\Libraries\UploadService())->storeImage($file, $folder, $prefix);
        if (! $url || ! str_starts_with($url, '/uploads/')) {
            // Vacío, o almacenamiento remoto: no hay copia local que optimizar.
            return $url;
        }

        $local = FCPATH . ltrim($url, '/');
        if (! is_file($local)) {
            return $url;
        }

        $nuevo = (new ImageOptimizer())->optimize($local, ImageOptimizer::MAX_WIDTH);

        return $nuevo === $local ? $url : dirname($url) . '/' . basename($nuevo);
    }

    /** Valor de un campo del POST, recortado; `null` si queda vacío. */
    protected function str(string $name, ?int $max = null): ?string
    {
        $v = trim((string) $this->request->getPost($name));
        if ($v === '') {
            return null;
        }

        return $max ? mb_substr($v, 0, $max) : $v;
    }

    /** Entero del POST; `null` si es 0 o no vino (para las FK opcionales). */
    protected function int(string $name): ?int
    {
        $v = (int) $this->request->getPost($name);

        return $v > 0 ? $v : null;
    }

    /** Decimal del POST; `null` si vino vacío (0 y vacío NO son lo mismo). */
    protected function dec(string $name): ?float
    {
        $v = trim((string) $this->request->getPost($name));

        return $v === '' ? null : (float) str_replace(',', '.', $v);
    }

    /** Fecha del POST; `null` si vino vacía. */
    protected function date(string $name): ?string
    {
        $v = trim((string) $this->request->getPost($name));

        return $v === '' ? null : $v;
    }

    /** Casilla del POST como 0/1. */
    protected function flag(string $name): int
    {
        return $this->request->getPost($name) ? 1 : 0;
    }

    /** Valor de una lista cerrada; cae al primero si llega algo inventado. */
    protected function pick(string $name, array $allowed, ?string $default = null): string
    {
        $v = (string) $this->request->getPost($name);

        return in_array($v, $allowed, true) ? $v : ($default ?? $allowed[0]);
    }
}
