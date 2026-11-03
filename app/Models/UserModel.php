<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\Auditable;

class UserModel extends Model
{
    use Auditable;

    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'is_active',
        'auth_method',
        'two_factor_enabled',
        'last_login_at',
        'last_login_ip',
        'failed_login_attempts',
        'locked_until',
        'preferred_language',
        'theme_color',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
        'password' => 'permit_empty|min_length[8]',
        'first_name' => 'required|max_length[50]',
        'last_name' => 'required|max_length[50]',
        'phone' => 'permit_empty|max_length[30]|regex_match[/^[0-9+\s()-]{6,30}$/]',
        'auth_method' => 'permit_empty|in_list[password,magic_link,oauth,ldap]',
        'preferred_language' => 'permit_empty|in_list[es,en]',
    ];

    protected $validationMessages = [
        'username' => [
            'required' => 'El nombre de usuario es requerido',
            'min_length' => 'El nombre de usuario debe tener al menos 3 caracteres',
            'is_unique' => 'Este nombre de usuario ya está en uso',
        ],
        'email' => [
            'required' => 'El correo electrónico es requerido',
            'valid_email' => 'Debe proporcionar un correo electrónico válido',
            'is_unique' => 'Este correo electrónico ya está registrado',
        ],
        'password' => [
            'min_length' => 'La contraseña debe tener al menos 8 caracteres',
        ],
        'phone' => [
            'regex_match' => 'El teléfono no tiene un formato válido',
            'max_length' => 'El teléfono no puede superar los 30 caracteres',
        ],
    ];

    protected $beforeInsert = ['hashPassword', 'setCreatedBy'];
    protected $beforeUpdate = ['hashPassword', 'setUpdatedBy'];
    protected $beforeDelete = ['setDeletedBy'];
    protected $afterInsert = ['auditInsert'];
    protected $afterUpdate = ['auditUpdate'];
    protected $afterDelete = ['auditDelete'];

    /**
     * Hash password before saving
     */
    protected function hashPassword(array $data): array
    {
        if (isset($data['data']['password']) && !empty($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_DEFAULT);
        }
        return $data;
    }

    /**
     * Caché **por petición** (no entre peticiones) de identidad y permisos.
     *
     * `getUserWithRoles()` y `hasPermission()` se preguntan muchas veces en una
     * sola pantalla: el filtro de auth, el de permisos, el sidebar (un enlace
     * por permiso) y los propios controladores. Sin esto, una página del panel
     * repetía la misma consulta 4–12 veces.
     *
     * Se invalida al tocar los roles (`assignRole`/`removeRole`) y se puede
     * limpiar a mano con `flushCache()` — necesario en CLI, donde un proceso
     * hace de varias peticiones (comandos, autotest, seeders).
     *
     * @var array<int, array|null>
     */
    private static array $userCache = [];
    /** @var array<int, list<string>> */
    private static array $permCache = [];

    public static function flushCache(?int $userId = null): void
    {
        if ($userId === null) {
            self::$userCache = [];
            self::$permCache = [];

            return;
        }

        unset(self::$userCache[$userId], self::$permCache[$userId]);
    }

    /**
     * Get user with roles
     */
    public function getUserWithRoles(int $userId): ?array
    {
        if (array_key_exists($userId, self::$userCache)) {
            return self::$userCache[$userId];
        }

        $user = $this->find($userId);

        if (!$user) {
            return self::$userCache[$userId] = null;
        }

        // Get user roles with role details
        $db = \Config\Database::connect();
        $roles = $db->table('user_roles')
            ->select('roles.id, roles.name, roles.description')
            ->join('roles', 'roles.id = user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->get()
            ->getResultArray();

        $user['roles'] = $roles;

        return self::$userCache[$userId] = $user;
    }

    /**
     * Get user with their permissions (through roles)
     */
    public function getUserPermissions(int $userId): array
    {
        $db = \Config\Database::connect();

        return $db->table('permissions')
            ->select('permissions.*')
            ->join('role_permissions', 'role_permissions.permission_id = permissions.id')
            ->join('user_roles', 'user_roles.role_id = role_permissions.role_id')
            ->where('user_roles.user_id', $userId)
            ->groupBy('permissions.id')
            ->get()
            ->getResultArray();
    }

    /**
     * Check if user has a specific permission
     *
     * Trae **todos** los permisos del usuario de una vez y responde en memoria:
     * el sidebar pregunta uno por enlace, así que preguntar de a uno a la base
     * era una consulta por enlace. El coste es el mismo para el primer permiso
     * y cero para los siguientes.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        if (!isset(self::$permCache[$userId])) {
            $db = \Config\Database::connect();

            self::$permCache[$userId] = array_column(
                $db->table('permissions')
                    ->select('permissions.name')
                    ->join('role_permissions', 'role_permissions.permission_id = permissions.id')
                    ->join('user_roles', 'user_roles.role_id = role_permissions.role_id')
                    ->where('user_roles.user_id', $userId)
                    ->groupBy('permissions.name')
                    ->get()
                    ->getResultArray(),
                'name'
            );
        }

        return in_array($permission, self::$permCache[$userId], true);
    }

    /**
     * Assign role to user
     */
    public function assignRole(int $userId, int $roleId): bool
    {
        $db = \Config\Database::connect();

        // Check if already assigned
        $exists = $db->table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->countAllResults();

        if ($exists > 0) {
            return true;
        }

        self::flushCache($userId); // sus roles y permisos acaban de cambiar

        return $db->table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    /**
     * Remove role from user
     */
    public function removeRole(int $userId, int $roleId): bool
    {
        $db = \Config\Database::connect();

        self::flushCache($userId);

        return $db->table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->delete();
    }

    /**
     * Update login tracking
     */
    public function updateLoginTracking(int $userId, string $ipAddress): void
    {
        $this->update($userId, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ipAddress,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedAttempts(int $userId): void
    {
        $user = $this->find($userId);
        if (!$user) {
            return;
        }

        $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
        $maxAttempts = 5; // From config

        $data = ['failed_login_attempts' => $attempts];

        // Lock account if max attempts reached
        if ($attempts >= $maxAttempts) {
            $lockDuration = 900; // 15 minutes
            $data['locked_until'] = date('Y-m-d H:i:s', time() + $lockDuration);
        }

        $this->update($userId, $data);
    }

    /**
     * Check if user account is locked
     */
    public function isLocked(int $userId): bool
    {
        $user = $this->find($userId);
        if (!$user) {
            return false;
        }

        if (empty($user['locked_until'])) {
            return false;
        }

        $lockedUntil = strtotime($user['locked_until']);
        if ($lockedUntil > time()) {
            return true;
        }

        // Unlock if time has passed
        $this->update($userId, [
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);

        return false;
    }
}
