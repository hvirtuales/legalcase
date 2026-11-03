<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\AutomationRegistry;
use Components\LeadFlow\Libraries\DemoData;
use Components\LeadFlow\Models\AutomationModel;
use Components\LeadFlow\Models\AutomationRunModel;

/**
 * Automatizaciones (§19): DISPARADOR → CONDICIONES → ACCIONES.
 *
 * El controlador es delgado a propósito: lo que llega del navegador **no se
 * guarda, se reconstruye** contra `AutomationRegistry` (mismo criterio que
 * `FormEngine::sanitize()` con el árbol de un formulario), y quien ejecuta es
 * `AutomationEngine`, al que llaman los servicios y no una pantalla.
 *
 * Las condiciones y las acciones viajan como **JSON en dos campos ocultos** que
 * arma el editor, igual que el `schema` del constructor de formularios: así todo
 * va en UN formulario con UN botón de guardar y no hay dos envíos que se puedan
 * desincronizar ni un token CSRF que rote entre ellos.
 */
class Automations extends BaseLfl
{
    private function guard()
    {
        if (! $this->companyId()) {
            $this->flash('error', lang('Lfl.errNoCompany'));

            return redirect()->to(base_url('leadflow'));
        }

        return null;
    }

    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('automations/index', ['rows' => null]);
        }

        return $this->render('automations/index', [
            'rows'      => (new AutomationModel())->forCompany($cid)->orderBy('sort', 'asc')->orderBy('id', 'asc')->findAll(),
            'canDelete' => $this->can('lfl.delete'),
        ]);
    }

    /** Las cuatro automatizaciones de ejemplo del §19, solo si la firma no tiene ninguna. */
    public function demo()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $this->result((new DemoData())->runAutomations($this->companyId(), $this->userId()));

        return redirect()->to(base_url('leadflow/automatizaciones'));
    }

    public function runs()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('automations/runs', ['runs' => null]);
        }

        return $this->render('automations/runs', ['runs' => (new AutomationRunModel())->recent($cid, 60)]);
    }

    public function create()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        if ($this->isPost()) {
            return $this->save(null);
        }

        return $this->form(lang('Lfl.autNew'), [], base_url('leadflow/automatizaciones/nueva'));
    }

    public function edit($id = null)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $row = (new AutomationModel())->owned((int) $id, $this->companyId());
        if (! $row) {
            return $this->notFound('leadflow/automatizaciones');
        }
        if ($this->isPost()) {
            return $this->save($row);
        }

        return $this->form(lang('Lfl.autEdit'), $row, base_url('leadflow/automatizaciones/editar/' . (int) $row['id']));
    }

    private function form(string $title, array $row, string $action)
    {
        $cid = $this->companyId();

        return $this->render('automations/form', [
            'title'      => $title,
            'row'        => $row,
            'action'     => $action,
            'conditions' => AutomationModel::conditions($row ?: null),
            'actions'    => AutomationModel::actions($row ?: null),
            'registry'   => (new AutomationRegistry())->forJs($cid),
        ]);
    }

    private function save(?array $existing)
    {
        $cid      = $this->companyId();
        $model    = new AutomationModel();
        $registry = new AutomationRegistry();

        $trigger = (string) $this->request->getPost('trigger_event');
        $mode    = (string) $this->request->getPost('match_mode');

        // Lista blanca: un disparador inventado no se guarda.
        if (! isset(AutomationRegistry::TRIGGERS[$trigger])) {
            $trigger = $existing['trigger_event'] ?? 'lead_created';
        }

        $data = [
            'company_id'    => $cid,
            'name'          => $this->str('name', 120),
            'trigger_event' => $trigger,
            'match_mode'    => $mode === 'any' ? 'any' : 'all',
            'conditions'    => json_encode($registry->sanitizeConditions((string) $this->request->getPost('conditions')), JSON_UNESCAPED_UNICODE),
            'actions'       => json_encode($registry->sanitizeActions((string) $this->request->getPost('actions'), $cid), JSON_UNESCAPED_UNICODE),
            'once_per_lead' => $this->request->getPost('once_per_lead') ? 1 : 0,
            'is_active'     => $this->request->getPost('is_active') ? 1 : 0,
            'sort'          => max(0, min(999, (int) $this->request->getPost('sort'))),
        ];
        if (! $existing) {
            $data['created_by'] = $this->userId();
        }

        $ok = $existing ? $model->update((int) $existing['id'], $data) : $model->insert($data);
        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        // Guardar una automatización sin acciones no es un error —se puede dejar a
        // medias y seguir mañana—, pero conviene decir que todavía no hace nada.
        $count = count(json_decode((string) $data['actions'], true) ?: []);
        $this->flash($count > 0 ? 'success' : 'error', $count > 0 ? lang('Lfl.okAutomationSaved', [$count]) : lang('Lfl.autNoActionsYet'));

        return redirect()->to(base_url('leadflow/automatizaciones'));
    }

    public function toggle($id = null)
    {
        $model = new AutomationModel();
        $row   = $model->owned((int) $id, $this->companyId());
        if (! $row) {
            return $this->notFound('leadflow/automatizaciones');
        }
        $model->skipValidation(true)->update((int) $row['id'], ['is_active' => (int) $row['is_active'] === 1 ? 0 : 1]);
        $model->skipValidation(false);
        $this->flash('success', lang('Lfl.okUpdated'));

        return redirect()->to(base_url('leadflow/automatizaciones'));
    }

    public function delete($id = null)
    {
        $model = new AutomationModel();
        $row   = $model->owned((int) $id, $this->companyId());
        if (! $row) {
            return $this->notFound('leadflow/automatizaciones');
        }
        $model->delete((int) $row['id']);
        $this->flash('success', lang('Lfl.okDeleted'));

        return redirect()->to(base_url('leadflow/automatizaciones'));
    }
}
