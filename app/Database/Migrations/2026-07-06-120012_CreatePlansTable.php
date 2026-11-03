<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlansTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('plans')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 80],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'max_companies'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'service_days'   => ['type' => 'INT', 'constraint' => 11, 'default' => 30],
            'price_amount'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
            'price_currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'],
            'features'       => ['type' => 'TEXT', 'null' => true],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deleted_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('plans');
    }

    public function down()
    {
        $this->forge->dropTable('plans', true);
    }
}
