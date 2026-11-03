<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Maestro de clientes a quienes se cotiza (por empresa). */
class CreateQuoClientsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_quo_clients')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 200], // razón social
            'nit'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'city'         => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'address'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'contact_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'phone'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'email'        => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'email2'       => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'notes'        => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'name']);
        $this->forge->addKey(['company_id', 'nit']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_quo_clients');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_quo_clients', true);
    }
}
