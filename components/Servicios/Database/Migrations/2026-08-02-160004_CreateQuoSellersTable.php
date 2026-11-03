<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vendedores que firman las cotizaciones. Maestro propio (no `users`): puede
 * haber comisionistas o externos sin cuenta en la plataforma. `card_url` es la
 * tarjeta de presentación digital que se imprime en el PDF.
 */
class CreateQuoSellersTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_quo_sellers')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'activo'], // activo|inactivo
            'card_url'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cmp_quo_sellers');
    }

    public function down()
    {
        $this->forge->dropTable('cmp_quo_sellers', true);
    }
}
