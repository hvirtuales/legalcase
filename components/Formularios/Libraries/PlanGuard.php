<?php

namespace Components\Formularios\Libraries;

use Components\Formularios\Models\FormModel;
use Components\Formularios\Models\ResponseModel;

/**
 * Hace valer el plan de la empresa sobre el componente (contrato 11): si el
 * servicio está incluido, cuántos formularios le quedan y cuántas respuestas.
 *
 * El plan EFECTIVO lo resuelve el núcleo (`CompanyModel::effectivePlan()`)
 * cuando existe: es quien sabe si la empresa cuelga de un grupo y qué plan rige
 * entonces. Ese método llegó con los tenants, así que una instalación sin ellos
 * no lo tiene y aquí se resuelve el plan de la propia empresa en vez de reventar
 * con «Call to undefined method» (contrato 15.2: llamar al núcleo por su firma
 * más vieja y degradar solo).
 *
 * Sin empresa activa o sin plan asignado: permitido e ilimitado. Un catálogo de
 * planes a medio configurar no puede dejar a nadie sin poder trabajar.
 *
 * Se aplica EN EL SERVICIO Y EN EL CONTROLADOR, no ocultando botones: esconder
 * el de «Nuevo formulario» no impide un POST directo.
 */
class PlanGuard
{
    private ?array $plan  = null;
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

        $companies = new \App\Models\CompanyModel();

        if (method_exists($companies, 'effectivePlan')) {
            return $this->plan = $companies->effectivePlan($companyId);
        }

        return $this->plan = $this->ownPlan($companyId);
    }

    /** El plan de la propia empresa: lo que hace un núcleo sin tenants. */
    private function ownPlan(int $companyId): ?array
    {
        $db = \Config\Database::connect();

        $company = $db->table('companies')->select('plan_id')->where('id', $companyId)->get()->getRowArray();
        if (empty($company['plan_id'])) {
            return null;
        }

        return $db->table('plans')->where('id', (int) $company['plan_id'])->get()->getRowArray() ?: null;
    }

    /** ¿El plan incluye el constructor de formularios? Sin plan, sí. */
    public function serviceEnabled(?int $companyId = null): bool
    {
        $plan = $this->plan($companyId);
        if (!$plan || !array_key_exists('forms_enabled', $plan)) {
            return true;
        }

        return (int) $plan['forms_enabled'] === 1;
    }

    /** Formularios que le quedan; null = ilimitado. Un borrador ocupa sitio igual. */
    public function formsLeft(?int $companyId = null): ?int
    {
        $companyId ??= (int) (session('active_company_id') ?? 0);
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_forms'] ?? 0);
        if ($max <= 0 || !$companyId) {
            return null;
        }

        return max(0, $max - (new FormModel())->where('company_id', $companyId)->countAllResults());
    }

    /** Respuestas que le quedan a la empresa en total; null = ilimitado. */
    public function responsesLeft(?int $companyId = null): ?int
    {
        $companyId ??= (int) (session('active_company_id') ?? 0);
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_responses'] ?? 0);
        if ($max <= 0 || !$companyId) {
            return null;
        }

        return max(0, $max - (new ResponseModel())->where('company_id', $companyId)->countAllResults());
    }

    /**
     * Motivo por el que NO se puede crear un formulario, o null si sí se puede.
     * Un único punto de decisión para las tres vías de alta (nuevo, duplicar y
     * usar plantilla) en vez de repetir las dos comprobaciones en cada una.
     */
    public function blockedReason(?int $companyId = null): ?string
    {
        if (!$this->serviceEnabled($companyId)) {
            return lang('Frm.errPlanDisabled');
        }
        $left = $this->formsLeft($companyId);
        if ($left !== null && $left <= 0) {
            return lang('Frm.errPlanQuota');
        }

        return null;
    }

    /** Lo mismo para recibir una respuesta: el cupo también se consume al contestar. */
    public function blockedForResponse(?int $companyId = null): ?string
    {
        if (!$this->serviceEnabled($companyId)) {
            return lang('Frm.pubNotAvailable');
        }
        $left = $this->responsesLeft($companyId);
        if ($left !== null && $left <= 0) {
            return lang('Frm.pubFull');
        }

        return null;
    }
}
