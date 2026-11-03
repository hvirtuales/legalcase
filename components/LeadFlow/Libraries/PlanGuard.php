<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\LeadModel;

/**
 * Hace valer el plan de la empresa (contrato §11): bandera
 * `plans.leadflow_enabled` y cupo `plans.max_leads`.
 *
 * Se aplica **en el servicio**, en todas las vías que crean leads —alta manual,
 * formulario público, importación y datos demo—, no escondiendo botones.
 *
 * **Portabilidad (§15.2):** `CompanyModel::effectivePlan()` llegó con los tenants;
 * se pregunta con `method_exists()` y, si no está, se lee el plan de la propia
 * empresa. Sin empresa o sin plan → permitido e ilimitado. Una columna que no
 * existe en `plans` significa «esta instalación no gradúa el servicio», no
 * «prohibido».
 */
class PlanGuard
{
    private array $plans = [];

    private function plan(int $companyId): ?array
    {
        if (array_key_exists($companyId, $this->plans)) {
            return $this->plans[$companyId];
        }
        if ($companyId <= 0) {
            return $this->plans[$companyId] = null;
        }

        $companies = new \App\Models\CompanyModel();
        if (method_exists($companies, 'effectivePlan')) {
            return $this->plans[$companyId] = $companies->effectivePlan($companyId);
        }

        return $this->plans[$companyId] = $this->ownPlan($companyId);
    }

    private function ownPlan(int $companyId): ?array
    {
        $db      = \Config\Database::connect();
        $company = $db->table('companies')->select('plan_id')->where('id', $companyId)->get()->getRowArray();
        if (! $company || empty($company['plan_id'])) {
            return null;
        }

        return $db->table('plans')->where('id', (int) $company['plan_id'])->get()->getRowArray() ?: null;
    }

    public function serviceEnabled(int $companyId): bool
    {
        $plan = $this->plan($companyId);
        if (! $plan || ! array_key_exists('leadflow_enabled', $plan)) {
            return true;
        }

        return (int) $plan['leadflow_enabled'] === 1;
    }

    /** Leads que le quedan a la empresa; `null` = ilimitado. */
    public function leadsLeft(int $companyId): ?int
    {
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_leads'] ?? 0);
        if ($max <= 0) {
            return null;
        }

        return max(0, $max - (new LeadModel())->countFor($companyId));
    }

    /**
     * ¿La empresa puede salir en el directorio público?
     *
     * Misma regla que el resto: si la columna no existe en `plans`, esta instalación
     * no gradúa el servicio y la respuesta es sí.
     */
    public function directoryEnabled(int $companyId): bool
    {
        $plan = $this->plan($companyId);
        if (! $plan || ! array_key_exists('lfl_directory_enabled', $plan)) {
            return true;
        }

        return (int) $plan['lfl_directory_enabled'] === 1;
    }

    /** ¿Su plan la destaca en el directorio? Aquí el defecto es NO: destacar se compra. */
    public function featured(int $companyId): bool
    {
        $plan = $this->plan($companyId);

        return $plan !== null && (int) ($plan['lfl_featured'] ?? 0) === 1;
    }

    /** Perfiles que le quedan por publicar; `null` = ilimitado. */
    public function profilesLeft(int $companyId): ?int
    {
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_profiles'] ?? 0);
        if ($max <= 0) {
            return null;
        }

        return max(0, $max - (new \Components\LeadFlow\Models\ProfileModel())->publishedCount($companyId));
    }

    /**
     * ¿Las opiniones están habilitadas para esta empresa?
     *
     * **Aquí el defecto es NO, al revés que el resto de las banderas.** La regla general
     * del componente es «una columna ausente significa permitido», pero los testimonios
     * están restringidos en algunas jurisdicciones de publicidad legal: encenderlos
     * porque nadie los configuró sería tomar una decisión legal en nombre de la firma.
     */
    public function reviewsEnabled(int $companyId): bool
    {
        $plan = $this->plan($companyId);

        return $plan !== null && (int) ($plan['lfl_reviews_enabled'] ?? 0) === 1;
    }

    /**
     * Oportunidades que le quedan por reclamar este mes; `null` = ilimitado.
     *
     * **Por mes y no en total**: es una cuota recurrente, que es lo que se vende en una
     * membresía. El mes se cuenta natural (desde el día 1), no en ventanas de 30 días:
     * una cuota que se renueva «dentro de 11 días» no se puede explicar en una factura.
     */
    public function opportunitiesLeft(int $companyId): ?int
    {
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_opportunities'] ?? 0);
        if ($max <= 0) {
            return null;
        }

        return max(0, $max - (new \Components\LeadFlow\Models\OpportunityClaimModel())->thisMonth($companyId));
    }

    /** @return string|null motivo por el que NO se puede reclamar una oportunidad, o null */
    public function opportunityBlockReason(int $companyId): ?string
    {
        if (! $this->directoryEnabled($companyId)) {
            return lang('Lfl.planNoDirectory');
        }
        $left = $this->opportunitiesLeft($companyId);
        if ($left !== null && $left <= 0) {
            return lang('Lfl.planOppExhausted');
        }

        return null;
    }

    /** @return string|null motivo por el que NO se puede PUBLICAR un perfil, o null */
    public function publishBlockReason(int $companyId): ?string
    {
        if (! $this->directoryEnabled($companyId)) {
            return lang('Lfl.planNoDirectory');
        }
        $left = $this->profilesLeft($companyId);
        if ($left !== null && $left <= 0) {
            return lang('Lfl.planProfilesExhausted');
        }

        return null;
    }

    /** @return string|null motivo por el que NO se puede crear un lead, o null */
    public function blockReason(int $companyId): ?string
    {
        if (! $this->serviceEnabled($companyId)) {
            return lang('Lfl.planDisabled');
        }
        $left = $this->leadsLeft($companyId);
        if ($left !== null && $left <= 0) {
            return lang('Lfl.planExhausted');
        }

        return null;
    }
}
