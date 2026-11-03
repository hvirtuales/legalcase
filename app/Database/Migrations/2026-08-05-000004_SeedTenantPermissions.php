<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Permisos del nivel tenant.
 *
 * Se conceden **solo a Super Admin**: gestionar el catálogo de tenants es
 * administración de plataforma. El administrador de un tenant NO necesita
 * ninguno de estos permisos — su panel (`/tenant`) se gobierna por el
 * **vínculo** (`company_users.is_admin` sobre la fila-tenant), igual que
 * «Mi empresa» se gobierna por `CompanyModel::isManager()`.
 *
 * Es a propósito: los roles de la plataforma son globales (`hasPermission()`
 * une todos los roles del usuario sin mirar contexto), así que un rol
 * «Administrador de tenant» con permisos de empresas le daría ese poder sobre
 * TODAS las empresas de la plataforma, no solo sobre su grupo. El alcance sale
 * del vínculo, no del rol.
 */
class SeedTenantPermissions extends Migration
{
    /** @var array<string,string> */
    private array $perms = [
        'tenants.view'   => 'Ver listado de tenants (grupos de empresas)',
        'tenants.create' => 'Crear tenants',
        'tenants.edit'   => 'Editar tenants, su plan y sus empresas',
        'tenants.delete' => 'Eliminar tenants',
    ];

    public function up()
    {
        if (!$this->db->tableExists('permissions')) {
            return;
        }

        $now   = date('Y-m-d H:i:s');
        $table = $this->db->table('permissions');

        foreach ($this->perms as $name => $desc) {
            if (!$table->getWhere(['name' => $name])->getRow()) {
                $table->insert(['name' => $name, 'description' => $desc, 'module' => 'tenants', 'created_at' => $now]);
            }
        }

        $role = $this->db->table('roles')->getWhere(['name' => 'Super Admin'])->getRow();
        if (!$role) {
            return;
        }

        foreach (array_keys($this->perms) as $name) {
            $p = $this->db->table('permissions')->getWhere(['name' => $name])->getRow();
            if ($p && !$this->db->table('role_permissions')->getWhere(['role_id' => $role->id, 'permission_id' => $p->id])->getRow()) {
                $this->db->table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $p->id]);
            }
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('permissions')) {
            return;
        }

        $ids = array_column(
            $this->db->table('permissions')->select('id')->whereIn('name', array_keys($this->perms))->get()->getResultArray(),
            'id'
        );
        if ($ids) {
            $this->db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
            $this->db->table('permissions')->whereIn('id', $ids)->delete();
        }
    }
}
