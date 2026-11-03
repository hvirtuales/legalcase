<?php

namespace Components\Networking\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mensajes del hilo de una conexión. `request_id` apunta a la solicitud aceptada
 * (= la conexión); al eliminarla se van sus mensajes en cascada.
 */
class CreateNetMessagesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_net_messages')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'request_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sender_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'body'       => ['type' => 'TEXT'],
            'read_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['request_id', 'id']);
        $this->forge->addKey('sender_id');
        $this->forge->addForeignKey('request_id', 'cmp_net_requests', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sender_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_net_messages');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_net_messages', true);
    }
}
