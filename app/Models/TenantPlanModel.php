<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lista blanca de planes de empresa que un tenant puede repartir entre sus
 * empresas (`tenant_plans`).
 *
 * La define el **Super Admin** al crear o editar el tenant; el administrador
 * del tenant solo elige dentro de ella. Uno de los planes puede quedar marcado
 * como `is_default`: es el que recibe automáticamente una empresa recién
 * colgada del grupo, para que no quede sin servicios por un olvido.
 */
class TenantPlanModel extends Model
{
    protected $table          = 'tenant_plans';
    protected $primaryKey      = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'tenant_id',
        'plan_id',
        'is_default',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** IDs de los planes habilitados para un tenant. */
    public function planIdsFor(int $tenantId): array
    {
        return array_map('intval', array_column(
            $this->select('plan_id')->where('tenant_id', $tenantId)->findAll(),
            'plan_id'
        ));
    }

    /**
     * Planes (filas de `plans`) que el tenant puede repartir, ya filtrados a los
     * **activos y de ámbito empresa**: un plan retirado del catálogo o convertido
     * en plan de tenant deja de ofrecerse aunque siga en la lista blanca.
     */
    public function plansFor(int $tenantId): array
    {
        $ids = $this->planIdsFor($tenantId);
        if (!$ids) {
            return [];
        }

        $plans = (new PlanModel())->whereIn('id', $ids)->where('is_active', 1)->orderBy('price_amount', 'ASC')->findAll();

        return array_values(array_filter($plans, static fn ($p) => PlanModel::scopeOf($p) === 'company'));
    }

    /** El plan por defecto del tenant (el que recibe una empresa recién colgada); null si no hay. */
    public function defaultPlanId(int $tenantId): ?int
    {
        $row = $this->where('tenant_id', $tenantId)->where('is_default', 1)->first();

        return $row ? (int) $row['plan_id'] : null;
    }

    /** ¿Ese plan está habilitado para ese tenant? */
    public function allows(int $tenantId, int $planId): bool
    {
        return in_array($planId, array_map(static fn ($p) => (int) $p['id'], $this->plansFor($tenantId)), true);
    }

    /**
     * Reemplaza la lista blanca completa del tenant.
     *
     * @param list<int> $planIds     planes habilitados
     * @param int|null  $defaultPlan cuál de ellos es el que se asigna por defecto
     */
    public function sync(int $tenantId, array $planIds, ?int $defaultPlan = null): void
    {
        $planIds = array_values(array_unique(array_map('intval', $planIds)));

        $this->where('tenant_id', $tenantId)->delete();
        if (!$planIds) {
            return;
        }

        // El «por defecto» tiene que estar entre los habilitados; si no, no hay.
        if ($defaultPlan !== null && !in_array($defaultPlan, $planIds, true)) {
            $defaultPlan = null;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($planIds as $planId) {
            $rows[] = [
                'tenant_id'  => $tenantId,
                'plan_id'    => $planId,
                'is_default' => $defaultPlan === $planId ? 1 : 0,
                'created_by' => session()->get('user_id'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->db->table($this->table)->insertBatch($rows);
    }
}
