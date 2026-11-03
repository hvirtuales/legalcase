<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pivote usuario↔empresa (company_users). Aísla usuarios por empresa.
 */
class CompanyUserModel extends Model
{
    protected $table          = 'company_users';
    protected $primaryKey      = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id',
        'user_id',
        'is_admin',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Vincula un usuario a una empresa (idempotente). */
    public function addUser(int $companyId, int $userId, bool $isAdmin = false): bool
    {
        $existing = $this->where('company_id', $companyId)->where('user_id', $userId)->first();
        if ($existing) {
            if ($isAdmin && empty($existing['is_admin'])) {
                $this->update($existing['id'], ['is_admin' => 1]);
            }

            return true;
        }

        return (bool) $this->insert([
            'company_id' => $companyId,
            'user_id'    => $userId,
            'is_admin'   => $isAdmin ? 1 : 0,
            'created_by' => session()->get('user_id'),
        ]);
    }

    public function removeUser(int $companyId, int $userId): bool
    {
        return $this->where('company_id', $companyId)->where('user_id', $userId)->delete();
    }

    /** IDs de usuarios de una empresa. */
    public function getUserIds(int $companyId): array
    {
        return array_map('intval', array_column(
            $this->select('user_id')->where('company_id', $companyId)->findAll(),
            'user_id'
        ));
    }

    /** Usuarios (filas de users) de una empresa, con la marca is_admin. */
    public function getUsers(int $companyId): array
    {
        return $this->select('users.*, company_users.is_admin')
            ->join('users', 'users.id = company_users.user_id')
            ->where('company_users.company_id', $companyId)
            ->where('users.deleted_at', null)
            ->orderBy('users.first_name', 'ASC')
            ->findAll();
    }

    public function isMember(int $companyId, int $userId): bool
    {
        return $this->where('company_id', $companyId)->where('user_id', $userId)->countAllResults() > 0;
    }

    public function memberCount(int $companyId): int
    {
        return $this->where('company_id', $companyId)->countAllResults();
    }
}
