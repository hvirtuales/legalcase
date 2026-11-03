<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Foto del producto y **listas de precios**.
 *
 * Una empresa cotiza el mismo producto a distinto precio según a quién le venda
 * (base, minorista, mayorista…). Se modela con:
 *
 *   - `cmp_quo_price_types`  — las listas, por empresa.
 *   - `cmp_quo_product_prices` — el precio de un producto EN una lista (tabla
 *     puente; sin fila, se usa el `unit_price` base del producto).
 *   - `cmp_quo_clients.price_type_id` — la lista que le corresponde al cliente,
 *     que es la etiqueta que se ve en su ficha y la que aplica el cotizador.
 */
class AddPhotoAndPriceTypes extends Migration
{
    public function up()
    {
        // 1. Foto del producto
        if ($this->db->tableExists('cmp_quo_products') && !$this->db->fieldExists('photo', 'cmp_quo_products')) {
            $this->forge->addColumn('cmp_quo_products', [
                'photo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'description'],
            ]);
        }

        // 2. Listas de precios
        if (!$this->db->tableExists('cmp_quo_price_types')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
                'notes'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'name']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_quo_price_types');
        }

        // 3. Precio de un producto en una lista
        if (!$this->db->tableExists('cmp_quo_product_prices')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'product_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'price_type_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'unit_price'    => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['product_id', 'price_type_id']);
            $this->forge->addForeignKey('product_id', 'cmp_quo_products', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('price_type_id', 'cmp_quo_price_types', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_quo_product_prices');
        }

        // 4. Lista de precios del cliente (su etiqueta)
        if ($this->db->tableExists('cmp_quo_clients') && !$this->db->fieldExists('price_type_id', 'cmp_quo_clients')) {
            $this->forge->addColumn('cmp_quo_clients', [
                'price_type_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'company_id'],
            ]);
            $this->forge->addForeignKey('price_type_id', 'cmp_quo_price_types', 'id', 'SET NULL', 'SET NULL');
            $this->forge->processIndexes('cmp_quo_clients');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('price_type_id', 'cmp_quo_clients')) {
            $this->forge->dropForeignKey('cmp_quo_clients', 'cmp_quo_clients_price_type_id_foreign');
            $this->forge->dropColumn('cmp_quo_clients', 'price_type_id');
        }
        $this->forge->dropTable('cmp_quo_product_prices', true);
        $this->forge->dropTable('cmp_quo_price_types', true);

        if ($this->db->fieldExists('photo', 'cmp_quo_products')) {
            $this->forge->dropColumn('cmp_quo_products', 'photo');
        }
    }
}
