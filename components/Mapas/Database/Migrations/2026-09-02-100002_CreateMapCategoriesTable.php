<?php

namespace Components\Mapas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Categorías de punto: «Moda», «Comida», «Servicios»…
 *
 * Son **por empresa** y no por mapa: quien administra tres centros comerciales
 * usa las mismas categorías en los tres, y tenerlas por mapa le obligaría a
 * volver a crearlas —y a elegir otra vez los colores— en cada uno. Mismo
 * criterio que las categorías de Servicios.
 *
 * El `color` es el del pin en el plano, así que es parte del dato y no un
 * detalle de presentación: se guarda como hex y el renderizador lo valida.
 */
class CreateMapCategoriesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_map_categories')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'color'      => ['type' => 'VARCHAR', 'constraint' => 9, 'default' => '#2563eb'],
            'icon'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 5, 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_map_categories');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_map_categories', true);
    }
}
