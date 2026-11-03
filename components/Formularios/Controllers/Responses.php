<?php

namespace Components\Formularios\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Formularios\Libraries\AttachmentService;
use Components\Formularios\Libraries\ExportService;
use Components\Formularios\Libraries\FormService;
use Components\Formularios\Libraries\ReportService;
use Components\Formularios\Libraries\ResponseService;
use Components\Formularios\Models\AttachmentModel;
use Components\Formularios\Models\FormModel;
use Components\Formularios\Models\ResponseModel;

/**
 * Respuestas: bandeja, detalle, informe, descarga de adjuntos y exportación.
 *
 * Cada método comprueba que la respuesta o el formulario sean DE LA EMPRESA
 * ACTIVA. Tener `frm.view` autoriza a ver respuestas; no a ver las de otra
 * empresa, y el id va en la URL.
 */
class Responses extends BaseController
{
    private ResponseModel $responses;
    private FormModel $forms;
    private FormService $svc;

    public function __construct()
    {
        $this->responses = new ResponseModel();
        $this->forms     = new FormModel();
        $this->svc       = new FormService();
    }

    private function companyId(): int
    {
        return (int) (session('active_company_id') ?? 0);
    }

    private function ownForm(int $id): ?array
    {
        $companyId = $this->companyId();

        return $companyId ? $this->forms->ofCompany($id, $companyId) : null;
    }

    private function ownResponse(int $id): ?array
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return null;
        }
        $row = $this->responses->find($id);

        return ($row && (int) $row['company_id'] === $companyId) ? $row : null;
    }

    /**
     * Bandeja general: todas las respuestas de la empresa.
     *
     * Admite `?form=<id>` para acotarla a un formulario sin salir de aquí. El id
     * se comprueba contra la empresa activa: uno de otra empresa no filtra nada,
     * cae a la bandeja completa.
     */
    public function all()
    {
        $pick = (int) ($this->request->getGet('form') ?? 0);
        $form = $pick > 0 ? $this->ownForm($pick) : null;

        return $this->listing($form, $form !== null);
    }

    /** Las de un formulario concreto. */
    public function index($formId = null)
    {
        $form = $this->ownForm((int) $formId);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }

        return $this->listing($form);
    }

    /**
     * @param array|null $form      formulario al que se acota, o null
     * @param bool       $filtered  true si se llegó por el filtro de la bandeja
     *                              general (cambia a dónde vuelven los enlaces)
     */
    private function listing(?array $form, bool $filtered = false)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Formularios\Views\responses', [
                'title' => lang('Frm.responses'), 'dt' => null, 'form' => null,
            ]);
        }

        $formId = $form ? (int) $form['id'] : 0;

        // Con el filtro, la paginación y el orden siguen viviendo en la bandeja
        // general; con la ruta por formulario, en la suya.
        $base = ($form && !$filtered) ? 'formularios/respuestas/' . $formId : 'formularios/respuestas';

        $dt = (new DataTable($this->responses, $this->request))
            ->searchable(['reference'])
            ->sortable(['reference', 'created_at', 'device'])
            ->defaultSort('created_at', 'desc')
            ->query(static function ($m) use ($companyId, $formId) {
                $m->where('company_id', $companyId);
                if ($formId) {
                    $m->where('form_id', $formId);
                }
            })
            ->paginate(20);

        // Los títulos de los formularios de esta página, en una consulta.
        $titles = [];
        $ids    = array_unique(array_column($dt['rows'], 'form_id'));
        if ($ids) {
            foreach ($this->forms->whereIn('id', $ids)->findAll() as $f) {
                $titles[(int) $f['id']] = (string) $f['title'];
            }
        }

        return view('Components\Formularios\Views\responses', [
            'title'    => lang('Frm.responses'),
            'dt'       => $dt,
            'form'     => $form,
            'filtered' => $filtered,
            'titles'   => $titles,
            'baseUrl'  => base_url($base),
            // El selector lista TODOS los formularios de la empresa, no solo los
            // que ya tienen respuestas: si no, uno recién publicado no se podría
            // elegir para comprobar que aún no ha llegado ninguna.
            'forms'       => $this->forms->where('company_id', $companyId)->orderBy('title', 'asc')->findAll(),
            'extraParams' => $filtered ? ['form' => $formId] : [],
        ]);
    }

    public function show($id = null)
    {
        $row = $this->ownResponse((int) $id);
        if (!$row) {
            return redirect()->to('formularios/respuestas')->with('error', lang('Frm.notFound'));
        }

        $form = $this->forms->find((int) $row['form_id']);

        return view('Components\Formularios\Views\response_show', [
            'title'    => lang('Frm.responseDetail'),
            'response' => $row,
            'form'     => $form,
            'labels'   => is_array($row['labels'] ?? null) ? $row['labels'] : [],
            'values'   => is_array($row['data'] ?? null) ? $row['data'] : [],
            'files'    => (new AttachmentModel())->forResponse((int) $row['id']),
        ]);
    }

    public function report($formId = null)
    {
        $form = $this->ownForm((int) $formId);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }

        $schema = $this->svc->liveSchema($form);
        $report = new ReportService();

        return view('Components\Formularios\Views\report', [
            'title'   => lang('Frm.report'),
            'form'    => $form,
            'summary' => $report->summary($form),
            'stats'   => $report->fieldStats($schema, $form),
        ]);
    }

    /**
     * Descarga de un adjunto.
     *
     * Dos comprobaciones y no una: que exista y que sea de la empresa activa. El
     * permiso `frm.view` dice que puede ver adjuntos, no que pueda ver los de
     * otra empresa. Los archivos están fuera de `public/`, así que esta es la
     * única vía.
     */
    public function attachment($id = null)
    {
        $companyId = $this->companyId();
        $att       = (new AttachmentModel())->find((int) $id);

        if (!$att || !$companyId || (int) $att['company_id'] !== $companyId) {
            return redirect()->to('formularios/respuestas')->with('error', lang('Frm.notFound'));
        }

        $path = (new AttachmentService())->absolutePath($att);
        if (!is_file($path)) {
            return redirect()->back()->with('error', lang('Frm.fileMissing'));
        }

        return $this->response->download($path, null)->setFileName((string) $att['original_name']);
    }

    public function markRead($id = null)
    {
        $row = $this->ownResponse((int) $id);
        if (!$row) {
            return redirect()->to('formularios/respuestas')->with('error', lang('Frm.notFound'));
        }

        $this->responses->update((int) $row['id'], ['is_read' => (int) $row['is_read'] === 1 ? 0 : 1]);

        return redirect()->back();
    }

    public function delete($id = null)
    {
        $row = $this->ownResponse((int) $id);
        if (!$row) {
            return redirect()->to('formularios/respuestas')->with('error', lang('Frm.notFound'));
        }

        (new ResponseService())->deleteResponse((int) $row['id']);

        return redirect()->to('formularios/respuestas')->with('success', lang('Frm.responseDeleted'));
    }

    /**
     * Exportación de TODAS las respuestas de la empresa, de todos sus
     * formularios.
     *
     * La hoja sale dispersa a propósito —cada formulario tiene sus preguntas, y
     * una respuesta solo rellena las suyas—, con una columna «Formulario» para
     * poder separarlas. Para una hoja compacta, se filtra por formulario y se
     * exporta ese.
     */
    public function exportAll($format = 'csv')
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('formularios')->with('error', lang('Frm.noCompany'));
        }

        $titles = [];
        foreach ($this->forms->where('company_id', $companyId)->findAll() as $f) {
            $titles[(int) $f['id']] = (string) $f['title'];
        }

        $export = new ExportService();

        if ($format === 'json') {
            return $this->response
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $export->filenameAll('json') . '"')
                ->setBody($export->jsonAll($companyId, $titles));
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $export->filenameAll('csv') . '"')
            ->setBody($export->csvAll($companyId, $titles));
    }

    /** Exportación completa del formulario: CSV para Excel o JSON. */
    public function export($formId = null, $format = 'csv')
    {
        $form = $this->ownForm((int) $formId);
        if (!$form) {
            return redirect()->to('formularios')->with('error', lang('Frm.notFound'));
        }

        $schema = $this->svc->liveSchema($form);
        $export = new ExportService();

        if ($format === 'json') {
            return $this->response
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $export->filename($form, 'json') . '"')
                ->setBody($export->json($form, $schema));
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $export->filename($form, 'csv') . '"')
            ->setBody($export->csv($form, $schema));
    }
}
