<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVoucherEntriesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_voucher_entries')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'voucher_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'account_code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'account_name' => ['type' => 'VARCHAR', 'constraint' => 200],
            'debit'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'credit'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'description'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sort_order'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('voucher_id');
        $this->forge->addForeignKey('voucher_id', 'cmp_cont_vouchers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_voucher_entries');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_voucher_entries', true);
    }
}
