<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/** "Otros impuestos" por ítem (ReteICA, ReteIVA, …) — categoría 'other' del catálogo. */
class CreateVoucherItemTaxesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_voucher_item_taxes')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'item_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tax_config_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 120],
            'rate'          => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'amount'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'is_additive'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('item_id');
        $this->forge->addForeignKey('item_id', 'cmp_cont_voucher_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_voucher_item_taxes');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_voucher_item_taxes', true);
    }
}
