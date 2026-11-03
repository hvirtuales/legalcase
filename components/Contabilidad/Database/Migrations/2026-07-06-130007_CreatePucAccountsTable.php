<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Plan Único de Cuentas — GLOBAL (compartido por todas las empresas). Sin
 * company_id. Portable (VARCHAR en vez de ENUM). Jerarquía por parent_code.
 */
class CreatePucAccountsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_puc_accounts')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 200],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 20], // activo|pasivo|patrimonio|ingreso|gasto|costo
            'nature'      => ['type' => 'VARCHAR', 'constraint' => 20], // debito|credito
            'level'       => ['type' => 'INT', 'constraint' => 4, 'default' => 1],
            'parent_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('parent_code');
        $this->forge->createTable('cmp_cont_puc_accounts');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_puc_accounts', true);
    }
}
