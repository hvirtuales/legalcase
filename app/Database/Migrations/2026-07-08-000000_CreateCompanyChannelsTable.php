<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Canales de comunicación por empresa (SMTP / WhatsApp / SMS) — a NIVEL DE
 * NÚCLEO (los usan todos los componentes, no solo Cobros). Portable; los
 * secretos los cifra el modelo. Migra desde la tabla anterior del componente
 * (`cmp_cobros_channels`) si existe y luego la elimina.
 */
class CreateCompanyChannelsTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('company_channels')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'smtp_host'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'smtp_port'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'smtp_user'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'smtp_pass'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'smtp_sender_name'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'smtp_sender_email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'smtp_security'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'starttls'],
                'wa_provider'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'meta'],
                'wa_endpoint'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'wa_phone_id'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'wa_token'          => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'sms_provider'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'twilio'],
                'sms_account_sid'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'sms_auth_token'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'sms_sender_id'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('company_channels');
        }

        // Migración desde la tabla anterior del componente Cobros (si existe)
        if ($this->db->tableExists('cmp_cobros_channels')) {
            $cols = 'company_id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_sender_name, smtp_sender_email, smtp_security, wa_provider, wa_endpoint, wa_phone_id, wa_token, sms_provider, sms_account_sid, sms_auth_token, sms_sender_id, created_at, updated_at';
            $this->db->query("INSERT INTO company_channels ({$cols}) SELECT {$cols} FROM cmp_cobros_channels");
            $this->forge->dropTable('cmp_cobros_channels', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('company_channels', true);
    }
}
