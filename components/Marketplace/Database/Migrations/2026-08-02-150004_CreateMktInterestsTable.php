<?php

namespace Components\Marketplace\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El cruce entre oferta y demanda: cuando una empresa manifiesta interés en la
 * publicación de otra queda registrado aquí, y el publicador lo gestiona desde su
 * bandeja «Interesados».
 *
 * `company_id` es la empresa INTERESADA (la del publicador sale de la publicación).
 */
class CreateMktInterestsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_mkt_interests')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'listing_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true], // empresa interesada
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'message'    => ['type' => 'TEXT', 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'new'], // new|contacted|closed
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['listing_id', 'status']);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('listing_id', 'cmp_mkt_listings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_mkt_interests');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mkt_interests', true);
    }
}
