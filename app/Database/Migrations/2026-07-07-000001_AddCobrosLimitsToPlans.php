<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Planes: habilitar el servicio de Cobros y fijar límite de facturas.
 * Idempotente. cobros_enabled por defecto 1 (los planes existentes lo conservan).
 */
class AddCobrosLimitsToPlans extends Migration
{
    public function up()
    {
        $fields = [];
        if (!$this->db->fieldExists('cobros_enabled', 'plans')) {
            $fields['cobros_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_invoices', 'plans')) {
            $fields['max_invoices'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0]; // 0 = ilimitado
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down()
    {
        foreach (['max_invoices', 'cobros_enabled'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
