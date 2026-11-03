<?php

namespace Components\Mapas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Los niveles del mapa: «Sótano», «Piso 1», «Terraza».
 *
 * Cada nivel es un plano —una imagen— y los puntos cuelgan de él. Se guardan
 * **las medidas de la imagen** (`img_width`/`img_height`) porque el visor las
 * necesita antes de que la imagen termine de cargar: con ellas puede reservar
 * el hueco, calcular el encuadre inicial y colocar los pines desde el primer
 * fotograma, en vez de dar un salto cuando la imagen aparece.
 *
 * `sort_order` y no el id para ordenar: los niveles se reordenan (llega un
 * sótano nuevo por debajo del que ya estaba) y el orden de creación no dice
 * nada sobre en qué piso está cada uno.
 */
class CreateMapLevelsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_map_levels')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'map_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'image'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'img_width'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'img_height' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'sort_order' => ['type' => 'INT', 'constraint' => 5, 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey(['map_id', 'sort_order']);
        $this->forge->addForeignKey('map_id', 'cmp_map_maps', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_map_levels');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_map_levels', true);
    }
}
