<?php

namespace Components\Mapas\Libraries;

use App\Models\CompanyModel;
use Components\Mapas\Models\MapModel;

/**
 * ¿El plan de la empresa incluye este componente, y cuántos mapas le quedan?
 *
 * Las dos comprobaciones se aplican **en el servicio y en el controlador**, no
 * ocultando el botón: un POST directo se salta la vista.
 */
class PlanGuard
{
    /** Plan efectivo de la empresa, con la firma más vieja que pueda tener el núcleo. */
    private function plan(int $companyId): ?array
    {
        $cm = new CompanyModel();

        // `effectivePlan()` llegó con los tenants; en un núcleo anterior no
        // existe y llamarlo mataría el componente con «undefined method».
        if (method_exists($cm, 'effectivePlan')) {
            return $cm->effectivePlan($companyId) ?: null;
        }
        $company = $cm->find($companyId);
        if (!$company || empty($company['plan_id'])) {
            return null;
        }

        return db_connect()->table('plans')->where('id', (int) $company['plan_id'])->get()->getRowArray() ?: null;
    }

    public function serviceEnabled(int $companyId): bool
    {
        $plan = $this->plan($companyId);

        // Sin plan, o con un plan que no conoce esta columna, se permite. Una
        // columna que falta significa «esta instalación no gradúa este
        // servicio», no «está prohibido»: es el error que dejó el Mailer
        // apagado para todo el mundo durante meses.
        if (!$plan || !array_key_exists('maps_enabled', $plan)) {
            return true;
        }

        return (int) $plan['maps_enabled'] === 1;
    }

    /** Mapas que puede crear todavía; `null` = sin límite. */
    public function mapsLeft(int $companyId): ?int
    {
        $plan = $this->plan($companyId);
        if (!$plan || !array_key_exists('max_maps', $plan)) {
            return null;
        }
        $max = (int) $plan['max_maps'];
        if ($max <= 0) {
            return null;
        }
        $usados = (new MapModel())->where('company_id', $companyId)->countAllResults();

        return max(0, $max - $usados);
    }

    public function canCreateMap(int $companyId): bool
    {
        if (!$this->serviceEnabled($companyId)) {
            return false;
        }
        $left = $this->mapsLeft($companyId);

        return $left === null || $left > 0;
    }
}
