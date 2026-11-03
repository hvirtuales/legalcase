<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('users')) {
            return;
        }

        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'username'              => ['type' => 'VARCHAR', 'constraint' => 50],
            'email'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'password'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'first_name'            => ['type' => 'VARCHAR', 'constraint' => 50],
            'last_name'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'phone'                 => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'is_active'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'reset_hash'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'auth_method'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'password'], // portable (validado en el modelo; antes ENUM)
            'two_factor_enabled'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'last_login_at'         => ['type' => 'DATETIME', 'null' => true],
            'last_login_ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'failed_login_attempts' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'locked_until'          => ['type' => 'DATETIME', 'null' => true],
            'preferred_language'    => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'es'],
            'theme_color'           => ['type' => 'VARCHAR', 'constraint' => 7, 'null' => true, 'comment' => 'User personal theme color preference in hex format (#RRGGBB)'],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'            => ['type' => 'DATETIME', 'null' => true],
            'created_by'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deleted_by'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reset_expires_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('deleted_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('users');
    }

    public function down()
    {
        $this->forge->dropTable('users', true);
    }
}
