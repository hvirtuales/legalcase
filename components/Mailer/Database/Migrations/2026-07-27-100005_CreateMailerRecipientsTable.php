<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Destinatarios de una campaña (cola de envío reanudable + log por contacto).
 * status: pending|sent|failed|bounced|complained|skipped.
 */
class CreateMailerRecipientsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_mailer_recipients')) {
            return;
        }
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'contact_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 190],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'error'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sent_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('campaign_id');
        $this->forge->addKey('company_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('campaign_id', 'cmp_mailer_campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_mailer_recipients');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mailer_recipients', true);
    }
}
