<?php

namespace Components\Marketplace\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Categorías base del marketplace. Son **globales** (compartidas por todas las
 * empresas) para que el catálogo sea comparable y la búsqueda funcione entre
 * empresas distintas; el juego inicial lo siembra CatalogService.
 * Portable e idempotente.
 */
class CreateMktCategoriesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_mkt_categories')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 140],
            'icon'       => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'icon-box-seam'],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->createTable('cmp_mkt_categories');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mkt_categories', true);
    }
}
