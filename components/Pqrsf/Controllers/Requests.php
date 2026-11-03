<?php

namespace Components\Pqrsf\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use App\Models\CompanyUserModel;
use Components\Pqrsf\Libraries\AttachmentService;
use Components\Pqrsf\Libraries\DemoData;
use Components\Pqrsf\Libraries\PlanGuard;
use Components\Pqrsf\Libraries\PqrService;
use Components\Pqrsf\Models\EventModel;
use Components\Pqrsf\Models\PointModel;
use Components\Pqrsf\Models\RequestModel;
use Components\Pqrsf\Models\SettingsModel;
use Components\Pqrsf\Models\TypeModel;

/** Bandeja y gestión de las solicitudes. */
class Requests extends BaseController
{
    private PqrService $service;
    private RequestModel $model;

    public function __construct()
    {
        $this->service = new PqrService();
        $this->model   = new RequestModel();
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
            return view('Components\Pqrsf\Views\requests\index', [
                'title' => lang('Pqr.inbox'), 'dt' => null, 'metrics' => [], 'points' => [], 'agents' => [], 'filters' => [],
            ]);
        }

        // Filtros: vacío = sin filtrar. `vencidas=1` es un filtro compuesto.
        $f = [
            'status'   => (string) $this->request->getGet('estado'),
            'type'     => (string) $this->request->getGet('tipo'),
            'point'    => (string) $this->request->getGet('punto'),
            'assignee' => (string) $this->request->getGet('responsable'),
            'late'     => (string) $this->request->getGet('vencidas'),
        ];

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['number', 'subject', 'message', 'citizen_name', 'citizen_doc'])
            ->sortable(['number', 'type', 'status', 'due_at', 'created_at'])
            ->defaultSort('id', 'desc')
            ->query(static function ($m) use ($companyId, $f) {
                $m->where('company_id', $companyId);
                if ($f['status'] !== '') {
                    $m->where('status', $f['status']);
                }
                if ($f['type'] !== '') {
                    $m->where('type', $f['type']);
                }
                if ($f['point'] !== '') {
                    $m->where('point_id', (int) $f['point']);
                }
                if ($f['assignee'] !== '') {
                    $f['assignee'] === '0' ? $m->where('assignee_id', null) : $m->where('assignee_id', (int) $f['assignee']);
                }
                if ($f['late'] === '1') {
                    $m->whereIn('status', ['recibida', 'en_gestion'])->where('due_at <', date('Y-m-d H:i:s'));
                }

                return $m;
            })
            ->paginate(15);

        return view('Components\Pqrsf\Views\requests\index', [
            'title'   => lang('Pqr.inbox'),
            'dt'      => $dt,
            'metrics' => $this->model->metrics($companyId),
            'points'  => (new PointModel())->mapFor($companyId),
            'agents'  => $this->agents($companyId),
            'filters' => $f,
            'types'   => (new TypeModel())->mapFor($companyId),
            // El botón de datos demo solo tiene sentido con la bandeja vacía.
            'showDemo' => (new DemoData())->isEmpty($companyId),
        ]);
    }

    /**
     * POST pqrsf/demo — llena la bandeja con solicitudes de ejemplo.
     * Solo si la empresa no tiene ninguna (no pisa datos reales) y respetando el
     * servicio y el cupo del plan.
     */
    public function seedDemo()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errNoCompany'));
        }

        $guard = new PlanGuard();
        if (!$guard->serviceEnabled($companyId)) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errPlanDisabled'));
        }

        $demo = new DemoData();
        if (!$demo->isEmpty($companyId)) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errDemoNotEmpty'));
        }

        $left = $guard->requestsLeft($companyId);
        if ($left !== null && $left < $demo->size()) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errDemoQuota'));
        }

        $res = $demo->generate($companyId, (int) session()->get('user_id'));

        return redirect()->to('pqrsf')->with('success', lang('Pqr.okDemo', [$res['requests'], $res['points']]));
    }

    /**
     * POST pqrsf/eliminar/{id} — borra una solicitud (soft-delete).
     *
     * Es lo que permite deshacer los datos de ejemplo, y en general retirar de la
     * bandeja lo que no debió entrar. El hilo se conserva colgando de la fila
     * borrada, para que quede el rastro de lo que se hizo.
     */
    public function delete($id = null)
    {
        [$row, $stop] = $this->owned($id);
        if ($stop) {
            return $stop;
        }

        $this->model->delete((int) $row['id']);

        return redirect()->to('pqrsf')->with('success', lang('Pqr.okDeleted', [$row['number']]));
    }

    /** Usuarios de la empresa a los que se puede asignar. */
    private function agents(int $companyId): array
    {
        $out = [];

        foreach ((new CompanyUserModel())->getUsers($companyId) as $u) {
            $out[(int) $u['id']] = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['username'] ?? '#' . $u['id']);
        }

        return $out;
    }

    public function show($id = null)
    {
        $companyId = $this->companyId();
        $row       = $companyId ? $this->model->own($companyId, (int) $id) : null;
        if (!$row) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errNotFound'));
        }

        $cfg = (new SettingsModel())->forCompany($companyId);

        return view('Components\Pqrsf\Views\requests\show', [
            'title'       => $row['number'],
            'row'         => $row,
            'thread'      => (new EventModel())->threadFor((int) $row['id']),
            'points'      => (new PointModel())->mapFor($companyId),
            'types'       => (new TypeModel())->mapFor($companyId),
            'agents'      => $this->agents($companyId),
            'channels'    => $this->service->channelsFor($row),
            'attachments' => (new AttachmentService())->listFor('request', (int) $row['id']),
            'answerLinks' => $this->service->answerLinks($row),
            'ticketUrl'   => $this->service->ticketUrl($row),
            'aiReady'     => $this->service->aiAvailable($cfg),
        ]);
    }

    /** POST pqrsf/asignar/{id} */
    public function assign($id = null)
    {
        [$row, $stop] = $this->owned($id);
        if ($stop) {
            return $stop;
        }

        $userId = (int) $this->request->getPost('assignee_id') ?: null;
        $name   = $userId ? ($this->agents((int) $row['company_id'])[$userId] ?? null) : null;
        $this->service->assign($row, $userId, $name);

        return redirect()->back()->with('success', lang('Pqr.okAssigned'));
    }

    /** POST pqrsf/estado/{id} */
    public function status($id = null)
    {
        [$row, $stop] = $this->owned($id);
        if ($stop) {
            return $stop;
        }

        return $this->service->changeStatus($row, (string) $this->request->getPost('status'))
            ? redirect()->back()->with('success', lang('Pqr.okStatus'))
            : redirect()->back()->with('error', lang('Pqr.errStatus'));
    }

    /** POST pqrsf/prioridad/{id} — aplica la prioridad (p. ej. la que sugirió la IA). */
    public function priority($id = null)
    {
        [$row, $stop] = $this->owned($id);
        if ($stop) {
            return $stop;
        }

        $p = (string) $this->request->getPost('priority');
        if (!in_array($p, RequestModel::PRIORITIES, true)) {
            return redirect()->back()->with('error', lang('Pqr.errPriority'));
        }

        $this->model->update((int) $row['id'], ['priority' => $p]);
        (new EventModel())->log((int) $row['id'], 'estado', lang('Pqr.evPriority', [lang('Pqr.priority_' . $p)]), ['priority' => $p]);

        return redirect()->back()->with('success', lang('Pqr.okPriority'));
    }

    /** POST pqrsf/nota/{id} */
    public function note($id = null)
    {
        [$row, $stop] = $this->owned($id);
        if ($stop) {
            return $stop;
        }

        $body = trim((string) $this->request->getPost('body'));
        if ($body === '') {
            return redirect()->back()->with('error', lang('Pqr.errNoteEmpty'));
        }

        // Una nota puede ser interna o visible para el ciudadano.
        $this->service->addNote($row, $body, !$this->request->getPost('public'));

        return redirect()->back()->with('success', lang('Pqr.okNote'));
    }

    /** POST pqrsf/ia/{id} — regenera el borrador con instrucciones. */
    public function draft($id = null)
    {
        [$row, $stop] = $this->owned($id);
        if ($stop) {
            return $stop;
        }

        $cfg = (new SettingsModel())->forCompany((int) $row['company_id']);
        if (!$this->service->aiAvailable($cfg)) {
            return redirect()->back()->with('error', lang('Pqr.errAiOff'));
        }

        $this->service->runAi($row, $cfg, (string) $this->request->getPost('instructions'));

        return redirect()->back()->with('success', lang('Pqr.okDraft'));
    }

    /** POST pqrsf/responder/{id} — aprueba la respuesta y avisa. */
    public function answer($id = null)
    {
        [$row, $stop] = $this->owned($id);
        if ($stop) {
            return $stop;
        }

        $company  = (new \App\Models\CompanyModel())->find((int) $row['company_id']);
        $channels = array_values(array_filter((array) $this->request->getPost('channels')));

        // Un soporte subido aquí nace público: se adjunta PARA el ciudadano.
        $file = $this->request->getFile('answer_file');
        if ($file && $file->isValid()) {
            $up = (new AttachmentService())->store($file, 'request', (int) $row['id'], (int) session()->get('user_id'), true);
            if (!$up['ok']) {
                return redirect()->back()->withInput()->with('error', $up['message']);
            }
            (new EventModel())->log((int) $row['id'], 'adjunto', $file->getClientName(), ['attachment_id' => $up['id'], 'public' => true]);
        }

        $res = $this->service->approveAndSend($row, $company, (string) $this->request->getPost('answer'), $channels, [
            'url'   => (string) $this->request->getPost('answer_link'),
            'label' => (string) $this->request->getPost('answer_link_label'),
        ]);

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** POST pqrsf/adjunto/publicar/{id} — muestra u oculta un soporte al ciudadano. */
    public function toggleAttachment($attId = null)
    {
        $svc = new AttachmentService();
        $att = $svc->find((int) $attId);

        if (!$att || $svc->ownerCompanyId($att['owner_type'], (int) $att['owner_id']) !== $this->companyId()) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errNotFound'));
        }

        $now = empty($att['is_public']);
        $svc->setPublic((int) $attId, $now);

        return redirect()->back()->with('success', lang($now ? 'Pqr.attShared' : 'Pqr.attUnshared', [$att['name']]));
    }

    /** POST pqrsf/adjunto/{id} — el gestor sube un soporte. */
    public function attach($id = null)
    {
        [$row, $stop] = $this->owned($id);
        if ($stop) {
            return $stop;
        }

        $file = $this->request->getFile('file');
        if (!$file) {
            return redirect()->back()->with('error', lang('Pqr.attErrFile'));
        }

        $res = (new AttachmentService())->store($file, 'request', (int) $row['id'], (int) session()->get('user_id'));
        if ($res['ok']) {
            (new EventModel())->log((int) $row['id'], 'adjunto', $file->getClientName(), ['attachment_id' => $res['id']]);
        }

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** GET pqrsf/adjunto/ver/{id} — descarga con permiso y empresa. */
    public function download($attId = null)
    {
        $svc = new AttachmentService();
        $att = $svc->find((int) $attId);
        if (!$att) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errNotFound'));
        }

        // El permiso no basta: el adjunto tiene que ser de la empresa activa.
        if ($svc->ownerCompanyId($att['owner_type'], (int) $att['owner_id']) !== $this->companyId()) {
            return redirect()->to('pqrsf')->with('error', lang('Pqr.errNotFound'));
        }

        $path = $svc->absolutePath($att);
        if (!is_file($path)) {
            return redirect()->back()->with('error', lang('Pqr.attErrMissing'));
        }

        return $this->response->download($path, null)->setFileName($att['name']);
    }

    /** POST pqrsf/adjunto/eliminar/{id} */
    public function deleteAttachment($attId = null)
    {
        $svc = new AttachmentService();
        $att = $svc->find((int) $attId);

        if ($att && $svc->ownerCompanyId($att['owner_type'], (int) $att['owner_id']) === $this->companyId()) {
            $svc->delete((int) $attId);

            return redirect()->back()->with('success', lang('Pqr.attDeleted'));
        }

        return redirect()->back()->with('error', lang('Pqr.errNotFound'));
    }

    /**
     * Solicitud de la empresa activa o redirección.
     *
     * @return array{0:?array,1:mixed}
     */
    private function owned($id): array
    {
        $companyId = $this->companyId();
        $row       = $companyId ? $this->model->own($companyId, (int) $id) : null;

        return $row ? [$row, null] : [null, redirect()->to('pqrsf')->with('error', lang('Pqr.errNotFound'))];
    }
}
