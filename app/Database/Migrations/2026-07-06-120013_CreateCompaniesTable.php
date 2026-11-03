<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompaniesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('companies')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'nit'           => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'slug'          => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'logo'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'website'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'address'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'brand_color'   => ['type' => 'VARCHAR', 'constraint' => 9, 'null' => true],
            'contact_email' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'contact_phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'whatsapp'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'plan_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'owner_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deleted_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('slug');
        $this->forge->addKey('plan_id');
        $this->forge->addKey('owner_id');
        $this->forge->createTable('companies');
    }

    public function down()
    {
        $this->forge->dropTable('companies', true);
    }
}
