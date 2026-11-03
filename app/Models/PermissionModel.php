<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\Auditable;

class PermissionModel extends Model
{
    use Auditable;

    protected $table = 'permissions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'name',
        'description',
        'module',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[100]|is_unique[permissions.name,id,{id}]',
        'description' => 'permit_empty|max_length[500]',
        'module' => 'required|max_length[50]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'El nombre del permiso es requerido',
            'is_unique' => 'Este permiso ya existe',
        ],
        'module' => [
            'required' => 'El módulo es requerido',
        ],
    ];

    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = ['setUpdatedBy'];
    protected $afterInsert = ['auditInsert'];
    protected $afterUpdate = ['auditUpdate'];
    protected $afterDelete = ['auditDelete'];

    /**
     * Get all permissions grouped by module
     */
    public function getGroupedByModule(): array
    {
        $permissions = $this->orderBy('module', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($permissions as $permission) {
            $module = $permission['module'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $permission;
        }

        return $grouped;
    }

    /**
     * Get permissions by module
     */
    public function getByModule(string $module): array
    {
        return $this->where('module', $module)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Get roles that have this permission
     */
    public function getRolesWithPermission(int $permissionId): array
    {
        $db = \Config\Database::connect();

        return $db->table('role_permissions')
            ->select('roles.*')
            ->join('roles', 'roles.id = role_permissions.role_id')
            ->where('role_permissions.permission_id', $permissionId)
            ->get()
            ->getResultArray();
    }
}
