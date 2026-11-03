<?php

namespace Components\Networking\Libraries;

use Components\Networking\Models\RequestModel;

/**
 * Hace valer el plan de la empresa activa sobre el componente (contrato §11):
 * lee `companies.plan_id` → `plans` y expone si el servicio está habilitado y
 * cuánto cupo de conexiones queda.
 *
 * Sin empresa activa o sin plan asignado → permitido e ilimitado.
 */
class PlanGuard
{
    /** Plan de la empresa activa (cacheado por request); null = Global o sin plan. */
    private ?array $plan = null;
    private bool $resolved = false;

    private function plan(): ?array
    {
        if ($this->resolved) {
            return $this->plan;
        }
        $this->resolved = true;

        $companyId = (int) (session('active_company_id') ?? 0);
        if ($companyId === 0) {
            return $this->plan = null;
        }
        // El plan efectivo lo resuelve el núcleo: es quien sabe si la empresa
        // pertenece a un tenant y qué plan rige en ese caso.
        return $this->plan = (new \App\Models\CompanyModel())->effectivePlan($companyId);
    }

    /** ¿El plan habilita el componente? Sin plan → sí. */
    public function serviceEnabled(): bool
    {
        $plan = $this->plan();
        if (!$plan || !array_key_exists('networking_enabled', $plan)) {
            return true;
        }

        return (int) $plan['networking_enabled'] === 1;
    }

    /** Cupo de conexiones que le quedan al usuario; null = ilimitado. */
    public function connectionsLeft(int $userId): ?int
    {
        $plan = $this->plan();
        $max  = (int) ($plan['max_connections'] ?? 0);
        if ($max <= 0) {
            return null; // 0 = ilimitado
        }

        $used = (new RequestModel())
            ->where('status', 'accepted')
            ->groupStart()->where('requester_id', $userId)->orWhere('addressee_id', $userId)->groupEnd()
            ->countAllResults();

        return max(0, $max - $used);
    }
}
