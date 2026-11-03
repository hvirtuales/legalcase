<?php

namespace Components\Paginas\Controllers;

use App\Controllers\BaseController;
use Components\Paginas\Libraries\BlockRegistry;
use Components\Paginas\Libraries\PageService;
use Components\Paginas\Libraries\Renderer;
use Components\Paginas\Models\BlockModel;

/**
 * Bloques personalizados: fragmentos guardados con nombre para volver a
 * soltarlos en cualquier página.
 *
 * El alta llega **desde el editor** por JSON (botón «Guardar como bloque» de
 * cada bloque del lienzo), así que `store()` responde JSON y no redirige. El
 * listado y la baja sí son pantallas normales.
 */
class Blocks extends BaseController
{
    private BlockModel $model;
    private PageService $svc;

    public function __construct()
    {
        $this->model = new BlockModel();
        $this->svc   = new PageService();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    private function own(int $id): ?array
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return null;
        }
        $row = $this->model->find($id);

        return $row && (int) $row['company_id'] === $companyId ? $row : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Paginas\Views\blocks', ['title' => lang('Pag.blocks'), 'rows' => null]);
        }

        $rows     = $this->model->forCompany($companyId);
        $registry = BlockRegistry::types();
        $company  = (new \App\Models\CompanyModel())->find($companyId);

        return view('Components\Paginas\Views\blocks', [
            'title'    => lang('Pag.blocks'),
            'rows'     => $rows,
            // Cada bloque guardado se muestra renderizado de verdad: es la única
            // forma de reconocerlo de un vistazo entre diez.
            'previews' => array_map(
                fn (array $b): string => (new Renderer([], ['editor' => true, 'company' => $company]))
                    ->render(is_array($b['blocks'] ?? null) ? $b['blocks'] : []),
                array_column($rows, null, 'id')
            ),
            'typeLabel' => static fn (string $t): string => isset($registry[$t]) ? lang($registry[$t]['label']) : $t,
        ]);
    }

    /**
     * POST bloques/guardar — llega del editor con `{name, blocks:[…]}`.
     *
     * El fragmento pasa por el mismo saneado que una página: un bloque guardado
     * se vuelve a insertar tal cual en otras páginas, así que no puede ser la
     * puerta trasera por la que entre un árbol sin validar.
     */
    public function store()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return $this->response->setStatusCode(400)
                ->setJSON(['ok' => false, 'error' => lang('Pag.errNoCompany')]);
        }

        $raw  = $this->request->getJSON(true);
        $name = trim((string) ($raw['name'] ?? ''));
        $tree = $this->svc->sanitizeTree($raw['blocks'] ?? []);

        if ($name === '' || !$tree) {
            return $this->response->setStatusCode(422)
                ->setJSON(['ok' => false, 'error' => lang('Pag.errBlockEmpty'), 'csrf' => csrf_hash()]);
        }

        $ok = $this->model->insert([
            'company_id' => $companyId,
            'user_id'    => (int) session()->get('user_id'),
            'name'       => mb_substr($name, 0, 120),
            'root_type'  => (string) ($tree[0]['type'] ?? 'section'),
            'blocks'     => $tree,
        ]);

        if (!$ok) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'    => false,
                'error' => implode(' ', $this->model->errors()),
                'csrf'  => csrf_hash(),
            ]);
        }

        $id = (int) $this->model->getInsertID();

        return $this->response->setJSON([
            'ok'    => true,
            'block' => ['id' => $id, 'name' => $name, 'blocks' => $tree],
            'csrf'  => csrf_hash(),
        ]);
    }

    public function delete($id = null)
    {
        $row = $this->own((int) $id);
        if (!$row) {
            return redirect()->to('paginas/bloques')->with('error', lang('Pag.errBlockNotFound'));
        }
        $this->model->delete((int) $row['id']);

        return redirect()->to('paginas/bloques')->with('success', lang('Pag.okBlockDeleted'));
    }
}
