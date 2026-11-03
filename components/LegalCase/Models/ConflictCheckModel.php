<?php

namespace Components\LegalCase\Models;

/** Evidencia de cada verificación de conflicto de interés y de la decisión tomada. */
class ConflictCheckModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_conflict_checks';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['company_id', 'matter_id', 'contact_id', 'role', 'result', 'hits', 'decision', 'justification', 'decided_by', 'created_at'];

    public function forMatter(int $companyId, int $matterId): array
    {
        return $this->where('company_id', $companyId)->where('matter_id', $matterId)->orderBy('id', 'DESC')->findAll(50);
    }
}
