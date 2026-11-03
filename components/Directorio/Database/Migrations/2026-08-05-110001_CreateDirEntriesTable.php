<?php

namespace Components\Directorio\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Entradas del directorio del grupo.
 *
 * Aislamiento por **`tenant_id`** (no `company_id`): el dato es del grupo.
 * `company_id` es opcional y apunta a una empresa **del mismo grupo** cuando la
 * entrada corresponde a una de sus empresas registradas; se valida en el
 * servicio contra `TenantGuard::childIdsOfTenants()`.
 */
class CreateDirEntriesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_dir_entries')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'category'    => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'location'    => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'website'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'sort_order'  => ['type' => 'INT', 'constraint' => 11, 'null' => false, 'default' => 0],
            'is_published' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->addForeignKey('tenant_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'SET NULL');
        $this->forge->createTable('cmp_dir_entries', true);
    }

    public function down()
    {
        $this->forge->dropTable('cmp_dir_entries', true);
    }
}
