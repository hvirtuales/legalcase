<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;

/**
 * El reclamo de una oportunidad por parte de una firma.
 *
 * **Su UNIQUE `(opportunity_id, company_id)` es el único freno fiable** contra el doble
 * reclamo: dos peticiones simultáneas pasan las dos la comprobación previa —leen el
 * mismo contador— y es la base de datos la que deja entrar solo a una. Igual que el
 * UNIQUE de la bitácora de automatizaciones hace valer el «una sola vez por lead» entre
 * peticiones.
 *
 * `useTimestamps` está apagado: la fila se escribe una vez y no se vuelve a tocar, así
 * que `updated_at` sobraría.
 */
class OpportunityClaimModel extends Model
{
    protected $table         = 'cmp_lfl_opportunity_claims';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['opportunity_id', 'company_id', 'user_id', 'lead_id', 'created_at'];

    public function byCompany(int $companyId)
    {
        return $this->where('company_id', $companyId);
    }

    /** ¿Esta firma ya reclamó esta oportunidad? */
    public function already(int $opportunityId, int $companyId): bool
    {
        return $this->where('opportunity_id', $opportunityId)->where('company_id', $companyId)
            ->countAllResults() > 0;
    }

    /** Ids de las oportunidades que esta firma ya reclamó. */
    public function idsOf(int $companyId): array
    {
        return array_map('intval', array_column(
            $this->select('opportunity_id')->where('company_id', $companyId)->findAll(),
            'opportunity_id'
        ));
    }

    /** Reclamos de la firma dentro del mes en curso: es como se cuenta el cupo del plan. */
    public function thisMonth(int $companyId): int
    {
        return $this->where('company_id', $companyId)
            ->where('created_at >=', date('Y-m-01 00:00:00'))->countAllResults();
    }
}
