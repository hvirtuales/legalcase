<?php

namespace Components\LegalCase\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Servicio y cupos del componente en `plans` (contrato §11). `plans` es del
 * núcleo: esta migración solo AÑADE columnas propias, comprueba que la tabla
 * exista y las retira en `down()`.
 *
 *  · `lgc_enabled`     — habilita LegalCase para las empresas del plan.
 *  · `lgc_max_matters` — asuntos por empresa (0 = ilimitado).
 *  · `lgc_max_portal`  — cuentas del portal por empresa (0 = ilimitado).
 */
class AddLegalCaseLimitsToPlans extends Migration
{
    private const COLUMNS = [
        'lgc_enabled'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        'lgc_max_matters' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
        'lgc_max_portal'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
    ];

    public function up()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }

        $fields = [];
        foreach (self::COLUMNS as $name => $def) {
            if (!$this->db->fieldExists($name, 'plans')) {
                $fields[$name] = $def;
            }
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }
        foreach (array_keys(self::COLUMNS) as $name) {
            if ($this->db->fieldExists($name, 'plans')) {
                $this->forge->dropColumn('plans', $name);
            }
        }
    }
}
