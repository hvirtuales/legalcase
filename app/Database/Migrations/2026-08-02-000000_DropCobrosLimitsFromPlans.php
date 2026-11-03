<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Elimina de `plans` las columnas del servicio de cartera (`cobros_enabled`,
 * `max_invoices`) que sembró 2026-07-07-000001_AddCobrosLimitsToPlans: el
 * componente que las leía ya no forma parte de la plataforma, así que quedaban
 * como columnas muertas en el formulario de planes.
 *
 * Idempotente (comprueba `fieldExists`) y reversible: `down()` las restaura con
 * sus valores por defecto originales. Portable: todo por Forge.
 */
class DropCobrosLimitsFromPlans extends Migration
{
    public function up()
    {
        foreach (['max_invoices', 'cobros_enabled'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }

    public function down()
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
}
