<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\EngagementService;
use Components\LeadFlow\Models\NoteModel;

/** Notas y comunicaciones desde la ficha del lead. */
class Engagement extends BaseLfl
{
    public function note($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new EngagementService())->addNote($lead, (string) $this->request->getPost('body'),
            (bool) $this->request->getPost('is_transferable')));

        return $this->toLead((int) $lead['id'], 'comunicaciones');
    }

    public function deleteNote($id = null)
    {
        $note = (new NoteModel())->owned((int) $id, $this->companyId());
        if (! $note || ! $this->lead((int) $note['lead_id'], true)) {
            return $this->notFound();
        }
        $this->result((new EngagementService())->deleteNote($note));

        return $this->toLead((int) $note['lead_id'], 'comunicaciones');
    }

    public function log($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new EngagementService())->logCommunication($lead, [
            'channel'      => $this->request->getPost('channel'),
            'direction'    => $this->request->getPost('direction'),
            'outcome'      => $this->request->getPost('outcome'),
            'duration_min' => $this->request->getPost('duration_min'),
            'subject'      => $this->request->getPost('subject'),
            'body'         => $this->request->getPost('body'),
        ]));

        return $this->toLead((int) $lead['id'], 'comunicaciones');
    }

    public function email($leadId = null)
    {
        $lead = $this->lead((int) $leadId, true);
        if (! $lead) {
            return $this->notFound();
        }
        $this->result((new EngagementService())->sendEmail($lead, (string) $this->request->getPost('subject'),
            (string) $this->request->getPost('body')));

        return $this->toLead((int) $lead['id'], 'comunicaciones');
    }
}
