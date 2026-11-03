<?php

namespace Components\Mailer\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Mailer\Libraries\DesignSanitizer;
use Components\Mailer\Libraries\EmailRegistry;
use Components\Mailer\Libraries\EmailRenderer;
use Components\Mailer\Libraries\MailerService;
use Components\Mailer\Models\TemplateModel;

/** Gestión de plantillas de correo (HTML). Aisladas por empresa activa. */
class Templates extends BaseController
{
    private TemplateModel $model;

    public function __construct()
    {
        $this->model = new TemplateModel();
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
            return view('Components\Mailer\Views\templates\index', ['title' => lang('Mailer.templates'), 'dt' => null]);
        }
        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['name', 'subject'])
            ->sortable(['name', 'created_at'])
            ->defaultSort('name', 'asc')
            ->query(fn ($m) => $m->where('company_id', $companyId))
            ->paginate(15);

        return view('Components\Mailer\Views\templates\index', ['title' => lang('Mailer.templates'), 'dt' => $dt]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('mailer/plantillas')->with('error', lang('Mailer.mNoCompany'));
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save(null, $companyId);
        }

        return view('Components\Mailer\Views\templates\form', $this->formData($companyId, []));
    }

    public function edit($id)
    {
        $tpl = $this->owned($id);
        if (!$tpl) {
            return redirect()->to('mailer/plantillas')->with('error', lang('Mailer.mTplNotFound'));
        }
        if ($this->request->getMethod() === 'POST') {
            return $this->save((int) $id, (int) $tpl['company_id']);
        }

        return view('Components\Mailer\Views\templates\form', $this->formData((int) $tpl['company_id'], $tpl));
    }

    /** Lo que necesita el constructor de correos, igual que en el asistente. */
    private function formData(int $companyId, array $tpl): array
    {
        return [
            'title'    => $tpl ? lang('Mailer.editTemplate') : lang('Mailer.newTemplate'),
            'tpl'      => $tpl,
            'company'  => (new \App\Models\CompanyModel())->find($companyId) ?? [],
            'registry' => EmailRegistry::forJs(),
            'groups'   => EmailRegistry::groupLabels(),
        ];
    }
    private function save(?int $id, int $companyId)
    {
        // El HTML lo produce el SERVIDOR a partir del árbol, igual que en una
        // campaña: del navegador solo llega el diseño, que se sanea antes.
        $design = (new DesignSanitizer())->clean((string) $this->request->getPost('design_json'));
        $html   = (new EmailRenderer((new \App\Models\CompanyModel())->find($companyId) ?? []))->render($design);

        $data = [
            'company_id'  => $companyId,
            'name'        => trim((string) $this->request->getPost('name')),
            'subject'     => trim((string) $this->request->getPost('subject')) ?: null,
            'body_html'   => $html,
            'design_json' => json_encode($design, JSON_UNESCAPED_UNICODE),
        ];
        $ok = $id ? $this->model->update($id, $data) : $this->model->insert($data);
        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('mailer/plantillas')->with('success', lang('Mailer.mTplSaved'));
    }

    public function delete($id)
    {
        if (!$this->owned($id)) {
            return redirect()->to('mailer/plantillas')->with('error', lang('Mailer.mTplNotFound'));
        }
        $this->model->delete($id);

        return redirect()->to('mailer/plantillas')->with('success', lang('Mailer.mTplDeleted'));
    }

    /** Vista previa del correo de la plantilla (HTML real con datos de muestra) — para iframe/pestaña. */
    public function preview($id)
    {
        $tpl = $this->owned($id);
        if (!$tpl) {
            return $this->response->setStatusCode(404)->setBody(lang('Mailer.mTplNotFound'));
        }
        $body = (new MailerService())->previewHtml($this->pseudoCampaign($tpl));
        $html = '<!doctype html><html lang="' . esc(lang('Mailer.htmlLang'), 'attr') . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . esc(lang('Mailer.previewPage')) . '</title></head><body style="margin:0;padding:0">' . $body . '</body></html>';

        return $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8')->setBody($html);
    }

    /** Envía un correo de prueba con esta plantilla a una dirección. */
    public function test($id)
    {
        $tpl = $this->owned($id);
        if (!$tpl) {
            return redirect()->to('mailer/plantillas')->with('error', lang('Mailer.mTplNotFound'));
        }
        $to = strtolower(trim((string) $this->request->getPost('test_email')));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->to('mailer/plantillas/editar/' . $id)->with('error', lang('Mailer.mTestBadEmail'));
        }
        if (trim((string) $tpl['body_html']) === '') {
            return redirect()->to('mailer/plantillas/editar/' . $id)->with('error', lang('Mailer.mTplNoBody'));
        }

        $res = (new MailerService())->sendTest($this->pseudoCampaign($tpl), $to);
        if ($res['ok']) {
            return redirect()->to('mailer/plantillas/editar/' . $id)->with('success', lang('Mailer.mTestSent', [$to]));
        }

        return redirect()->to('mailer/plantillas/editar/' . $id)->with('error', lang('Mailer.mTestFail', [$res['message']]));
    }

    /** Estructura mínima tipo campaña para reutilizar el render/envío de MailerService. */
    private function pseudoCampaign(array $tpl): array
    {
        return [
            'company_id' => (int) $tpl['company_id'],
            'subject'    => $tpl['subject'] ?: ('Vista previa: ' . $tpl['name']),
            'body_html'  => (string) $tpl['body_html'],
            'from_name'  => null,
            'from_email' => null,
        ];
    }

    private function owned($id): ?array
    {
        $companyId = $this->companyId();
        $tpl       = $this->model->find($id);

        return ($tpl && (int) $tpl['company_id'] === $companyId) ? $tpl : null;
    }
}
