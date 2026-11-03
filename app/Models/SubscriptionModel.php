<?php

namespace App\Models;

use App\Traits\Auditable;
use CodeIgniter\Model;

class SubscriptionModel extends Model
{
    use Auditable;

    protected $table          = 'subscriptions';
    protected $primaryKey      = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false; // la tabla no tiene deleted_at
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'plan_id'    => 'required|is_natural_no_zero',
        'status'     => 'required|in_list[trial,active,expired,cancelled]',
    ];

    // Solo insert/update (la tabla no tiene deleted_by)
    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = ['setUpdatedBy'];
    protected $afterInsert  = ['auditInsert'];
    protected $afterUpdate  = ['auditUpdate'];

    /** Suscripción vigente (más reciente) de una empresa. */
    public function getActiveForCompany(int $companyId): ?array
    {
        return $this->where('company_id', $companyId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * ¿La suscripción está vigente? No lo está si el estado es expired/cancelled
     * o si ya pasó su end_date.
     */
    public function isValid(?array $sub): bool
    {
        if (!$sub) {
            return false;
        }
        if (in_array($sub['status'] ?? '', ['expired', 'cancelled'], true)) {
            return false;
        }
        if (!empty($sub['end_date']) && strtotime($sub['end_date']) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Marca como 'expired' (en BD) la suscripción si su end_date ya pasó y aún
     * figura como trial/active. Devuelve la suscripción (posiblemente actualizada).
     */
    public function markExpiredIfNeeded(?array $sub): ?array
    {
        if (!$sub || in_array($sub['status'] ?? '', ['expired', 'cancelled'], true)) {
            return $sub;
        }
        if (!empty($sub['end_date']) && strtotime($sub['end_date']) < time()) {
            $this->update($sub['id'], ['status' => 'expired']);
            $sub['status'] = 'expired';
        }

        return $sub;
    }

    /**
     * Última suscripción por empresa para un conjunto de ids.
     *
     * @return array<int, array> mapa companyId => suscripción
     */
    public function getMapForCompanies(array $companyIds): array
    {
        if (empty($companyIds)) {
            return [];
        }
        $rows = $this->whereIn('company_id', $companyIds)->orderBy('id', 'DESC')->findAll();
        $map  = [];
        foreach ($rows as $r) {
            $cid = (int) $r['company_id'];
            if (!isset($map[$cid])) {
                $map[$cid] = $r;
            }
        }

        return $map;
    }

    /**
     * Crea una suscripción calculando end_date desde los días de servicio del
     * plan si no se provee.
     */
    public function createForCompany(int $companyId, int $planId, string $status, ?string $startDate, ?string $endDate, int $serviceDays = 0): int|false
    {
        $start = $startDate ?: date('Y-m-d H:i:s');
        if (!$endDate) {
            $endDate = date('Y-m-d H:i:s', strtotime($start . ' + ' . max(0, $serviceDays) . ' days'));
        }

        return $this->insert([
            'company_id' => $companyId,
            'plan_id'    => $planId,
            'start_date' => $start,
            'end_date'   => $endDate,
            'status'     => $status,
        ]);
    }
}
