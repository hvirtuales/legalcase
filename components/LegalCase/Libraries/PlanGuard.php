<?php

namespace Components\LegalCase\Libraries;

use App\Models\CompanyModel;
use Components\LegalCase\Models\MatterModel;
use Components\LegalCase\Models\PortalAccountModel;

/**
 * Hace valer el plan de la empresa (contrato §11) en el SERVICIO, no en la vista.
 *
 * Sin plan, o con un plan que no tiene la columna, todo está permitido e ilimitado:
 * una columna que no existe significa «permitido».
 */
class PlanGuard
{
    /** @var array<int,?array> */
    private array $plans = [];

    private function plan(int $companyId): ?array
    {
        if (!array_key_exists($companyId, $this->plans)) {
            $this->plans[$companyId] = $companyId > 0 ? (new CompanyModel())->effectivePlan($companyId) : null;
        }

        return $this->plans[$companyId];
    }

    public function serviceEnabled(int $companyId): bool
    {
        $plan = $this->plan($companyId);
        if (!$plan || !array_key_exists('lgc_enabled', $plan)) {
            return true;
        }

        return (int) $plan['lgc_enabled'] === 1;
    }

    /** Asuntos que le quedan a la empresa; null = ilimitado. */
    public function mattersLeft(int $companyId): ?int
    {
        $max = (int) ($this->plan($companyId)['lgc_max_matters'] ?? 0);
        if ($max <= 0) {
            return null;
        }

        return max(0, $max - (new MatterModel())->where('company_id', $companyId)->countAllResults());
    }

    /** Cuentas del portal que le quedan; null = ilimitado. */
    public function portalLeft(int $companyId): ?int
    {
        $max = (int) ($this->plan($companyId)['lgc_max_portal'] ?? 0);
        if ($max <= 0) {
            return null;
        }

        return max(0, $max - (new PortalAccountModel())->where('company_id', $companyId)->countAllResults());
    }
}
