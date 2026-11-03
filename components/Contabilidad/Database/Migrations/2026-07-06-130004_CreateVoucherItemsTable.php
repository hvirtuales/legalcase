<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVoucherItemsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_voucher_items')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'voucher_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'description'      => ['type' => 'TEXT'],
            'amount'           => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'iva_rate'         => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'iva_amount'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'retencion_rate'   => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'retencion_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'sort_order'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('voucher_id');
        $this->forge->addForeignKey('voucher_id', 'cmp_cont_vouchers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_voucher_items');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_voucher_items', true);
    }
}
