<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Las columnas del directorio en `plans` (contrato §11), en la carpeta del
 * componente como manda el contrato y con el mismo cuidado que
 * `AddLeadFlowLimitsToPlans`: comprueba `tableExists('plans')`, `fieldExists()`
 * antes de cada `addColumn` y `resetDataCache()` tras cada ALTER.
 *
 * Con estas tres y las dos que ya existían se expresan los cuatro modelos de
 * participación del §10 del insumo de contenidos, sin una línea de código nuevo:
 *
 *  · Perfil básico      → directorio sí, destacado no, 1 perfil.
 *  · Destacado          → `lfl_featured = 1`.
 *  · Premium            → destacado + varios perfiles.
 *  · Firma aliada       → `max_profiles` alto.
 *
 * `max_profiles` cuenta perfiles PUBLICADOS; `0` = ilimitado, igual que `max_leads`.
 * Y como en todo el componente, **una columna que no existe significa «permitido»**.
 */
class AddDirectoryLimitsToPlans extends Migration
{
    private const COLUMNS = [
        'lfl_directory_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        'lfl_featured'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        'max_profiles'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
    ];

    public function up()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        foreach (self::COLUMNS as $name => $definition) {
            if (! $this->db->fieldExists($name, 'plans')) {
                $this->forge->addColumn('plans', [$name => $definition]);
                $this->db->resetDataCache();
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        foreach (array_keys(self::COLUMNS) as $name) {
            if ($this->db->fieldExists($name, 'plans')) {
                $this->forge->dropColumn('plans', $name);
                $this->db->resetDataCache();
            }
        }
    }
}
