<?php

namespace Components\Paginas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Plantillas reutilizables: un árbol de bloques completo del que nace una página.
 *
 * `company_id` NULL = plantilla **de arranque**, común a toda la plataforma (las
 * siembra `Libraries\TemplateLibrary`). Con empresa = plantilla propia, guardada
 * desde una página existente. La lista de una empresa es la unión de las dos, y
 * solo las suyas se pueden borrar.
 */
class CreatePagTemplatesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_pag_templates')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // NULL = plantilla de arranque (global, no editable por la empresa).
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true], // solo las de arranque (idempotencia)
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'category'    => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'general'],
            'blocks'      => ['type' => 'TEXT', 'null' => true],
            'settings'    => ['type' => 'TEXT', 'null' => true],
            'sort_order'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'deleted_at']);
        $this->forge->addKey('code');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_pag_templates');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_pag_templates', true);
    }
}
