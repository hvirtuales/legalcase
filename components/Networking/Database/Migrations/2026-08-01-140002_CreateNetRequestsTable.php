<?php

namespace Components\Networking\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Solicitudes de conexión. Una solicitud ACEPTADA *es* la conexión (no hay tabla
 * aparte: evita que ambas se desincronicen). `message` guarda la observación del
 * porqué del contacto. `status` es VARCHAR + validación en el modelo (portabilidad).
 */
class CreateNetRequestsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_net_requests')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'requester_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'addressee_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'message'      => ['type' => 'TEXT', 'null' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], // pending|accepted|rejected|cancelled
            'responded_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['requester_id', 'addressee_id']);
        $this->forge->addKey(['addressee_id', 'status']);
        $this->forge->addKey(['requester_id', 'status']);
        $this->forge->addForeignKey('requester_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('addressee_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_net_requests');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_net_requests', true);
    }
}
