<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVouchersTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_vouchers')) {
            return;
        }

        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'type_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'number'               => ['type' => 'VARCHAR', 'constraint' => 30],
            'date'                 => ['type' => 'DATE'],
            'beneficiary_name'     => ['type' => 'VARCHAR', 'constraint' => 200, 'default' => ''],
            'beneficiary_id'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'description'          => ['type' => 'TEXT', 'null' => true],
            'payment_method'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'Efectivo'],
            'source_account_label' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'subtotal'             => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total_iva'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total_retencion'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total_amount'         => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'emitido'], // emitido|pagado|anulado
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            'created_by'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deleted_by'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'number']);
        $this->forge->addKey('company_id');
        $this->forge->addKey('type_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('type_id', 'cmp_cont_voucher_types', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('cmp_cont_vouchers');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_vouchers', true);
    }
}
