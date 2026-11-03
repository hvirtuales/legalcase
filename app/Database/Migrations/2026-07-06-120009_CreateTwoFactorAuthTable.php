<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTwoFactorAuthTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('two_factor_auth')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'secret'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'recovery_codes' => ['type' => 'TEXT', 'null' => true], // JSON como texto (portable)
            'enabled'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('two_factor_auth');
    }

    public function down()
    {
        $this->forge->dropTable('two_factor_auth', true);
    }
}
