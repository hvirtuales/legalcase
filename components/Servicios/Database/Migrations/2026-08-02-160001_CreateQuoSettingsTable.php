<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ajustes del documento por empresa: cómo se llama, cómo se numera y qué textos
 * fijos lleva. Los datos de la empresa emisora (razón social, NIT, logo, color)
 * NO se guardan aquí: salen de `companies`.
 *
 * `last_number`/`last_prefix` sostienen la numeración consecutiva; si el prefijo
 * cambia, la serie reinicia en `initial_number`.
 */
class CreateQuoSettingsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_quo_settings')) {
            return;
        }

        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'doc_type'              => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Cotización'],
            'prefix'                => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'COT'],
            'initial_number'        => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'last_number'           => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'last_prefix'           => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => ''],
            'default_currency'      => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],
            'default_validity_days' => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
            'default_retention'     => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'terms_html'            => ['type' => 'TEXT', 'null' => true],
            'cover_enabled'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'cover_title'           => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'cover_html'            => ['type' => 'TEXT', 'null' => true],
            'show_detail_default'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_quo_settings');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_quo_settings', true);
    }
}
