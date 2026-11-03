<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\Auditable;

class RoleModel extends Model
{
    use Auditable;

    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[50]|is_unique[roles.name,id,{id}]',
        'description' => 'permit_empty|max_length[500]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'El nombre del rol es requerido',
            'is_unique' => 'Este nombre de rol ya existe',
        ],
    ];

    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = ['setUpdatedBy'];
    protected $afterInsert = ['auditInsert'];
    protected $afterUpdate = ['auditUpdate'];
    protected $afterDelete = ['auditDelete'];

    /**
     * Get role with its permissions
     */
    public function getRoleWithPermissions(int $roleId): ?array
    {
        $role = $this->find($roleId);
        if (!$role) {
            return null;
        }

        $db = \Config\Database::connect();
        $permissions = $db->table('role_permissions')
            ->select('permissions.*')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->get()
            ->getResultArray();

        $role['permissions'] = $permissions;
        return $role;
    }

    /**
     * Assign permission to role
     */
    public function assignPermission(int $roleId, int $permissionId): bool
    {
        $db = \Config\Database::connect();

        // Check if already assigned
        $exists = $db->table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->countAllResults();

        if ($exists > 0) {
            return true;
        }

        return $db->table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    /**
     * Remove permission from role
     */
    public function removePermission(int $roleId, int $permissionId): bool
    {
        $db = \Config\Database::connect();

        return $db->table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }

    /**
     * Sync permissions for a role
     */
    public function syncPermissions(int $roleId, array $permissionIds): bool
    {
        $db = \Config\Database::connect();

        // Remove all current permissions
        $db->table('role_permissions')
            ->where('role_id', $roleId)
            ->delete();

        // Add new permissions
        if (empty($permissionIds)) {
            return true;
        }

        $data = [];
        foreach ($permissionIds as $permissionId) {
            $data[] = [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ];
        }

        return $db->table('role_permissions')->insertBatch($data);
    }

    /**
     * Get users count for this role
     */
    public function getUsersCount(int $roleId): int
    {
        $db = \Config\Database::connect();

        return $db->table('user_roles')
            ->where('role_id', $roleId)
            ->countAllResults();
    }
}
