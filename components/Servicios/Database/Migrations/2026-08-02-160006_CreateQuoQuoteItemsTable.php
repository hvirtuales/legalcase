<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Líneas de la cotización. Copian del producto lo necesario (código, nombre,
 * descripción, enlace) para que el documento no cambie si luego se edita el
 * catálogo; `product_id` queda solo como referencia (`SET NULL`).
 *
 * Los importes por línea se persisten ya calculados por `QuoteCalculator`.
 */
class CreateQuoQuoteItemsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_quo_quote_items')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'quote_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'product_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => ''],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 200],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'details_url'    => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'unit_price'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'quantity'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 1],
            'discount_type'  => ['type' => 'VARCHAR', 'constraint' => 1, 'default' => '%'],
            'discount_value' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_percent'    => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'line_subtotal'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'line_discount'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'line_base'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'line_tax'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'line_total'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'sort_order'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['quote_id', 'sort_order']);
        $this->forge->addForeignKey('quote_id', 'cmp_quo_quotes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'cmp_quo_products', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cmp_quo_quote_items');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_quo_quote_items', true);
    }
}
