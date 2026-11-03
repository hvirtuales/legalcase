<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Listas de contactos («bases de datos»). Aisladas por empresa. */
class CreateMailerListsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_mailer_lists')) {
            return;
        }
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_mailer_lists');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mailer_lists', true);
    }
}
