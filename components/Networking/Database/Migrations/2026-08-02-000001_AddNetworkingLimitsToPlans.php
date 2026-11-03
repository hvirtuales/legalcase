<?php

namespace Components\Networking\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vive en el componente: es SU bandera y SU cupo. `plans` es del núcleo, así
 * que la migración solo AÑADE columnas propias y comprueba que la tabla exista
 * (el núcleo la crea antes); nunca la redefine.
 *
 * Añade a `plans` el servicio y el cupo del componente Networking:
 *   - networking_enabled: bandera que habilita/deshabilita el componente por plan
 *     (default 1; los planes existentes lo conservan habilitado).
 *   - max_connections: cupo de conexiones aceptadas por usuario (0 = ilimitado).
 *
 * Migración del NÚCLEO porque `plans` es tabla del núcleo (contrato §11: reservado
 * a componentes de primera parte). Idempotente y portable.
 */
class AddNetworkingLimitsToPlans extends Migration
{
    public function up()
    {
        $fields = [];
        if (!$this->db->fieldExists('networking_enabled', 'plans')) {
            $fields['networking_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_connections', 'plans')) {
            $fields['max_connections'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0]; // 0 = ilimitado
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down()
    {
        foreach (['max_connections', 'networking_enabled'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
