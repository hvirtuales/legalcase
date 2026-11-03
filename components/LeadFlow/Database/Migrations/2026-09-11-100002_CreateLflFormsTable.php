<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Formularios dinámicos de captación y precalificación.
 *
 * **Un formulario ES un árbol JSON** (`schema`, TEXT + json_encode — sin JSON
 * nativo, §8): el servidor lo reconstruye contra `FieldRegistry` al guardar y lo
 * pinta en cada visita. El `token` público es aleatorio (32 bytes), nunca el id:
 * con el id se podrían recorrer los formularios de todas las firmas.
 */
class CreateLflFormsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_lfl_forms')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'token'            => ['type' => 'VARCHAR', 'constraint' => 64],
            'service_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'campaign_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'is_public'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'draft'], // draft|published|closed
            'schema'           => ['type' => 'TEXT', 'null' => true],
            'ask_consultation' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'success_message'  => ['type' => 'TEXT', 'null' => true],
            'submissions'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'views'            => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_by'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('service_id', 'cmp_lfl_services', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('campaign_id', 'cmp_lfl_campaigns', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_lfl_forms');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_lfl_forms', true);
    }
}
