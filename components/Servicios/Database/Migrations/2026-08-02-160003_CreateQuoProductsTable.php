<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catálogo de productos/servicios con su precio y condiciones por defecto, que se
 * copian a la línea de la cotización al añadirlos (y ahí se pueden ajustar).
 * `discount_type` es `%` o `$` (VARCHAR + validación en el modelo, §8).
 */
class CreateQuoProductsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_quo_products')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'code'             => ['type' => 'VARCHAR', 'constraint' => 60],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 200],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'unit_price'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'default_quantity' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 1],
            'discount_type'    => ['type' => 'VARCHAR', 'constraint' => 1, 'default' => '%'],
            'discount_value'   => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_percent'      => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
            'details_url'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addKey(['company_id', 'name']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_quo_products');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_quo_products', true);
    }
}
