<?php

namespace Components\Marketplace\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Etiquetas libres, normalizadas por `slug` para que «Diseño Web», «diseño web»
 * y «DISEÑO WEB» sean la misma etiqueta y la búsqueda las encuentre juntas.
 * Van en tabla propia (no en un VARCHAR de la publicación) para poder filtrar y
 * contar por etiqueta sin recorrer todo el catálogo.
 */
class CreateMktTagsTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_mkt_tags')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 60],
                'slug'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('slug');
            $this->forge->createTable('cmp_mkt_tags');
        }

        if (!$this->db->tableExists('cmp_mkt_listing_tags')) {
            $this->forge->addField([
                'listing_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'tag_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            ]);
            $this->forge->addKey(['listing_id', 'tag_id'], true); // PK compuesta: sin duplicados
            $this->forge->addKey('tag_id');
            $this->forge->addForeignKey('listing_id', 'cmp_mkt_listings', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('tag_id', 'cmp_mkt_tags', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_mkt_listing_tags');
        }
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mkt_listing_tags', true);
        $this->forge->dropTable('cmp_mkt_tags', true);
    }
}
