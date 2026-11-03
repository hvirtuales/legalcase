<?php

namespace Components\Directorio\Libraries;

use Components\Directorio\Models\BoardModel;

/**
 * Hace valer el plan **del grupo** (contrato de tenant §5): si el servicio está
 * incluido y cuántas vistas quedan.
 *
 * El plan efectivo lo resuelve el núcleo: un tenant es una fila de `companies`,
 * así que `CompanyModel::effectivePlan()` sirve igual. Sin plan asignado:
 * permitido e ilimitado, como en el resto de la plataforma.
 */
class PlanGuard
{
    private ?array $plan = null;
    private bool $resolved = false;

    private function plan(?int $tenantId = null): ?array
    {
        if ($this->resolved) {
            return $this->plan;
        }
        $this->resolved = true;

        $tenantId ??= (int) (session('active_tenant_id') ?? 0);
        if (!$tenantId) {
            return $this->plan = null;
        }

        return $this->plan = (new \App\Models\CompanyModel())->effectivePlan($tenantId);
    }

    /** ¿El plan del grupo incluye el directorio? Sin plan → sí. */
    public function serviceEnabled(?int $tenantId = null): bool
    {
        $plan = $this->plan($tenantId);

        return $plan === null ? true : !empty($plan['directory_enabled']);
    }

    /**
     * Vistas que le quedan al grupo; null = ilimitado.
     *
     * La columna del plan sigue llamándose `max_entries` —la creó la migración
     * del componente cuando el cupo era de entradas escritas a mano— pero
     * desde v3.0.0 cuenta vistas, que es lo único que hay. Renombrarla
     * obligaría a otra migración y a romper la simetría de `--drop-data`.
     */
    public function boardsLeft(?int $tenantId = null): ?int
    {
        $tenantId ??= (int) (session('active_tenant_id') ?? 0);
        $plan = $this->plan($tenantId);
        $max  = $plan ? (int) ($plan['max_entries'] ?? 0) : 0;
        if ($max <= 0) {
            return null; // 0 = ilimitado
        }

        $used = (new BoardModel())->where('tenant_id', $tenantId)->countAllResults();

        return max(0, $max - $used);
    }
}
