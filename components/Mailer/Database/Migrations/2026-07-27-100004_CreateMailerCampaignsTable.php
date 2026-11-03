<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Campañas de envío. status: draft|sending|paused|sent|failed. body_html es
 * una instantánea (snapshot) del cuerpo al momento de crear/editar la campaña.
 */
class CreateMailerCampaignsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_mailer_campaigns')) {
            return;
        }
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'list_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'template_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'subject'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'from_name'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'from_email'    => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'reply_to'      => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'body_html'     => ['type' => 'TEXT', 'null' => true],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'total'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'sent_count'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'failed_count'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'bounced_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'started_at'    => ['type' => 'DATETIME', 'null' => true],
            'finished_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('list_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('list_id', 'cmp_mailer_lists', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_mailer_campaigns');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mailer_campaigns', true);
    }
}
