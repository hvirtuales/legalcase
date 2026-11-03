<?php

namespace Components\Mailer\Libraries;

use App\Models\CompanyModel;
use App\Models\PlanModel;
use Components\Mailer\Models\RecipientModel;

/**
 * Aplica los límites del plan al servicio de Correos masivos: si está habilitado
 * (`mailer_enabled`) y cuántos envíos quedan este mes (`max_emails`). Lee
 * `companies.plan_id` → `plans`. Sin plan asignado: permitido e ilimitado.
 */
class PlanGuard
{
    private function plan(int $companyId): ?array
    {
        $companies = new CompanyModel();

        // El plan EFECTIVO lo resuelve el núcleo cuando existe: es quien sabe si
        // la empresa cuelga de un grupo. Llegó con los tenants, así que una
        // instalación sin ellos no lo tiene (contrato 15.2: degradar solo).
        if (method_exists($companies, 'effectivePlan')) {
            return $companies->effectivePlan($companyId);
        }

        $company = $companies->find($companyId);
        if (!$company || empty($company['plan_id'])) {
            return null;
        }

        return (new PlanModel())->find($company['plan_id']);
    }

    /**
     * ¿El plan incluye el servicio? Sin plan, o sin la columna, SÍ.
     *
     * La comprobación de la columna no es un adorno: mientras la migración que
     * la crea no existió, `!empty($plan['mailer_enabled'])` sobre una clave
     * ausente daba **false**, y eso apagaba el componente entero para cualquier
     * empresa con plan —ocultaba «Nueva campaña» y bloqueaba crear, enviar,
     * encolar y el despacho por consola—. Una columna que falta significa «esta
     * instalación no gradúa este servicio», no «está prohibido».
     */
    public function serviceEnabled(int $companyId): bool
    {
        $plan = $this->plan($companyId);

        if (!$plan || !array_key_exists('mailer_enabled', $plan)) {
            return true;
        }

        return (int) $plan['mailer_enabled'] === 1;
    }

    /** Envíos restantes en el mes actual según el plan; null = ilimitado. */
    public function emailsLeft(int $companyId): ?int
    {
        $plan = $this->plan($companyId);
        $max  = $plan ? (int) ($plan['max_emails'] ?? 0) : 0;
        if ($max <= 0) {
            return null;
        }

        return max(0, $max - $this->sentThisMonth($companyId));
    }

    /** Correos efectivamente enviados este mes (destinatarios con status sent). */
    public function sentThisMonth(int $companyId): int
    {
        $start = date('Y-m-01 00:00:00');

        return (new RecipientModel())
            ->where('company_id', $companyId)
            ->where('status', 'sent')
            ->where('sent_at >=', $start)
            ->countAllResults();
    }
}
