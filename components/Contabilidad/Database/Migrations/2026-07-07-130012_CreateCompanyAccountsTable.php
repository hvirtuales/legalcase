<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Cuentas bancarias de la empresa (§3.15) — origen de pagos en Egresos. Portable. */
class CreateCompanyAccountsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_company_accounts')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'bank_name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'account_type'    => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Ahorros'],
            'account_number'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'account_holder'  => ['type' => 'VARCHAR', 'constraint' => 200, 'default' => ''],
            'initial_balance' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'is_active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_company_accounts');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_company_accounts', true);
    }
}
