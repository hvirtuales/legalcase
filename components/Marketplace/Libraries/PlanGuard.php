<?php

namespace Components\Marketplace\Libraries;

use Components\Marketplace\Models\ListingModel;

/**
 * Hace valer el plan de la empresa activa sobre el componente (contrato §11):
 * lee `companies.plan_id` → `plans` y expone si el servicio está habilitado y
 * cuántas publicaciones activas puede tener todavía la empresa.
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

    /** ¿El plan habilita el marketplace? Sin plan → sí. */
    public function serviceEnabled(?int $companyId = null): bool
    {
        $plan = $this->plan($companyId);
        if (!$plan || !array_key_exists('marketplace_enabled', $plan)) {
            return true;
        }

        return (int) $plan['marketplace_enabled'] === 1;
    }

    /**
     * Publicaciones que le quedan a la empresa; null = ilimitado.
     * Cuenta solo las vivas (borrador, publicada o pausada): las cerradas liberan cupo.
     */
    public function listingsLeft(?int $companyId = null): ?int
    {
        $companyId ??= (int) (session('active_company_id') ?? 0);
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_listings'] ?? 0);
        if ($max <= 0 || !$companyId) {
            return null;
        }

        $used = (new ListingModel())
            ->where('company_id', $companyId)
            ->whereIn('status', ['draft', 'published', 'paused'])
            ->countAllResults();

        return max(0, $max - $used);
    }
}
