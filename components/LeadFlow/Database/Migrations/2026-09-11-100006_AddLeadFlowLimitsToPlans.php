<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Añade a `plans` la bandera y el cupo del componente (contrato §11).
 *
 * La migración vive en la carpeta del componente: la columna es suya, así que se
 * instala y se revierte con él. Solo **añade** columnas propias, comprueba
 * `tableExists('plans')` al entrar y `fieldExists()` antes de cada `addColumn`, y
 * llama a `resetDataCache()` tras cada ALTER (`fieldExists()` cachea las columnas).
 *
 * `max_leads` cuenta leads vivos de la firma; `0` = ilimitado.
 */
class AddLeadFlowLimitsToPlans extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        if (! $this->db->fieldExists('leadflow_enabled', 'plans')) {
            $this->forge->addColumn('plans', [
                'leadflow_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->db->resetDataCache();
        }

        if (! $this->db->fieldExists('max_leads', 'plans')) {
            $this->forge->addColumn('plans', [
                'max_leads' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            ]);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        foreach (['leadflow_enabled', 'max_leads'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
                $this->db->resetDataCache();
            }
        }
    }
}
