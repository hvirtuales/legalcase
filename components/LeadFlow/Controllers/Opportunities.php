<?php

namespace Components\LeadFlow\Controllers;

use Components\LeadFlow\Libraries\Directory\OpportunityService;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Models\OpportunityClaimModel;

/**
 * La bandeja de oportunidades de la firma.
 *
 * Mirarla es `lfl.view` —es consultar—, pero **reclamar es `lfl.create`**: lo que hace
 * ese botón es dar de alta un lead con los datos de una persona, y ese es el verbo del
 * contrato para dar de alta.
 *
 * La pantalla no decide nada: `OpportunityService` resuelve qué áreas ve esta firma, si
 * la oportunidad sigue abierta, si ya la reclamó y si su plan le deja reclamar más.
 */
class Opportunities extends BaseLfl
{
    public function index()
    {
        $cid = $this->companyId();
        if (! $cid) {
            $this->flash('error', lang('Lfl.errNoCompany'));

            return redirect()->to(base_url('leadflow'));
        }

        $svc  = new OpportunityService();
        $plan = new PlanGuard();

        return $this->render('opportunities/index', [
            'title'       => lang('Lfl.navOpportunities'),
            'rows'        => $svc->board($cid),
            'areas'       => $svc->publishedAreas($cid),
            'profiles'    => count($svc->publishedProfiles($cid)),
            'left'        => $plan->opportunitiesLeft($cid),
            'directoryOn' => $plan->directoryEnabled($cid),
            'claimed'     => (new OpportunityClaimModel())->thisMonth($cid),
            'canClaim'    => $this->can('lfl.create') && $this->access()->canWrite,
        ]);
    }

    public function claim($id = null)
    {
        $cid = $this->companyId();
        if (! $cid) {
            return $this->notFound('leadflow/oportunidades');
        }

        $res = (new OpportunityService())->claim((int) $id, $cid, $this->userId());
        $this->result($res);

        // Al reclamar se va directo a la ficha del lead: es lo que hay que trabajar
        // ahora, y la bandeja ya no tiene nada que ofrecer sobre ese caso.
        return $res['ok'] && $res['id'] > 0
            ? $this->toLead((int) $res['id'])
            : redirect()->to(base_url('leadflow/oportunidades'));
    }
}
