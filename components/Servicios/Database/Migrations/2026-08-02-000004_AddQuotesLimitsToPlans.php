<?php

namespace Components\Servicios\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vive en el componente: es SU bandera y SU cupo. `plans` es del núcleo, así
 * que la migración solo AÑADE columnas propias y comprueba que la tabla exista
 * (el núcleo la crea antes); nunca la redefine.
 *
 * Servicio y cupo del componente Cotizaciones en `plans`:
 *   - quotes_enabled: habilita/deshabilita el componente por plan (default 1).
 *   - max_quotes: cotizaciones por empresa (0 = ilimitado).
 *
 * Migración del NÚCLEO porque `plans` es tabla del núcleo (contrato §11).
 * Idempotente y portable.
 */
class AddQuotesLimitsToPlans extends Migration
{
    public function up()
    {
        $fields = [];
        if (!$this->db->fieldExists('quotes_enabled', 'plans')) {
            $fields['quotes_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_quotes', 'plans')) {
            $fields['max_quotes'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0]; // 0 = ilimitado
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down()
    {
        foreach (['max_quotes', 'quotes_enabled'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
