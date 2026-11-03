<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Categorías para clasificar el catálogo de productos/servicios.
 *
 * Son **por empresa** (a diferencia de las del Marketplace, que son globales
 * para que el catálogo sea comparable entre empresas): aquí cada empresa
 * clasifica lo suyo con su propio criterio.
 *
 * La relación con el producto es `SET NULL`: borrar una categoría no puede
 * llevarse por delante los productos clasificados con ella.
 */
class CreateQuoCategoriesTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_quo_categories')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
                'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'sort_order'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'name']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('cmp_quo_categories');
        }

        if ($this->db->tableExists('cmp_quo_products') && !$this->db->fieldExists('category_id', 'cmp_quo_products')) {
            $this->forge->addColumn('cmp_quo_products', [
                'category_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'company_id'],
            ]);
            $this->forge->addForeignKey('category_id', 'cmp_quo_categories', 'id', 'SET NULL', 'SET NULL');
            $this->forge->processIndexes('cmp_quo_products');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('category_id', 'cmp_quo_products')) {
            $this->forge->dropForeignKey('cmp_quo_products', 'cmp_quo_products_category_id_foreign');
            $this->forge->dropColumn('cmp_quo_products', 'category_id');
        }
        $this->forge->dropTable('cmp_quo_categories', true);
    }
}
