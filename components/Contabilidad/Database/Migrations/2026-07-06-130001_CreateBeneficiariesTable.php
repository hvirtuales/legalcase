<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabla de beneficiarios/terceros del componente. Portable (VARCHAR/TEXT, todo
 * por Forge), con prefijo cmp_cont_ y FK a companies del núcleo. Idempotente.
 */
class CreateBeneficiariesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cont_beneficiaries')) {
            return;
        }

        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'                => ['type' => 'VARCHAR', 'constraint' => 200],
            'id_type'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NIT'],
            'id_number'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'email'               => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'phone'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'address'             => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'city'                => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'contact_name'        => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'bank_name'           => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
            'bank_account_type'   => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Ahorros'],
            'bank_account_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_by'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deleted_by'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'id_type', 'id_number']);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cont_beneficiaries');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cont_beneficiaries', true);
    }
}
