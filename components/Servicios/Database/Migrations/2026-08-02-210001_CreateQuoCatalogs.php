<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catálogo digital: una vitrina pública con productos escogidos, el valor
 * (escalón de precio) con el que se muestran y los vendedores que la comparten.
 *
 * Cada vendedor asignado tiene **su propia URL** (token aleatorio, no el id):
 * el cliente entra, arma su pedido y este **se guarda como una cotización** del
 * componente —no hay tabla de pedidos aparte— con ese vendedor asignado.
 */
class CreateQuoCatalogs extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_quo_catalogs')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'name'         => ['type' => 'VARCHAR', 'constraint' => 150],
                'headline'     => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'intro'        => ['type' => 'TEXT', 'null' => true],
                'price_tier'   => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true], // 1..3; null = condiciones base
                'show_prices'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'is_published' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'name']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_quo_catalogs');
        }

        if (!$this->db->tableExists('cmp_quo_catalog_products')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'catalog_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'product_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['catalog_id', 'product_id']);
            $this->forge->addForeignKey('catalog_id', 'cmp_quo_catalogs', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('product_id', 'cmp_quo_products', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_quo_catalog_products');
        }

        if (!$this->db->tableExists('cmp_quo_catalog_sellers')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'catalog_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'seller_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'token'      => ['type' => 'VARCHAR', 'constraint' => 64],
                'visits'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['catalog_id', 'seller_id']);
            $this->forge->addKey('token');
            $this->forge->addForeignKey('catalog_id', 'cmp_quo_catalogs', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('seller_id', 'cmp_quo_sellers', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_quo_catalog_sellers');
        }

        // De qué catálogo vino una cotización (las del cotizador quedan en null).
        if ($this->db->tableExists('cmp_quo_quotes') && !$this->db->fieldExists('catalog_id', 'cmp_quo_quotes')) {
            $this->forge->addColumn('cmp_quo_quotes', [
                'catalog_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'company_id'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('catalog_id', 'cmp_quo_quotes')) {
            $this->forge->dropColumn('cmp_quo_quotes', 'catalog_id');
        }
        $this->forge->dropTable('cmp_quo_catalog_sellers', true);
        $this->forge->dropTable('cmp_quo_catalog_products', true);
        $this->forge->dropTable('cmp_quo_catalogs', true);
    }
}
