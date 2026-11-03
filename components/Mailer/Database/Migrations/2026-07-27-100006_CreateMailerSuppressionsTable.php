<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lista de supresión por empresa (control de rebotes/quejas/bajas). Ningún
 * envío puede ir a un correo suprimido. reason: bounce|complaint|unsubscribe|manual.
 */
class CreateMailerSuppressionsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_mailer_suppressions')) {
            return;
        }
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 190],
            'reason'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'manual'],
            'notes'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('email');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_mailer_suppressions');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mailer_suppressions', true);
    }
}
