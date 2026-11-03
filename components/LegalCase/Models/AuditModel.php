<?php

namespace Components\LegalCase\Models;

/**
 * Bitácora del componente. **Solo inserción**: el modelo rechaza update y delete, para
 * que la evidencia de publicaciones, accesos y validaciones no se pueda reescribir
 * desde la aplicación.
 */
class AuditModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_audit';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['company_id', 'matter_id', 'actor_type', 'actor_id', 'action', 'entity', 'entity_id', 'detail', 'ip', 'created_at'];

    protected $beforeUpdate = ['readOnly'];
    protected $beforeDelete = ['readOnly'];

    protected function readOnly(array $data): array
    {
        throw new \RuntimeException('cmp_lgc_audit es de solo inserción.');
    }

    public function forMatter(int $companyId, int $matterId, int $limit = 200): array
    {
        return $this->where('company_id', $companyId)->where('matter_id', $matterId)
            ->orderBy('id', 'DESC')->findAll($limit);
    }
}
