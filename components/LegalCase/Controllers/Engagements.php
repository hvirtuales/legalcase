<?php

namespace Components\LegalCase\Controllers;

use Components\LegalCase\Libraries\EngagementService;
use Components\LegalCase\Libraries\MessageService;
use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\SignatureModel;

/** Contrato de prestación de servicios / carta de encargo / poder del asunto. */
class Engagements extends BaseLgc
{
    public function edit($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        $svc = new EngagementService();
        if ($this->isPost()) {
            $r = $svc->save($matter, $this->request->getPost(), $this->acc());

            return $r->ok ? $this->done($r, $this->matterUrl($matter)) : redirect()->back()->withInput()->with('error', esc($r->message));
        }
        $row = $svc->current((int) $matter['id']);

        return view('Components\LegalCase\Views\engagements\form', [
            'title'     => lang('Lgc.engagement'),
            'matter'    => $matter,
            'row'       => $row ?? ['kind' => 'services_contract', 'fee_type' => 'fixed', 'currency' => 'COP', 'status' => 'draft'],
            'docs'      => (new DocumentModel())->select('id, name, classification')->where('matter_id', (int) $matter['id'])->where('is_current', 1)->findAll(200),
            'accounts'  => MessageService::accountsFor($this->companyId(), (int) $matter['id']),
            'signature' => $row && $row['signature_id'] ? (new SignatureModel())->find((int) $row['signature_id']) : null,
            'canWork'   => $this->acc()->canWorkOn($matter),
            'acc'       => $this->acc(),
        ]);
    }

    public function sign($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new EngagementService())->sendForSignature($matter, (int) $this->post('account_id'), $this->acc()), 'legalcase/asuntos/contrato/' . (int) $matter['id']);
    }

    public function terminate($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }

        return $this->done((new EngagementService())->terminate($matter, $this->acc()), 'legalcase/asuntos/contrato/' . (int) $matter['id']);
    }
}
