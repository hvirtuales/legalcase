<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enlace público de la cotización: el cliente la abre sin tener cuenta y puede
 * aprobarla, rechazarla o pedir ajustes.
 *
 *  - `public_token`: identificador aleatorio de la URL (no adivinable). Único.
 *  - `public_password`: HASH de la contraseña opcional (nunca la contraseña).
 *  - `public_enabled`: permite cortar el acceso sin perder el token ni el histórico.
 *  - `client_response` / `client_responded_at`: última respuesta del cliente, para
 *    verla de un vistazo en el listado (el detalle completo va en cmp_quo_responses).
 */
class AddPublicLinkToQuotes extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_quo_quotes')) {
            $fields = [];
            if (!$this->db->fieldExists('public_token', 'cmp_quo_quotes')) {
                $fields['public_token'] = ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true];
            }
            if (!$this->db->fieldExists('public_password', 'cmp_quo_quotes')) {
                $fields['public_password'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
            }
            if (!$this->db->fieldExists('public_enabled', 'cmp_quo_quotes')) {
                $fields['public_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0];
            }
            if (!$this->db->fieldExists('client_response', 'cmp_quo_quotes')) {
                $fields['client_response'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true];
            }
            if (!$this->db->fieldExists('client_responded_at', 'cmp_quo_quotes')) {
                $fields['client_responded_at'] = ['type' => 'DATETIME', 'null' => true];
            }
            if ($fields) {
                $this->forge->addColumn('cmp_quo_quotes', $fields);
                $this->db->query('CREATE UNIQUE INDEX idx_quo_public_token ON cmp_quo_quotes (public_token)');
            }
        }

        if (!$this->db->tableExists('cmp_quo_responses')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'quote_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'action'     => ['type' => 'VARCHAR', 'constraint' => 20], // approved|rejected|changes|viewed
                'message'    => ['type' => 'TEXT', 'null' => true],
                'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'default' => ''],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['quote_id', 'id']);
            $this->forge->addForeignKey('quote_id', 'cmp_quo_quotes', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_quo_responses');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_quo_responses', true);

        if ($this->db->tableExists('cmp_quo_quotes')) {
            foreach (['client_responded_at', 'client_response', 'public_enabled', 'public_password', 'public_token'] as $col) {
                if ($this->db->fieldExists($col, 'cmp_quo_quotes')) {
                    $this->forge->dropColumn('cmp_quo_quotes', $col);
                }
            }
        }
    }
}
