<?php

namespace Components\Networking\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El perfil de red deja de pedir a mano datos que la plataforma YA conoce.
 *
 * Fuera: `headline`, `company_name` y `city` — la empresa está relacionada
 * (`companies`) y reescribirla a mano es redundante y se desincroniza.
 * Dentro: `company_id` (qué empresa muestra el usuario, por si administra varias)
 * y `branch` (sucursal / sede, que sí es información de la persona).
 *
 * Lo que se enseña de la persona sale de `users` y lo de la empresa de
 * `companies`; el usuario solo edita cargo, sucursal e intereses.
 * Idempotente y portable.
 */
class ReshapeProfilesAroundCompany extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cmp_net_profiles')) {
            return;
        }

        $add = [];
        if (!$this->db->fieldExists('company_id', 'cmp_net_profiles')) {
            $add['company_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
        }
        if (!$this->db->fieldExists('branch', 'cmp_net_profiles')) {
            $add['branch'] = ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''];
        }
        if ($add) {
            $this->forge->addColumn('cmp_net_profiles', $add);
        }

        foreach (['headline', 'company_name', 'city'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_net_profiles')) {
                $this->forge->dropColumn('cmp_net_profiles', $col);
            }
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('cmp_net_profiles')) {
            return;
        }

        $add = [];
        if (!$this->db->fieldExists('headline', 'cmp_net_profiles')) {
            $add['headline'] = ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''];
        }
        if (!$this->db->fieldExists('company_name', 'cmp_net_profiles')) {
            $add['company_name'] = ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''];
        }
        if (!$this->db->fieldExists('city', 'cmp_net_profiles')) {
            $add['city'] = ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''];
        }
        if ($add) {
            $this->forge->addColumn('cmp_net_profiles', $add);
        }

        foreach (['branch', 'company_id'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_net_profiles')) {
                $this->forge->dropColumn('cmp_net_profiles', $col);
            }
        }
    }
}
