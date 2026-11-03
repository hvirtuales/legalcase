<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuthProvidersTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('auth_providers')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'provider'         => ['type' => 'VARCHAR', 'constraint' => 50],
            'provider_user_id' => ['type' => 'VARCHAR', 'constraint' => 255],
            'provider_data'    => ['type' => 'TEXT', 'null' => true], // JSON como texto (portable)
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_by'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'provider']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('auth_providers');
    }

    public function down()
    {
        $this->forge->dropTable('auth_providers', true);
    }
}
