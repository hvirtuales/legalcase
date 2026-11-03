<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Configuración del componente, una fila por empresa: a dónde avisar, si el
 * captcha está activo, el texto de la política de datos que firma quien
 * responde y su versión (lo que se sella en cada respuesta).
 */
class CreateFrmSettingsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_frm_settings')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'notify_email'    => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'captcha_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'require_consent' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'policy_text'     => ['type' => 'TEXT', 'null' => true],
            'policy_version'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '1.0'],
            'retention_days'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_frm_settings');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_frm_settings', true);
    }
}
