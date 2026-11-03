<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\DocumentService;
use Components\LeadFlow\Libraries\FormEngine;
use Components\LeadFlow\Libraries\QrService;
use Components\LeadFlow\Libraries\SubmissionService;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\ServiceModel;

/**
 * Formularios dinámicos (§13): listado, constructor y el llenado interno de
 * precalificación desde la ficha del lead.
 */
class Forms extends BaseLfl
{
    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->render('forms/index', ['forms' => null]);
        }

        $forms = (new FormModel())->select('cmp_lfl_forms.*, s.name AS service_name, c.name AS campaign_name')
            ->join('cmp_lfl_services s', 's.id = cmp_lfl_forms.service_id', 'left')
            ->join('cmp_lfl_campaigns c', 'c.id = cmp_lfl_forms.campaign_id', 'left')
            ->where('cmp_lfl_forms.company_id', $cid)->orderBy('cmp_lfl_forms.id', 'desc')->findAll();

        return $this->render('forms/index', ['forms' => $forms, 'canDelete' => $this->can('lfl.delete')]);
    }

    public function create()
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound('leadflow/formularios');
        }

        if ($this->isPost()) {
            $model = new FormModel();
            $ok    = $model->insert([
                'company_id'       => $cid,
                'name'             => $this->str('name', 150),
                'description'      => $this->str('description', 2000) ?: null,
                'token'            => FormModel::newToken(),
                'service_id'       => (new ServiceModel())->owned((int) $this->request->getPost('service_id'), $cid) ? (int) $this->request->getPost('service_id') : null,
                'campaign_id'      => (new CampaignModel())->owned((int) $this->request->getPost('campaign_id'), $cid) ? (int) $this->request->getPost('campaign_id') : null,
                'status'           => 'draft',
                'schema'           => '[]',
                'ask_consultation' => 1,
                'created_by'       => $this->userId(),
            ]);
            if (! $ok) {
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }
            $this->flash('success', lang('Lfl.okFormCreated'));

            return redirect()->to(base_url('leadflow/formularios/editar/' . (int) $model->getInsertID()));
        }

        return $this->render('forms/create', [
            'services'  => (new ServiceModel())->options($cid),
            'campaigns' => (new CampaignModel())->options($cid),
        ]);
    }

    public function edit($id = null)
    {
        $cid   = $this->companyId();
        $model = new FormModel();
        $form  = $model->owned((int) $id, $cid);
        if (! $form) {
            return $this->notFound('leadflow/formularios');
        }

        if ($this->isPost()) {
            $schema = (new FormEngine())->sanitize((string) $this->request->getPost('schema'));
            $status = (string) $this->request->getPost('status');
            $data   = [
                'name'             => $this->str('name', 150),
                'description'      => $this->str('description', 2000) ?: null,
                'service_id'       => (new ServiceModel())->owned((int) $this->request->getPost('service_id'), $cid) ? (int) $this->request->getPost('service_id') : null,
                'campaign_id'      => (new CampaignModel())->owned((int) $this->request->getPost('campaign_id'), $cid) ? (int) $this->request->getPost('campaign_id') : null,
                'status'           => in_array($status, FormModel::STATUSES, true) ? $status : 'draft',
                'is_public'        => $this->request->getPost('is_public') ? 1 : 0,
                'ask_consultation' => $this->request->getPost('ask_consultation') ? 1 : 0,
                'success_message'  => $this->str('success_message', 2000) ?: null,
                'schema'           => json_encode($schema, JSON_UNESCAPED_UNICODE),
            ];
            if (! $model->update((int) $form['id'], $data)) {
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }
            $this->flash('success', lang('Lfl.okFormSaved', [count($schema)]));

            return redirect()->to(base_url('leadflow/formularios/editar/' . (int) $form['id']));
        }

        $url = base_url('leadflow-publico/f/' . $form['token']);

        return $this->render('forms/builder', [
            'form'      => $form,
            'fields'    => FormModel::fields($form),
            'services'  => (new ServiceModel())->options($cid),
            'campaigns' => (new CampaignModel())->options($cid),
            'publicUrl' => $url,
            'qr'        => (new QrService())->dataUri($url),
        ]);
    }

    public function delete($id = null)
    {
        $form = (new FormModel())->owned((int) $id, $this->companyId());
        if (! $form) {
            return $this->notFound('leadflow/formularios');
        }
        (new FormModel())->delete((int) $form['id']);
        $this->flash('success', lang('Lfl.okDeleted'));

        return redirect()->to(base_url('leadflow/formularios'));
    }

    /** Llenar un formulario para un lead (una llamada de precalificación). */
    public function fill($leadId = null, $formId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        $form = $lead ? (new FormModel())->owned((int) $formId, (int) $lead['company_id']) : null;
        if (! $lead || ! $form) {
            return $this->notFound();
        }
        $fields = FormModel::fields($form);

        if ($this->isPost()) {
            $files = [];
            foreach ($fields as $f) {
                if ($f['type'] === 'file') {
                    $files[$f['id']] = $this->request->getFile('q.' . $f['id']);
                }
            }
            $engine = new FormEngine();
            $res    = $engine->collect($fields, (array) ($this->request->getPost('q') ?? []), $files);
            if ($res['errors']) {
                return redirect()->back()->withInput()->with('errors', array_values($res['errors']));
            }

            // Los documentos van ANTES de registrar la respuesta: el criterio
            // «tiene documentos» del scoring cuenta, y `record()` es quien recalcula.
            foreach ($res['files'] as $file) {
                (new DocumentService())->store($lead, $file, null, 'internal', $this->userId());
            }
            (new SubmissionService())->record($lead, $form, $res, 'internal', $this->userId());

            $this->flash('success', lang('Lfl.okSubmission'));

            return $this->toLead((int) $lead['id'], 'precalificacion');
        }

        return $this->render('forms/fill', ['lead' => $lead, 'form' => $form, 'fields' => $fields]);
    }
}
