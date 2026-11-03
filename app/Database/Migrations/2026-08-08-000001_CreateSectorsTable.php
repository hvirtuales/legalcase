<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sectores: la clasificación de las empresas.
 *
 * Es **catálogo de plataforma**, no de empresa ni de grupo: si cada tenant
 * definiera los suyos, dos cámaras de comercio llamarían distinto a lo mismo y
 * ninguna comparación entre grupos sería posible. Es la misma decisión que tomó
 * el Marketplace con sus categorías base (`cmp_mkt_categories`, globales «para
 * poder comparar») y la contraria a la de Servicios, cuyas categorías sí son
 * por empresa porque solo ordenan **su** catálogo.
 *
 * Portable a los tres motores: sin `ENUM` ni `JSON` nativo, todo por Forge.
 */
class CreateSectorsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('sectors')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // Icono de la plataforma (`icon-*`) y color para las fichas y los
            // marcadores del mapa del Directorio.
            'icon'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'color'       => ['type' => 'VARCHAR', 'constraint' => 9, 'null' => true],
            'sort_order'  => ['type' => 'INT', 'constraint' => 11, 'null' => false, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // El slug identifica al sector en una URL pública; único entre los vivos
        // lo garantiza el modelo, no un índice: con soft-delete un UNIQUE real
        // impediría reutilizar el nombre de un sector borrado.
        $this->forge->addKey('slug');
        $this->forge->createTable('sectors', true);
    }

    public function down()
    {
        $this->forge->dropTable('sectors', true);
    }
}
