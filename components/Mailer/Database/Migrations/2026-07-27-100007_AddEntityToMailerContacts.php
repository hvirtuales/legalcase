<?php

namespace Components\Mailer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Campo opcional «entidad» (organización) en los contactos y su snapshot en los
 * destinatarios de campaña (para personalizar {{entidad}} en el envío). Idempotente.
 */
class AddEntityToMailerContacts extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('entity', 'cmp_mailer_contacts')) {
            $this->forge->addColumn('cmp_mailer_contacts', [
                'entity' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'name'],
            ]);
        }
        if (!$this->db->fieldExists('entity', 'cmp_mailer_recipients')) {
            $this->forge->addColumn('cmp_mailer_recipients', [
                'entity' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'name'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('entity', 'cmp_mailer_contacts')) {
            $this->forge->dropColumn('cmp_mailer_contacts', 'entity');
        }
        if ($this->db->fieldExists('entity', 'cmp_mailer_recipients')) {
            $this->forge->dropColumn('cmp_mailer_recipients', 'entity');
        }
    }
}
