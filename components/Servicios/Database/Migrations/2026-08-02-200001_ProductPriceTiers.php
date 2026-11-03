<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sustituye las listas de precios con nombre por **tres valores por producto**.
 *
 * Cada producto puede definir hasta 3 «valores» y cada uno lleva su propio
 * precio, cantidad, tipo de descuento, descuento e impuesto —no solo el precio—,
 * y el cliente indica **cuál de los tres** se le aplica (`price_tier`, 1..3;
 * vacío = las condiciones base del producto).
 *
 * Reemplaza a `2026-08-02-190001`, que modelaba lo mismo con listas por empresa
 * (`cmp_quo_price_types`): con tres valores fijos sobra el maestro y el cliente
 * elige un número, no una lista.
 */
class ProductPriceTiers extends Migration
{
    public function up()
    {
        // 1. El precio por lista deja paso al valor por escalón
        $this->forge->dropTable('cmp_quo_product_prices', true);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tier'           => ['type' => 'TINYINT', 'constraint' => 1],       // 1, 2 o 3
            'label'          => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => ''],
            'unit_price'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'quantity'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 1],
            'discount_type'  => ['type' => 'VARCHAR', 'constraint' => 1, 'default' => '%'],
            'discount_value' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_percent'    => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['product_id', 'tier']);
        $this->forge->addForeignKey('product_id', 'cmp_quo_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_quo_product_prices');

        // 2. El cliente pasa de «qué lista» a «qué valor»
        if ($this->db->fieldExists('price_type_id', 'cmp_quo_clients')) {
            $this->forge->dropForeignKey('cmp_quo_clients', 'cmp_quo_clients_price_type_id_foreign');
            $this->forge->dropColumn('cmp_quo_clients', 'price_type_id');
        }
        if (!$this->db->fieldExists('price_tier', 'cmp_quo_clients')) {
            $this->forge->addColumn('cmp_quo_clients', [
                'price_tier' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true, 'after' => 'company_id'],
            ]);
        }

        // 3. Fuera el maestro de listas
        $this->forge->dropTable('cmp_quo_price_types', true);
    }

    public function down()
    {
        // Vuelta atrás: se recrean las listas vacías y el precio simple por lista.
        if ($this->db->fieldExists('price_tier', 'cmp_quo_clients')) {
            $this->forge->dropColumn('cmp_quo_clients', 'price_tier');
        }
        $this->forge->dropTable('cmp_quo_product_prices', true);
    }
}
