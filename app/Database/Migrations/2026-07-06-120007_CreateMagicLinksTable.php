<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMagicLinksTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('magic_links')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'token'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'used_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('email');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('magic_links');
    }

    public function down()
    {
        $this->forge->dropTable('magic_links', true);
    }
}
