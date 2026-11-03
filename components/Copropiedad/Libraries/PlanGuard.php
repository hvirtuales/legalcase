<?php

namespace Components\Copropiedad\Libraries;

use Components\Copropiedad\Models\UnitModel;

/**
 * Hace valer el plan de la empresa activa (contrato §11): bandera
 * `plans.copropiedad_enabled` y cupo `plans.max_units`.
 *
 * Se aplica **en el servicio y en el controlador**, no solo escondiendo el botón:
 * ocultar «Nuevo inmueble» no impide un POST directo a `inmuebles/nuevo`.
 *
 * **Portabilidad (contrato §15.2).** El plan efectivo lo sabe el núcleo, que es quien
 * conoce si la empresa cuelga de un tenant… pero `CompanyModel::effectivePlan()`
 * llegó **con** los tenants: en una instalación que no los tiene, ese método no
 * existe y llamarlo tumba el componente entero con «Call to undefined method». Por
 * eso se pregunta con `method_exists()` y, si no está, se resuelve el plan de la
 * propia empresa. El componente degrada solo en vez de exigir un núcleo concreto.
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
        if (! $companyId) {
            return $this->plan = null;
        }

        $companies = new \App\Models\CompanyModel();

        // Núcleo con tenants: él decide qué plan rige (el propio o el del grupo).
        if (method_exists($companies, 'effectivePlan')) {
            return $this->plan = $companies->effectivePlan($companyId);
        }

        return $this->plan = $this->ownPlan($companyId);
    }

    /**
     * El plan de la propia empresa, por Query Builder.
     *
     * Es el camino de la instalación **sin** tenants, donde `companies.plan_id` es
     * toda la verdad que hay.
     */
    private function ownPlan(int $companyId): ?array
    {
        $db      = \Config\Database::connect();
        $company = $db->table('companies')->select('plan_id')->where('id', $companyId)->get()->getRowArray();

        if (! $company || empty($company['plan_id'])) {
            return null;
        }

        return $db->table('plans')->where('id', (int) $company['plan_id'])->get()->getRowArray() ?: null;
    }

    /** ¿El plan incluye el servicio? */
    public function serviceEnabled(?int $companyId = null): bool
    {
        $plan = $this->plan($companyId);

        // Sin plan, o con un plan de antes de que existiera la columna: permitido.
        if (! $plan || ! array_key_exists('copropiedad_enabled', $plan)) {
            return true;
        }

        return (int) $plan['copropiedad_enabled'] === 1;
    }

    /** Inmuebles que le quedan a la empresa; `null` = ilimitado. */
    public function unitsLeft(?int $companyId = null): ?int
    {
        $companyId ??= (int) (session('active_company_id') ?? 0);
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_units'] ?? 0);

        if ($max <= 0 || ! $companyId) {
            return null;
        }

        $used = (new UnitModel())->countFor($companyId);

        return max(0, $max - $used);
    }

    /**
     * ¿Se puede crear un inmueble más? Devuelve el motivo si no.
     *
     * Un solo sitio decide, para que el listado (que pinta el aviso) y el guardado
     * (que lo impide) no puedan discrepar.
     *
     * @return string|null mensaje de error, o null si se puede
     */
    public function blockReason(?int $companyId = null): ?string
    {
        if (! $this->serviceEnabled($companyId)) {
            return lang('Cop.planDisabled');
        }

        $left = $this->unitsLeft($companyId);
        if ($left !== null && $left <= 0) {
            return lang('Cop.planUnitsExhausted');
        }

        return null;
    }
}
