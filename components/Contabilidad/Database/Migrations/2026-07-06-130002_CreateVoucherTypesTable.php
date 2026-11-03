<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVoucherTypesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_voucher_types')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 80],
            'prefix'           => ['type' => 'VARCHAR', 'constraint' => 10],
            'current_sequence' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_voucher_types');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_voucher_types', true);
    }
}
