<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Plantillas de formulario.
 *
 * `company_id` NULL = plantilla de ARRANQUE, global a la plataforma (las cuatro
 * que porta `TemplateLibrary`). Con empresa = una que esa empresa guardó desde
 * uno de sus formularios.
 *
 * `company_id` NO lleva FK a propósito: la columna es nullable para las globales
 * y `companies` usa soft-delete, así que un ON DELETE CASCADE casi nunca
 * dispararía. Es el mismo criterio que `companies.parent_id` en el núcleo.
 *
 * `code` es único para poder sembrar de forma idempotente: volver a correr el
 * seeder actualiza en vez de duplicar.
 */
class CreateFrmTemplatesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_frm_templates')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => ''],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 180],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'category'    => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => ''],
            'schema_json' => ['type' => 'TEXT', 'null' => true],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at']);
        $this->forge->addKey('code');
        $this->forge->createTable('cmp_frm_templates');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_frm_templates', true);
    }
}
