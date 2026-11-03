<?php

namespace Components\Paginas\Libraries;

use Components\Paginas\Models\PageModel;

/**
 * Hace valer el plan de la empresa activa sobre el componente (contrato §11):
 * si el servicio está incluido y cuántas páginas le quedan.
 *
 * El plan **efectivo** lo resuelve el núcleo (`CompanyModel::effectivePlan()`)
 * cuando existe: es quien sabe si la empresa cuelga de un grupo y qué plan rige
 * entonces. Ese método llegó con los **tenants**, así que una instalación sin
 * ellos no lo tiene y el componente lo resuelve por su cuenta —el plan de la
 * propia empresa, que es lo único que hay ahí— en vez de reventar con «Call to
 * undefined method» (contrato §15.2: llamar al núcleo por su firma más vieja y
 * degradar solo).
 *
 * Sin empresa activa o sin plan asignado → permitido e ilimitado: un catálogo de
 * planes a medio configurar no puede dejar a nadie sin poder trabajar.
 *
 * Se aplica **en el servicio y en el controlador**, no solo ocultando botones:
 * esconder el botón de «Nueva página» no impide un POST directo.
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

        $companies = new \App\Models\CompanyModel();

        if (method_exists($companies, 'effectivePlan')) {
            return $this->plan = $companies->effectivePlan($companyId);
        }

        return $this->plan = $this->ownPlan($companyId);
    }

    /**
     * El plan de la propia empresa, leyendo `companies.plan_id → plans`.
     *
     * Es lo que hace un núcleo sin tenants, donde no hay grupo del que heredar
     * vigencia ni plan. Se resuelve por Query Builder y no por el modelo de
     * planes para no arrastrar sus reglas de validación ni su soft-delete a una
     * simple lectura.
     */
    private function ownPlan(int $companyId): ?array
    {
        $db = \Config\Database::connect();

        $empresa = $db->table('companies')->select('plan_id')->where('id', $companyId)->get()->getRowArray();
        if (empty($empresa['plan_id'])) {
            return null;
        }

        return $db->table('plans')->where('id', (int) $empresa['plan_id'])->get()->getRowArray() ?: null;
    }

    /** ¿El plan incluye el constructor de páginas? Sin plan → sí. */
    public function serviceEnabled(?int $companyId = null): bool
    {
        $plan = $this->plan($companyId);
        if (!$plan || !array_key_exists('pages_enabled', $plan)) {
            return true;
        }

        return (int) $plan['pages_enabled'] === 1;
    }

    /**
     * Páginas que le quedan a la empresa; null = ilimitado.
     *
     * Cuenta todas las vivas, borradores incluidos: el cupo es de páginas
     * existentes, no de publicadas — un borrador ocupa sitio igual.
     */
    public function pagesLeft(?int $companyId = null): ?int
    {
        $companyId ??= (int) (session('active_company_id') ?? 0);
        $plan = $this->plan($companyId);
        $max  = (int) ($plan['max_pages'] ?? 0);
        if ($max <= 0 || !$companyId) {
            return null;
        }

        $used = (new PageModel())->where('company_id', $companyId)->countAllResults();

        return max(0, $max - $used);
    }

    /**
     * Motivo por el que NO se puede crear una página, o null si sí se puede.
     * Un único punto de decisión para los tres sitios que dan de alta (nueva,
     * duplicar y usar plantilla) en vez de repetir las dos comprobaciones.
     */
    public function blockedReason(?int $companyId = null): ?string
    {
        if (!$this->serviceEnabled($companyId)) {
            return lang('Pag.errPlanDisabled');
        }
        $left = $this->pagesLeft($companyId);
        if ($left !== null && $left <= 0) {
            return lang('Pag.errPlanQuota');
        }

        return null;
    }
}
