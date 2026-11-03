<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Permisos del catálogo de sectores.
 *
 * **Solo Super Admin**, igual que `tenants.*` y por el mismo motivo: los
 * sectores son vocabulario de plataforma. Si un administrador de empresa o de
 * grupo pudiera crearlos, cada uno inventaría el suyo y la clasificación
 * dejaría de servir para comparar — que es justo para lo que existe.
 *
 * Clasificar una empresa (elegir su sector) NO necesita estos permisos: eso se
 * hace desde el formulario de la empresa, con `companies.edit`.
 */
class SeedSectorPermissions extends Migration
{
    /** @var array<string,string> */
    private array $perms = [
        'sectors.view'   => 'Ver el catálogo de sectores',
        'sectors.create' => 'Crear sectores',
        'sectors.edit'   => 'Editar sectores',
        'sectors.delete' => 'Eliminar sectores',
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
                $table->insert(['name' => $name, 'description' => $desc, 'module' => 'sectors', 'created_at' => $now]);
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
