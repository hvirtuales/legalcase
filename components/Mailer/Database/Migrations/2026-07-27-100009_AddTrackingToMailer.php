<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Estadísticas de apertura y clics: columnas de tracking por destinatario
 * (píxel de apertura + token) y tabla de clics por URL. Idempotente.
 */
class AddTrackingToMailer extends Migration
{
    public function up()
    {
        $add = [];
        if (!$this->db->fieldExists('opened_at', 'cmp_mailer_recipients')) {
            $add['opened_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'sent_at'];
        }
        if (!$this->db->fieldExists('open_count', 'cmp_mailer_recipients')) {
            $add['open_count'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0];
        }
        if (!$this->db->fieldExists('clicked_at', 'cmp_mailer_recipients')) {
            $add['clicked_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        if (!$this->db->fieldExists('click_count', 'cmp_mailer_recipients')) {
            $add['click_count'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0];
        }
        if (!$this->db->fieldExists('track_token', 'cmp_mailer_recipients')) {
            $add['track_token'] = ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true];
        }
        if ($add) {
            $this->forge->addColumn('cmp_mailer_recipients', $add);
        }

        if (!$this->db->tableExists('cmp_mailer_clicks')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'campaign_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'recipient_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'url'          => ['type' => 'VARCHAR', 'constraint' => 500],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('campaign_id');
            $this->forge->addKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('campaign_id', 'cmp_mailer_campaigns', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_mailer_clicks');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mailer_clicks', true);
        foreach (['opened_at', 'open_count', 'clicked_at', 'click_count', 'track_token'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_mailer_recipients')) {
                $this->forge->dropColumn('cmp_mailer_recipients', $col);
            }
        }
    }
}
