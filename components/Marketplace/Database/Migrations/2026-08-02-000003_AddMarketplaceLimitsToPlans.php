<?php

namespace Components\Marketplace\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vive en el componente: es SU bandera y SU cupo. `plans` es del núcleo, así
 * que la migración solo AÑADE columnas propias y comprueba que la tabla exista
 * (el núcleo la crea antes); nunca la redefine.
 *
 * Servicio y cupo del componente Marketplace en `plans`:
 *   - marketplace_enabled: habilita/deshabilita el componente por plan (default 1).
 *   - max_listings: publicaciones activas por empresa (0 = ilimitado).
 *
 * Migración del NÚCLEO porque `plans` es tabla del núcleo (contrato §11).
 * Idempotente y portable.
 */
class AddMarketplaceLimitsToPlans extends Migration
{
    public function up()
    {
        $fields = [];
        if (!$this->db->fieldExists('marketplace_enabled', 'plans')) {
            $fields['marketplace_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_listings', 'plans')) {
            $fields['max_listings'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0]; // 0 = ilimitado
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down()
    {
        foreach (['max_listings', 'marketplace_enabled'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
