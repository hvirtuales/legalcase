<?php

namespace Components\Mapas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Los puntos de interés, sobre un nivel.
 *
 * **`x` e `y` van en PORCENTAJE del plano (0–100), no en píxeles.** Es la
 * decisión que sostiene todo lo demás:
 *
 *  - el mismo mapa se ve en un móvil, en un tótem y con cinco niveles de zoom,
 *    y un porcentaje vale igual en los tres;
 *  - el día que se reemplace el plano por uno de más resolución —de 3000 a 6000
 *    px— unos píxeles absolutos dejarían **todos** los pines descolocados, y
 *    nadie se acordaría de por qué.
 *
 * `DECIMAL(7,4)` da cuatro decimales: sobre un plano de 6000 px eso es una
 * precisión de 0,0006 px, muy por debajo de lo que nadie puede señalar con el
 * dedo. Con FLOAT habría error de redondeo al ir y volver de la base de datos.
 *
 * `category_id` es `SET NULL`: borrar una categoría **no borra los puntos**,
 * los deja sin clasificar. Un punto es información del sitio; la categoría solo
 * dice de qué color se pinta.
 */
class CreateMapPointsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_map_points')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'map_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'level_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'category_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'image'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'url'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'x'           => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'y'           => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey(['level_id', 'is_active']);
        $this->forge->addForeignKey('level_id', 'cmp_map_levels', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'cmp_map_categories', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_map_points');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_map_points', true);
    }
}
