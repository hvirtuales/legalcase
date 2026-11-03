<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompanyUsersTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('company_users')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'is_admin'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->createTable('company_users');
    }

    public function down()
    {
        $this->forge->dropTable('company_users', true);
    }
}
