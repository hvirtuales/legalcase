<?php

namespace Components\Pqrsf\Libraries;

use Components\Pqrsf\Models\RequestModel;

/**
 * Hace valer el plan de la empresa (contrato §11): lee `companies.plan_id` →
 * `plans` y dice si el servicio está habilitado y cuántas solicitudes quedan.
 *
 * Sin plan asignado → permitido e ilimitado: un catálogo de planes a medio
 * configurar no puede dejar a un ciudadano sin poder radicar.
 */
class PlanGuard
{
    private ?array $plan = null;
    private bool $resolved = false;

    private function plan(?int $companyId): ?array
    {
        if ($this->resolved) {
            return $this->plan;
        }
        $this->resolved = true;

        $companyId ??= (int) (session('active_company_id') ?? 0);
        if (!$companyId) {
            return $this->plan = null;
        }

        // El plan efectivo lo resuelve el núcleo: es quien sabe si la empresa
        // pertenece a un tenant y qué plan rige en ese caso.
        return $this->plan = (new \App\Models\CompanyModel())->effectivePlan($companyId);
    }

    public function serviceEnabled(?int $companyId = null): bool
    {
        $plan = $this->plan($companyId);
        if (!$plan || !array_key_exists('pqrsf_enabled', $plan)) {
            return true;
        }

        return (int) $plan['pqrsf_enabled'] === 1;
    }

    /** Solicitudes que le quedan a la empresa; null = ilimitado. */
    public function requestsLeft(?int $companyId = null): ?int
    {
        $companyId ??= (int) (session('active_company_id') ?? 0);
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_requests'] ?? 0);

        if ($max <= 0 || !$companyId) {
            return null;
        }

        return max(0, $max - (new RequestModel())->where('company_id', $companyId)->countAllResults());
    }
}
