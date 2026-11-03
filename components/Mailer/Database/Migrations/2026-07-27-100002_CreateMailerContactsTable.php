<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contactos de una lista. status: subscribed|unsubscribed|bounced|complained.
 * unsubscribe_token: enlace público de baja. meta: TEXT (json_encode).
 */
class CreateMailerContactsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_mailer_contacts')) {
            return;
        }
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'list_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 190],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'subscribed'],
            'meta'              => ['type' => 'TEXT', 'null' => true],
            'unsubscribe_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'subscribed_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('list_id');
        $this->forge->addKey('email');
        $this->forge->addKey('unsubscribe_token');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('list_id', 'cmp_mailer_lists', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_mailer_contacts');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mailer_contacts', true);
    }
}
