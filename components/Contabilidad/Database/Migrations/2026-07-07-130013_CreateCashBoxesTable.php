<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Cajas menores (§3.16). Portable, por empresa. */
class CreateCashBoxesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_cash_boxes')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 120],
            'description'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'initial_balance' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_cash_boxes');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_cash_boxes', true);
    }
}
