<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // [name, description, module]
        $defs = [
            ['users.view', 'Ver listado de usuarios', 'users'],
            ['users.create', 'Crear nuevos usuarios', 'users'],
            ['users.edit', 'Editar usuarios existentes', 'users'],
            ['users.delete', 'Eliminar usuarios', 'users'],
            ['roles.view', 'Ver listado de roles', 'roles'],
            ['roles.create', 'Crear nuevos roles', 'roles'],
            ['roles.edit', 'Editar roles existentes', 'roles'],
            ['roles.delete', 'Eliminar roles', 'roles'],
            ['permissions.view', 'Ver listado de permisos', 'permissions'],
            ['permissions.create', 'Crear nuevos permisos', 'permissions'],
            ['permissions.edit', 'Editar permisos existentes', 'permissions'],
            ['permissions.delete', 'Eliminar permisos', 'permissions'],
            ['settings.view', 'Ver configuración del sistema', 'settings'],
            ['settings.edit', 'Editar configuración del sistema', 'settings'],
            ['audit.view', 'Ver registros de auditoría', 'audit'],
            ['audit.export', 'Exportar registros de auditoría', 'audit'],
            ['companies.view', 'Ver listado de empresas', 'companies'],
            ['companies.create', 'Crear empresas', 'companies'],
            ['companies.edit', 'Editar empresas', 'companies'],
            ['companies.delete', 'Eliminar empresas', 'companies'],
            ['plans.view', 'Ver listado de planes', 'plans'],
            ['plans.create', 'Crear planes', 'plans'],
            ['plans.edit', 'Editar planes', 'plans'],
            ['plans.delete', 'Eliminar planes', 'plans'],
            ['api.view', 'Ver gestor de API, documentación y tokens', 'api'],
            ['api.create', 'Crear tokens de API', 'api'],
            ['api.delete', 'Revocar tokens de API', 'api'],
            ['components.manage', 'Gestionar componentes/plugins de la plataforma', 'components'],
            ['tenants.view', 'Ver listado de tenants (grupos de empresas)', 'tenants'],
            ['tenants.create', 'Crear tenants', 'tenants'],
            ['tenants.edit', 'Editar tenants, su plan y sus empresas', 'tenants'],
            ['tenants.delete', 'Eliminar tenants', 'tenants'],
        ];

        $permTable = $this->db->table('permissions');
        foreach ($defs as [$name, $desc, $module]) {
            if (!$permTable->getWhere(['name' => $name])->getRow()) {
                $permTable->insert(['name' => $name, 'description' => $desc, 'module' => $module, 'created_at' => $now]);
            }
        }

        // Asignaciones por rol. Gestión de componentes y de tenants: solo Super
        // Admin. El administrador de un tenant NO lleva `tenants.*` — su panel
        // se gobierna por el vínculo (`company_users.is_admin` sobre la
        // fila-tenant), porque los roles son globales y darle el permiso le
        // daría poder sobre los tenants de toda la plataforma.
        $all      = static fn ($p) => true;
        $notPerms = static fn ($p) => !in_array($p['module'], ['permissions', 'components', 'tenants'], true);
        $onlyAudit = static fn ($p) => $p['module'] === 'audit';

        $this->grant('Super Admin', $all);
        $this->grant('Admin', $notPerms);
        $this->grant('Auditor', $onlyAudit);
    }

    /** Otorga a un rol los permisos que cumplan el filtro (idempotente). */
    private function grant(string $roleName, callable $filter): void
    {
        $role = $this->db->table('roles')->getWhere(['name' => $roleName])->getRow();
        if (!$role) {
            return;
        }
        $perms  = $this->db->table('permissions')->get()->getResultArray();
        $rpTable = $this->db->table('role_permissions');

        foreach ($perms as $p) {
            if (!$filter($p)) {
                continue;
            }
            $has = $rpTable->getWhere(['role_id' => $role->id, 'permission_id' => $p['id']])->getRow();
            if (!$has) {
                $rpTable->insert(['role_id' => $role->id, 'permission_id' => $p['id']]);
            }
        }
    }
}
