<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Cuentas de cobro / solicitudes de pago (§3.13). Portable, prefijada, por empresa. */
class CreatePaymentRequestsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_payment_requests')) {
            return;
        }

        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'reference'             => ['type' => 'VARCHAR', 'constraint' => 40],
            'collaborator_name'     => ['type' => 'VARCHAR', 'constraint' => 200],
            'collaborator_id_type'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'CC'],
            'collaborator_id'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'collaborator_email'    => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'collaborator_phone'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'collaborator_city'     => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'collaborator_address'  => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'collaborator_contact'  => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'collaborator_bank'     => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
            'collaborator_account_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Ahorros'],
            'collaborator_account'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'concept'               => ['type' => 'TEXT', 'null' => true],
            'period'                => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'items_json'            => ['type' => 'TEXT', 'null' => true], // JSON como texto (portable)
            'amount'                => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'iva_amount'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'retencion_rate'        => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'retencion_amount'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total_amount'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'cert_no_iva'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'cert_art383'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'signature_data'        => ['type' => 'TEXT', 'null' => true],
            'signed_at'             => ['type' => 'DATETIME', 'null' => true],
            'status'                => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], // pending|approved|rejected
            'admin_notes'           => ['type' => 'TEXT', 'null' => true],
            'reviewed_by'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'reviewed_at'           => ['type' => 'DATETIME', 'null' => true],
            'voucher_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'reference']);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_payment_requests');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_payment_requests', true);
    }
}
