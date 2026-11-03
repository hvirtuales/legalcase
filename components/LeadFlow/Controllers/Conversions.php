<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\ConversionService;
use Components\LeadFlow\Libraries\ConversionSteps;
use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\ConversionModel;
use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\MemberModel;
use Components\LeadFlow\Models\NoteModel;
use Components\LeadFlow\Models\ServiceModel;

/**
 * El botón «Convertir a cliente» (§22): el modal con sus opciones y la ejecución.
 */
class Conversions extends BaseLfl
{
    public function prepare($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }
        $cid        = (int) $lead['company_id'];
        $svc        = new IntegrationService();
        $candidates = $svc->candidates($lead);
        $notes      = (new NoteModel())->forCompany($cid)->where('lead_id', (int) $lead['id'])->where('is_transferable', 1)->orderBy('id', 'desc')->findAll();
        $documents  = (new DocumentModel())->forLead((int) $lead['id'], $cid);

        // Los requisitos se evalúan sobre lo que el formulario enviaría tal como se
        // pinta por defecto, para que lo que se ve aquí sea lo que va a pasar.
        $steps = ConversionSteps::evaluate($lead, [
            'contact_mode'        => $candidates ? 'existing' : 'new',
            'existing_contact_id' => $candidates[0]['contact']['external_id'] ?? '',
            'lawyer_user_id'      => (int) ($lead['lawyer_user_id'] ?? 0),
            'include_summary'     => true,
            'notes'               => array_column($notes, 'id'),
            'documents'           => array_column(array_filter($documents, static fn ($d) => (int) $d['is_authorized'] === 1), 'id'),
        ], ['accountUsers' => $svc->lists($cid)['users']]);

        return $this->render('conversions/prepare', [
            'lead'        => $lead,
            'problems'    => (new ConversionService($svc))->problems($lead),
            'candidates'  => $candidates,
            'steps'       => $steps,
            'integration' => $svc->integration($cid),
            'notes'       => $notes,
            'documents'   => $documents,
            'service'     => $lead['service_id'] ? (new ServiceModel())->find((int) $lead['service_id']) : null,
            'team'        => Team::users($cid),
            'members'     => (new MemberModel())->mapFor($cid),
            'conversion'  => (new ConversionModel())->forLead((int) $lead['id']),
        ]);
    }

    public function execute($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }

        $res = (new ConversionService())->start($lead, [
            'contact_mode'        => $this->request->getPost('contact_mode'),
            'existing_contact_id' => $this->request->getPost('existing_contact_id'),
            'lawyer_user_id'      => $this->request->getPost('lawyer_user_id'),
            'transfer_info'       => $this->request->getPost('transfer_info'),
            'include_summary'     => $this->request->getPost('include_summary'),
            'notes'               => (array) ($this->request->getPost('notes') ?? []),
            'documents'           => (array) ($this->request->getPost('documents') ?? []),
        ], $this->userId());
        $this->result($res);

        return $this->toLead((int) $lead['id'], 'docketwise');
    }

    public function retry($id = null)
    {
        $conv = (new ConversionModel())->owned((int) $id, $this->companyId());
        if (! $conv || ! $this->lead((int) $conv['lead_id'], true)) {
            return $this->notFound('leadflow/docketwise?tab=errores');
        }
        $this->result((new ConversionService())->retry($conv, $this->userId()));

        return $this->back('leadflow/leads/ficha/' . (int) $conv['lead_id'] . '?tab=docketwise');
    }
}
