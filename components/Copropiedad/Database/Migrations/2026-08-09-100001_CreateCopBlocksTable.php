<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Torres, bloques, etapas y manzanas: la estructura física del conjunto.
 *
 * Es una tabla aparte y no un campo de texto en el inmueble porque hay que poder
 * contar por torre, filtrar por torre y renombrar una torre sin reescribir sus
 * doscientos apartamentos.
 *
 * `kind` es VARCHAR + validación en el modelo (§8, sin ENUM): un conjunto habla de
 * «torres», un condominio de «manzanas» y una urbanización por etapas de «etapas»,
 * y la palabra la elige quien administra.
 */
class CreateCopBlocksTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_cop_blocks')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'kind'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'torre'], // torre|bloque|etapa|manzana|interior
            'floors'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // El listado filtra por empresa, respeta el soft-delete y ordena por
        // `sort_order`: el índice cubre las tres cosas en ese orden.
        $this->forge->addKey(['company_id', 'deleted_at', 'sort_order']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_cop_blocks');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_cop_blocks', true);
    }
}
