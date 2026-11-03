<?php

namespace Components\Contabilidad\Libraries;

use Components\Contabilidad\Models\PaymentRequestModel;
use Components\Contabilidad\Models\VoucherModel;

/**
 * Aplica los límites del plan de la empresa al servicio de contabilidad:
 * si el servicio está habilitado y cuántos comprobantes / cuentas de cobro
 * quedan. Lee `companies.plan_id` → `plans`. Sin plan asignado: permitido y
 * sin límite (no rompe empresas sin plan).
 */
class PlanGuard
{
    private function plan(int $companyId): ?array
    {
        // El plan efectivo lo resuelve el núcleo: es quien sabe si la empresa
        // pertenece a un tenant y qué plan rige en ese caso.
        return (new \App\Models\CompanyModel())->effectivePlan($companyId);
    }

    public function serviceEnabled(int $companyId): bool
    {
        $plan = $this->plan($companyId);

        return $plan === null ? true : !empty($plan['service_enabled']);
    }

    /** Comprobantes restantes según el plan; null = ilimitado. */
    public function vouchersLeft(int $companyId): ?int
    {
        return $this->left($companyId, 'max_vouchers', new VoucherModel());
    }

    /** Cuentas de cobro restantes según el plan; null = ilimitado. */
    public function paymentRequestsLeft(int $companyId): ?int
    {
        return $this->left($companyId, 'max_payment_requests', new PaymentRequestModel());
    }

    private function left(int $companyId, string $field, $model): ?int
    {
        $plan = $this->plan($companyId);
        $max  = $plan ? (int) ($plan[$field] ?? 0) : 0;
        if ($max <= 0) {
            return null; // 0 = ilimitado
        }
        $used = $model->where('company_id', $companyId)->countAllResults();

        return max(0, $max - $used);
    }
}
