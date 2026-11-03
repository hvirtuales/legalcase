<?php

namespace Components\Servicios\Libraries;

use Components\Servicios\Models\QuoteModel;

/**
 * Hace valer el plan de la empresa activa (contrato §11): lee
 * `companies.plan_id` → `plans` y expone si el servicio está habilitado y cuántas
 * cotizaciones puede emitir todavía la empresa.
 *
 * Sin empresa activa o sin plan asignado → permitido e ilimitado.
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
        if (!$plan || !array_key_exists('quotes_enabled', $plan)) {
            return true;
        }

        return (int) $plan['quotes_enabled'] === 1;
    }

    /** Cotizaciones que le quedan a la empresa; null = ilimitado. */
    public function quotesLeft(?int $companyId = null): ?int
    {
        $companyId ??= (int) (session('active_company_id') ?? 0);
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_quotes'] ?? 0);
        if ($max <= 0 || !$companyId) {
            return null;
        }

        $used = (new QuoteModel())->where('company_id', $companyId)->countAllResults();

        return max(0, $max - $used);
    }
}
