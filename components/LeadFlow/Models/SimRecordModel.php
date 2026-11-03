<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * El «Docketwise» del adaptador simulado: una cuenta falsa por firma, con sus
 * contactos, matters, notas, documentos, usuarios y tipos de matter. El id de la
 * fila hace de id externo.
 */
class SimRecordModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_sim_records';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['company_id', 'entity', 'payload', 'created_at'];

    public function add(int $companyId, string $entity, array $payload): string
    {
        $this->insert([
            'company_id' => $companyId,
            'entity'     => $entity,
            'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (string) $this->getInsertID();
    }

    /** @return array<int,array> id => payload (+ id) */
    public function all(int $companyId, string $entity): array
    {
        $out = [];
        foreach ($this->forCompany($companyId)->where('entity', $entity)->orderBy('id', 'asc')->findAll() as $r) {
            $out[(int) $r['id']] = (json_decode((string) $r['payload'], true) ?: []) + ['id' => (int) $r['id']];
        }

        return $out;
    }

    public function one(int $companyId, string $entity, int $id): ?array
    {
        $r = $this->forCompany($companyId)->where('entity', $entity)->where('id', $id)->first();

        return $r ? (json_decode((string) $r['payload'], true) ?: []) + ['id' => (int) $r['id']] : null;
    }

    /**
     * Fusiona claves en el payload de un registro. Devuelve false si no existe o no
     * es de esta empresa: el simulado tiene que aislar igual que el real.
     */
    public function patch(int $companyId, string $entity, int $id, array $changes): bool
    {
        $row = $this->forCompany($companyId)->where('entity', $entity)->where('id', $id)->first();
        if (! $row) {
            return false;
        }
        $payload = (json_decode((string) $row['payload'], true) ?: []);

        return (bool) $this->update($id, [
            'payload' => json_encode($changes + $payload, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
