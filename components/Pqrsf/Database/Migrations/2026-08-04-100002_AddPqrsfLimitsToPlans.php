<?php

namespace Components\Pqrsf\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vive en el componente: es SU bandera y SU cupo. `plans` es del núcleo, así
 * que la migración solo AÑADE columnas propias y comprueba que la tabla exista
 * (el núcleo la crea antes); nunca la redefine.
 *
 * Servicio y cupo del componente PQRSF en `plans` (contrato §11):
 *   - `pqrsf_enabled`: habilita el servicio para las empresas del plan.
 *   - `max_requests`: solicitudes por empresa (0 = ilimitado).
 */
class AddPqrsfLimitsToPlans extends Migration
{
    /** @var list<string> */
    private array $columns = ['pqrsf_enabled', 'max_requests'];

    public function up()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }

        $fields = [];
        if (!$this->db->fieldExists('pqrsf_enabled', 'plans')) {
            $fields['pqrsf_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (!$this->db->fieldExists('max_requests', 'plans')) {
            $fields['max_requests'] = ['type' => 'INT', 'constraint' => 11, 'default' => 0];
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down()
    {
        foreach ($this->columns as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
