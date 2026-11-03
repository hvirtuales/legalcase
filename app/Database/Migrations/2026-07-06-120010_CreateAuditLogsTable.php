<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('audit_logs')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'event'          => ['type' => 'VARCHAR', 'constraint' => 50],
            'auditable_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'auditable_id'   => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'old_values'     => ['type' => 'TEXT', 'null' => true], // JSON como texto (portable)
            'new_values'     => ['type' => 'TEXT', 'null' => true], // JSON como texto (portable)
            'url'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'     => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey(['auditable_type', 'auditable_id']);
        $this->forge->addKey('event');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('audit_logs');
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
