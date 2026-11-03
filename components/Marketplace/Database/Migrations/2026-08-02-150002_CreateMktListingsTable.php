<?php

namespace Components\Marketplace\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Publicaciones del marketplace: lo que una empresa OFRECE (producto, servicio) o
 * NECESITA (necesidad, oportunidad de negocio).
 *
 * `type` y `status` son VARCHAR + validación en el modelo (portabilidad §8, sin
 * ENUM). Pertenece a una empresa (`company_id`) y guarda quién la publicó
 * (`user_id`), pero se muestra en el catálogo común a toda la plataforma.
 */
class CreateMktListingsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_mkt_listings')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'category_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'type'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'product'], // product|service|need|opportunity
            'title'          => ['type' => 'VARCHAR', 'constraint' => 180],
            'summary'        => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'image'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'price_type'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'quote'], // quote|fixed|from
            'price_amount'   => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'price_currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],
            'city'           => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'published'], // draft|published|paused|closed
            'valid_until'    => ['type' => 'DATETIME', 'null' => true],
            'views'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'type']);
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->addKey('category_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'cmp_mkt_categories', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_mkt_listings');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_mkt_listings', true);
    }
}
