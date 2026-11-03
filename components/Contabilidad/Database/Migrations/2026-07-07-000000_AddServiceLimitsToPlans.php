<?php

namespace Components\Contabilidad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vive en el componente: es SU bandera y SU cupo. `plans` es del núcleo, así
 * que la migración solo AÑADE columnas propias y comprueba que la tabla exista
 * (el núcleo la crea antes); nunca la redefine.
 *
 * Planes: habilitar el servicio (contabilidad) y fijar límites de comprobantes
 * y cuentas de cobro. Idempotente. service_enabled por defecto 1 (los planes
 * existentes conservan el servicio).
 */
class AddServiceLimitsToPlans extends Migration
{
    public function up()
    {
        $fields = [];
        if (!$this->db->fieldExists('service_enabled', 'plans')) {
            $fields['service_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_vouchers', 'plans')) {
            $fields['max_vouchers'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0]; // 0 = ilimitado
        }
        if (!$this->db->fieldExists('max_payment_requests', 'plans')) {
            $fields['max_payment_requests'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0]; // 0 = ilimitado
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down()
    {
        foreach (['max_payment_requests', 'max_vouchers', 'service_enabled'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
