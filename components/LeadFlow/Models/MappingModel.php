<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * `integration_mappings` (§21): id local → id externo.
 *
 * Es la pieza que hace idempotente la conversión: cada paso consulta aquí antes
 * de crear nada en Docketwise, y guarda su id en cuanto lo obtiene. Un reintento
 * tras un fallo a mitad encuentra lo ya creado y continúa.
 */
class MappingModel extends Model
{
    use CompanyScoped;

    public const TYPES = ['contact', 'matter', 'summary', 'note', 'document'];

    protected $table         = 'cmp_lfl_mappings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'provider', 'entity_type', 'local_id', 'external_id'];

    public function external(int $companyId, string $provider, string $type, int $localId): ?string
    {
        $row = $this->where('company_id', $companyId)->where('provider', $provider)
            ->where('entity_type', $type)->where('local_id', $localId)->first();

        return $row['external_id'] ?? null;
    }

    public function put(int $companyId, string $provider, string $type, int $localId, string $externalId): void
    {
        $row = $this->where('company_id', $companyId)->where('provider', $provider)
            ->where('entity_type', $type)->where('local_id', $localId)->first();

        $row
            ? $this->update((int) $row['id'], ['external_id' => $externalId])
            : $this->insert(['company_id' => $companyId, 'provider' => $provider, 'entity_type' => $type,
                'local_id' => $localId, 'external_id' => $externalId]);
    }
}
