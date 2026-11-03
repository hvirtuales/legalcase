<?php

namespace Components\Directorio\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bandera y cupo del Directorio en `plans`.
 *
 * La migración va **en el componente**, no en el núcleo: la columna es suya, así
 * que se instala y se revierte con él. Dos reglas para no pisar al núcleo (que
 * es quien crea la tabla): solo añade columnas propias y comprueba
 * `tableExists`/`fieldExists` antes de tocar nada.
 *
 * Estas columnas se ofrecen en los planes de **ámbito tenant** (el formulario
 * de planes lo decide por el `scope` del componente, no por la migración).
 */
class AddDirectoryLimitsToPlans extends Migration
{
    /** @var array<string,array> */
    private array $columns = [
        'directory_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true, 'default' => 1],
        'max_entries'       => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => 0],
    ];

    public function up()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }

        $fields = [];
        foreach ($this->columns as $name => $def) {
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
        foreach (array_keys($this->columns) as $name) {
            if ($this->db->fieldExists($name, 'plans')) {
                $this->forge->dropColumn('plans', $name);
            }
        }
    }
}
