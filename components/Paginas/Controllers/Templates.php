<?php

namespace Components\Paginas\Controllers;

use App\Controllers\BaseController;
use Components\Paginas\Libraries\PageService;
use Components\Paginas\Libraries\PlanGuard;
use Components\Paginas\Libraries\Renderer;
use Components\Paginas\Libraries\TemplateLibrary;
use Components\Paginas\Models\PageModel;
use Components\Paginas\Models\TemplateModel;

/**
 * Plantillas reutilizables: crear una página a partir de una, guardar una página
 * como plantilla y recuperar las de arranque.
 *
 * Una plantilla se puede **usar** aunque sea global, pero solo se puede
 * **borrar** si es de la empresa: `own()` distingue las dos cosas.
 */
class Templates extends BaseController
{
    private TemplateModel $model;
    private PageService $svc;

    public function __construct()
    {
        $this->model = new TemplateModel();
        $this->svc   = new PageService();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** Plantilla que la empresa puede USAR: la suya o una de arranque. */
    private function usable(int $id): ?array
    {
        $companyId = $this->companyId();
        $row       = $this->model->find($id);
        if (!$row) {
            return null;
        }
        $owner = $row['company_id'] === null ? null : (int) $row['company_id'];

        return ($owner === null || $owner === $companyId) ? $row : null;
    }

    /** Plantilla que la empresa puede BORRAR: solo la suya (las de arranque no). */
    private function own(int $id): ?array
    {
        $companyId = $this->companyId();
        $row       = $this->model->find($id);

        return $row && $companyId && (int) ($row['company_id'] ?? 0) === $companyId ? $row : null;
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Paginas\Views\templates', ['title' => lang('Pag.templates'), 'rows' => null]);
        }

        $lib = new TemplateLibrary();

        return view('Components\Paginas\Views\templates', [
            'title'       => lang('Pag.templates'),
            'rows'        => $this->model->forCompany($companyId),
            'companyId'   => $companyId,
            'countBlocks' => fn (array $t): int => $this->svc->countBlocks(is_array($t['blocks'] ?? null) ? $t['blocks'] : []),
            // Solo se ofrece sembrar si aún no están: la siembra es idempotente,
            // pero el botón no debe invitar a repetir algo ya hecho.
            'canSeed'     => !$lib->seeded(),
            'pages'       => (new PageModel())->where('company_id', $companyId)->orderBy('title', 'asc')->findAll(),
        ]);
    }

    /** Vista previa de la plantilla, con el renderizador de verdad. */
    public function preview($id = null)
    {
        $tpl = $this->usable((int) $id);
        if (!$tpl) {
            return redirect()->to('paginas/plantillas')->with('error', lang('Pag.errTemplateNotFound'));
        }

        $settings = is_array($tpl['settings'] ?? null) ? $tpl['settings'] : [];
        $company  = $this->companyId() ? (new \App\Models\CompanyModel())->find($this->companyId()) : null;

        return view('Components\Paginas\Views\public\page', [
            'page'      => ['title' => $tpl['name'], 'meta_description' => $tpl['description'], 'og_image' => null],
            'company'   => $company,
            'settings'  => $settings,
            // Con la empresa en el contexto, la vista previa de una plantilla
            // que use bloques de empresa se ve ya con la marca de quien mira.
            'body'      => (new Renderer($settings, ['editor' => true, 'company' => $company]))
                ->render(is_array($tpl['blocks'] ?? null) ? $tpl['blocks'] : []),
            'isPreview' => true,
        ]);
    }

    /** POST plantillas/usar/{id} — nace una página con el árbol de la plantilla. */
    public function useTemplate($id = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('paginas/plantillas')->with('error', lang('Pag.errNoCompany'));
        }

        $tpl = $this->usable((int) $id);
        if (!$tpl) {
            return redirect()->to('paginas/plantillas')->with('error', lang('Pag.errTemplateNotFound'));
        }

        if ($reason = (new PlanGuard())->blockedReason($companyId)) {
            return redirect()->to('paginas/plantillas')->with('error', $reason);
        }

        $title = trim((string) $this->request->getPost('title')) ?: $tpl['name'];
        $pages = new PageModel();

        $pages->insert([
            'company_id'       => $companyId,
            'user_id'          => (int) session()->get('user_id'),
            'title'            => mb_substr($title, 0, 180),
            'slug'             => $this->svc->uniqueSlug($companyId, $title),
            'status'           => 'draft',
            // Ids nuevos: la plantilla se usa muchas veces y sus bloques no
            // pueden compartir identificador entre páginas.
            'blocks'           => $this->svc->reindex(is_array($tpl['blocks'] ?? null) ? $tpl['blocks'] : []),
            'settings'         => is_array($tpl['settings'] ?? null) ? $tpl['settings'] : [],
            'meta_title'       => '',
            'meta_description' => '',
        ]);

        return redirect()->to('paginas/editor/' . (int) $pages->getInsertID())
            ->with('success', lang('Pag.okFromTemplate'));
    }

    /** POST plantillas/desde/{id} — guarda una página existente como plantilla. */
    public function fromPage($id = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('paginas/plantillas')->with('error', lang('Pag.errNoCompany'));
        }

        $page = (new PageModel())->find((int) $id);
        if (!$page || (int) $page['company_id'] !== $companyId) {
            return redirect()->to('paginas/plantillas')->with('error', lang('Pag.errNotFound'));
        }

        $name = trim((string) $this->request->getPost('name')) ?: $page['title'];

        $ok = $this->model->insert([
            'company_id'  => $companyId,
            'code'        => null,
            'name'        => mb_substr($name, 0, 120),
            'description' => mb_substr(trim((string) $this->request->getPost('description')), 0, 255),
            'category'    => 'propia',
            'blocks'      => is_array($page['blocks'] ?? null) ? $page['blocks'] : [],
            'settings'    => is_array($page['settings'] ?? null) ? $page['settings'] : [],
            'sort_order'  => 0,
        ]);

        if (!$ok) {
            return redirect()->back()->with('errors', $this->model->errors());
        }

        return redirect()->to('paginas/plantillas')->with('success', lang('Pag.okTemplateSaved'));
    }

    /** POST plantillas/sembrar — recupera las plantillas de arranque. */
    public function seed()
    {
        $n = (new TemplateLibrary())->seed();

        return redirect()->to('paginas/plantillas')->with('success', lang('Pag.okSeeded', [$n]));
    }

    public function delete($id = null)
    {
        $tpl = $this->own((int) $id);
        if (!$tpl) {
            // Incluye el caso «es de arranque»: esas no se borran desde aquí.
            return redirect()->to('paginas/plantillas')->with('error', lang('Pag.errTemplateNotOwn'));
        }
        $this->model->delete((int) $tpl['id']);

        return redirect()->to('paginas/plantillas')->with('success', lang('Pag.okTemplateDeleted'));
    }
}
