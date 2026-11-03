<?php

namespace Components\Formularios\Controllers;

use App\Controllers\BaseController;
use Components\Formularios\Libraries\FormService;
use Components\Formularios\Libraries\PlanGuard;
use Components\Formularios\Libraries\TemplateLibrary;
use Components\Formularios\Models\FormModel;
use Components\Formularios\Models\TemplateModel;

/**
 * Plantillas: las cuatro de arranque (globales) y las que cada empresa guarda
 * desde sus propios formularios.
 */
class Templates extends BaseController
{
    private TemplateModel $templates;

    public function __construct()
    {
        $this->templates = new TemplateModel();
    }

    private function companyId(): int
    {
        return (int) (session('active_company_id') ?? 0);
    }

    public function index()
    {
        $companyId = $this->companyId();

        return view('Components\Formularios\Views\templates', [
            'title'     => lang('Frm.templates'),
            'templates' => $companyId ? $this->templates->available($companyId) : [],
            'forms'     => $companyId
                ? (new FormModel())->where('company_id', $companyId)->orderBy('title', 'asc')->findAll()
                : [],
            'companyId' => $companyId,
        ]);
    }

    /** Crea un formulario nuevo a partir de una plantilla. Consume cupo. */
    public function useTemplate($id = null)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('formularios')->with('error', lang('Frm.noCompany'));
        }

        $tpl = $this->templates->usableBy((int) $id, $companyId);
        if (!$tpl) {
            return redirect()->to('formularios/plantillas')->with('error', lang('Frm.notFound'));
        }

        $guard = new PlanGuard();
        if (($reason = $guard->blockedReason($companyId)) !== null) {
            return redirect()->to('formularios')->with('error', $reason);
        }

        $display = (string) ($this->request->getPost('display') ?? 'card');

        $newId = (new FormService())->create(
            $companyId,
            (int) (session('user_id') ?? 0) ?: null,
            (string) $tpl['name'],
            $display,
            is_array($tpl['schema_json'] ?? null) ? $tpl['schema_json'] : []
        );

        return redirect()->to('formularios/editor/' . $newId)->with('success', lang('Frm.created'));
    }

    /** Guarda un formulario existente como plantilla de la empresa. */
    public function fromForm($id = null)
    {
        $companyId = $this->companyId();
        $form      = $companyId ? (new FormModel())->ofCompany((int) $id, $companyId) : null;

        if (!$form) {
            return redirect()->to('formularios/plantillas')->with('error', lang('Frm.notFound'));
        }

        $this->templates->insert([
            'company_id'  => $companyId,
            'code'        => '',
            'name'        => mb_substr((string) $form['title'], 0, 180),
            'description' => mb_substr((string) $this->request->getPost('description'), 0, 255),
            'category'    => lang('Frm.tplOwn'),
            'schema_json' => (new FormService())->sanitizeTree($form['schema_json'] ?? []),
            'is_active'   => 1,
        ]);

        return redirect()->to('formularios/plantillas')->with('success', lang('Frm.tplSaved'));
    }

    /** Siembra o repone las cuatro de arranque. Idempotente por `code`. */
    public function seed()
    {
        $n = TemplateLibrary::seed();

        return redirect()->to('formularios/plantillas')->with('success', lang('Frm.tplSeeded', [$n]));
    }

    /**
     * Borra una plantilla DE LA EMPRESA. Las globales no se borran desde aquí:
     * son de la plataforma, y quitarlas para todos desde el panel de una
     * empresa sería pasarse de alcance.
     */
    public function delete($id = null)
    {
        $companyId = $this->companyId();
        $tpl       = $this->templates->find((int) $id);

        if (!$tpl || $tpl['company_id'] === null || (int) $tpl['company_id'] !== $companyId) {
            return redirect()->to('formularios/plantillas')->with('error', lang('Frm.tplNotOwn'));
        }

        $this->templates->delete((int) $tpl['id']);

        return redirect()->to('formularios/plantillas')->with('success', lang('Frm.tplDeleted'));
    }
}
