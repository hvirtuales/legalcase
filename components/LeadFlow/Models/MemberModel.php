<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * La función comercial de cada usuario dentro de la firma.
 *
 * No es un rol del núcleo: los roles del núcleo son globales, y «el asesor solo
 * ve sus leads» es una regla de visibilidad **de esta firma**. Ver `Access`.
 */
class MemberModel extends Model
{
    use CompanyScoped;

    public const ROLES = ['supervisor', 'advisor', 'lawyer', 'auditor'];

    protected $table         = 'cmp_lfl_members';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'user_id', 'role', 'docketwise_user_id', 'is_active'];

    public function roleOf(int $companyId, int $userId): ?string
    {
        $row = $this->where('company_id', $companyId)->where('user_id', $userId)->where('is_active', 1)->first();

        return $row && in_array($row['role'], self::ROLES, true) ? $row['role'] : null;
    }

    /** @return array<int,array> user_id => fila */
    public function mapFor(int $companyId): array
    {
        $out = [];
        foreach ($this->where('company_id', $companyId)->findAll() as $r) {
            $out[(int) $r['user_id']] = $r;
        }

        return $out;
    }

    public function setFor(int $companyId, int $userId, ?string $role, ?string $dwUser): void
    {
        $row = $this->where('company_id', $companyId)->where('user_id', $userId)->first();

        if ($role === null || ! in_array($role, self::ROLES, true)) {
            if ($row) {
                $this->delete((int) $row['id']);
            }

            return;
        }

        $data = [
            'company_id'         => $companyId,
            'user_id'            => $userId,
            'role'               => $role,
            'docketwise_user_id' => $dwUser !== null && $dwUser !== '' ? mb_substr($dwUser, 0, 40) : null,
            'is_active'          => 1,
        ];

        $row ? $this->update((int) $row['id'], $data) : $this->insert($data);
    }

    public function docketwiseUserOf(int $companyId, int $userId): ?string
    {
        $row = $this->where('company_id', $companyId)->where('user_id', $userId)->first();

        return $row['docketwise_user_id'] ?? null;
    }
}
