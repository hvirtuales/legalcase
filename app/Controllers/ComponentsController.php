<?php

namespace App\Controllers;

use App\Libraries\ComponentManager;

/**
 * Panel de gestión de componentes (tipo plugins de WordPress). Gated por
 * `permission:components.manage` (solo Super Admin por defecto).
 */
class ComponentsController extends BaseController
{
    private ComponentManager $mgr;

    public function __construct()
    {
        $this->mgr = new ComponentManager();
    }

    public function index()
    {
        return view('components/index', [
            'title'      => 'Componentes',
            'components' => $this->mgr->all(),
        ]);
    }

    public function show($folder = null)
    {
        $m = $this->mgr->get((string) $folder);
        if (!$m) {
            return redirect()->to('/components')->with('error', 'Componente no encontrado.');
        }
        $status = null;
        foreach ($this->mgr->all() as $c) {
            if ($c['_folder'] === $folder) {
                $status = $c;
            }
        }

        return view('components/show', ['title' => $m['name'] ?? $folder, 'm' => $m, 'status' => $status]);
    }

    public function install()
    {
        return $this->back($this->mgr->install($this->folder()));
    }

    public function activate()
    {
        return $this->back($this->mgr->activate($this->folder()));
    }

    public function deactivate()
    {
        return $this->back($this->mgr->deactivate($this->folder()));
    }

    public function update()
    {
        return $this->back($this->mgr->update($this->folder()));
    }

    public function uninstall()
    {
        $removeFiles = (bool) $this->request->getPost('remove_files');
        // Eliminar los archivos sin borrar los datos dejaría tablas huérfanas sin
        // forma de revertirlas (las migraciones se van con la carpeta).
        $drop = $removeFiles || (bool) $this->request->getPost('drop_data');

        return $this->back($this->mgr->uninstall($this->folder(), $drop, $removeFiles));
    }

    private function folder(): string
    {
        return trim((string) $this->request->getPost('folder'));
    }

    private function back(array $res)
    {
        return redirect()->to('/components')->with($res['ok'] ? 'success' : 'error', $res['message']);
    }
}
