<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Movimientos de caja (§3.17): ingreso|egreso|arqueo|ajuste. Portable (VARCHAR en vez de ENUM). */
class CreateCashTransactionsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_cash_transactions')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cash_box_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'type'          => ['type' => 'VARCHAR', 'constraint' => 20], // ingreso|egreso|arqueo|ajuste
            'concept'       => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'amount'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'balance_after' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0], // arqueo/ajuste = efectivo contado
            'date'          => ['type' => 'DATE', 'null' => true],
            'reference'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('cash_box_id');
        $this->forge->addForeignKey('cash_box_id', 'cmp_cont_cash_boxes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_cash_transactions');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_cash_transactions', true);
    }
}
