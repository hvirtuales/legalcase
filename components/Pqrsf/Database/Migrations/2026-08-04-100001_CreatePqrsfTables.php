<?php

namespace Components\Pqrsf\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Esquema del componente PQRSF: puntos con QR, solicitudes, hilo colaborativo,
 * adjuntos y configuración por empresa.
 *
 * Portable (§8): sin ENUM ni JSON nativo, todo por Forge, `up()` idempotente.
 */
class CreatePqrsfTables extends Migration
{
    public function up()
    {
        // ---- Puntos de publicación (un QR por área) -------------------------
        if (!$this->db->tableExists('cmp_pqr_points')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
                'area'       => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
                'notes'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'token'      => ['type' => 'VARCHAR', 'constraint' => 64],
                'visits'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('token');
            $this->forge->addKey(['company_id', 'name']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_pqr_points');
        }

        // ---- Solicitudes -----------------------------------------------------
        if (!$this->db->tableExists('cmp_pqr_requests')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'point_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'number'         => ['type' => 'VARCHAR', 'constraint' => 30],
                'ticket_token'   => ['type' => 'VARCHAR', 'constraint' => 64],
                'type'           => ['type' => 'VARCHAR', 'constraint' => 20],
                'subject'        => ['type' => 'VARCHAR', 'constraint' => 200],
                'message'        => ['type' => 'TEXT'],
                'priority'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'media'],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'recibida'],
                'assignee_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'due_at'         => ['type' => 'DATETIME', 'null' => true],

                // Identificación: vacía cuando la solicitud es anónima
                'is_anonymous'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'citizen_name'   => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
                'citizen_doc'    => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
                'citizen_email'  => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
                'citizen_phone'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
                'citizen_city'   => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],

                // Consentimientos: la fecha, la IP y la versión son lo que los hace probables
                'accept_terms'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'consent_data'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'consent_comms'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'consent_at'     => ['type' => 'DATETIME', 'null' => true],
                'consent_ip'     => ['type' => 'VARCHAR', 'constraint' => 45, 'default' => ''],
                'policy_version' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],

                // IA y respuesta
                'ai_summary'     => ['type' => 'TEXT', 'null' => true],
                'ai_draft'       => ['type' => 'TEXT', 'null' => true],
                'answer'         => ['type' => 'TEXT', 'null' => true],
                'answered_at'    => ['type' => 'DATETIME', 'null' => true],
                'answered_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'closed_at'      => ['type' => 'DATETIME', 'null' => true],

                'created_at'     => ['type' => 'DATETIME', 'null' => true],
                'updated_at'     => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('ticket_token');
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey(['company_id', 'number']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('point_id', 'cmp_pqr_points', 'id', 'SET NULL', 'SET NULL');
            $this->forge->addForeignKey('assignee_id', 'users', 'id', 'SET NULL', 'SET NULL');
            $this->forge->createTable('cmp_pqr_requests');
        }

        // ---- Hilo colaborativo ------------------------------------------------
        if (!$this->db->tableExists('cmp_pqr_events')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'request_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'type'       => ['type' => 'VARCHAR', 'constraint' => 20],
                'body'       => ['type' => 'TEXT', 'null' => true],
                'meta'       => ['type' => 'TEXT', 'null' => true],
                'is_private' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('request_id');
            $this->forge->addForeignKey('request_id', 'cmp_pqr_requests', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'SET NULL');
            $this->forge->createTable('cmp_pqr_events');
        }

        // ---- Adjuntos (polimórficos, privados) ----------------------------------
        if (!$this->db->tableExists('cmp_pqr_attachments')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'owner_type' => ['type' => 'VARCHAR', 'constraint' => 20],
                'owner_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 190],
                'path'       => ['type' => 'VARCHAR', 'constraint' => 255],
                'mime'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
                'size'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['owner_type', 'owner_id']);
            $this->forge->createTable('cmp_pqr_attachments');
        }

        // ---- Configuración por empresa ---------------------------------------------
        if (!$this->db->tableExists('cmp_pqr_settings')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'prefix'            => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'PQR'],
                'last_number'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'days_peticion'     => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
                'days_queja'        => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
                'days_reclamo'      => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
                'days_sugerencia'   => ['type' => 'INT', 'constraint' => 11, 'default' => 30],
                'days_felicitacion' => ['type' => 'INT', 'constraint' => 11, 'default' => 30],
                'allow_anonymous'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'captcha_enabled'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'privacy_url'       => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'privacy_text'      => ['type' => 'TEXT', 'null' => true],
                'terms_url'         => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'terms_text'        => ['type' => 'TEXT', 'null' => true],
                'policy_version'    => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'v1'],
                'ai_enabled'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'ai_tone'           => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'formal'],
                'ack_template'      => ['type' => 'TEXT', 'null' => true],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_pqr_settings');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_pqr_attachments', true);
        $this->forge->dropTable('cmp_pqr_events', true);
        $this->forge->dropTable('cmp_pqr_requests', true);
        $this->forge->dropTable('cmp_pqr_points', true);
        $this->forge->dropTable('cmp_pqr_settings', true);
    }
}
