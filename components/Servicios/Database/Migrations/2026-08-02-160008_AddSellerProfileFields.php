<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perfil del vendedor: cargo, foto e imagen de firma.
 *
 * Las tres se **congelan también en la cotización** (`seller_*`), igual que el
 * resto del snapshot: un documento ya emitido no debe cambiar porque después se
 * edite el maestro de vendedores.
 */
class AddSellerProfileFields extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cmp_quo_sellers')) {
            $fields = [];
            if (!$this->db->fieldExists('position', 'cmp_quo_sellers')) {
                $fields['position'] = ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''];
            }
            if (!$this->db->fieldExists('photo', 'cmp_quo_sellers')) {
                $fields['photo'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
            }
            if (!$this->db->fieldExists('signature', 'cmp_quo_sellers')) {
                $fields['signature'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
            }
            if ($fields) {
                $this->forge->addColumn('cmp_quo_sellers', $fields);
            }
        }

        if ($this->db->tableExists('cmp_quo_quotes')) {
            $fields = [];
            if (!$this->db->fieldExists('seller_position', 'cmp_quo_quotes')) {
                $fields['seller_position'] = ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''];
            }
            if (!$this->db->fieldExists('seller_photo', 'cmp_quo_quotes')) {
                $fields['seller_photo'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
            }
            if (!$this->db->fieldExists('seller_signature', 'cmp_quo_quotes')) {
                $fields['seller_signature'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
            }
            if ($fields) {
                $this->forge->addColumn('cmp_quo_quotes', $fields);
            }
        }
    }

    public function down()
    {
        foreach (['signature', 'photo', 'position'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_quo_sellers')) {
                $this->forge->dropColumn('cmp_quo_sellers', $col);
            }
        }
        foreach (['seller_signature', 'seller_photo', 'seller_position'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_quo_quotes')) {
                $this->forge->dropColumn('cmp_quo_quotes', $col);
            }
        }
    }
}
