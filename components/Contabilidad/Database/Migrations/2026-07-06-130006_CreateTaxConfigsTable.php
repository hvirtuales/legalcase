<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTaxConfigsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_tax_configs')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'category'    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'iva'], // iva|retencion|other
            'rate'        => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'is_additive' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deleted_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_tax_configs');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_tax_configs', true);
    }
}
