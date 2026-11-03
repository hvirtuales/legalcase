<?php

namespace Components\Mapas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Las columnas de `plans` que gradúan este componente.
 *
 * Van en el componente y no en el núcleo (contrato §11): son **sus** columnas,
 * así que se instalan y se revierten con él. La única regla es que solo añaden
 * lo propio y comprueban antes que la tabla exista: `plans` la crea el núcleo y
 * un componente nunca la redefine.
 */
class AddMapLimitsToPlans extends Migration
{
    private const COLUMNS = [
        'maps_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        // 0 = sin límite, como el resto de cupos de la plataforma.
        'max_maps'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
    ];

    public function up()
    {
        if (!$this->db->tableExists('plans')) {
            return;
        }
        foreach (self::COLUMNS as $name => $def) {
            if ($this->db->fieldExists($name, 'plans')) {
                continue;
            }
            $this->forge->addColumn('plans', [$name => $def]);
            // `fieldExists()` cachea los nombres de columna: sin esto la
            // siguiente vuelta pregunta por la lista de ANTES del ALTER.
            $this->db->resetDataCache();
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
                $this->db->resetDataCache();
            }
        }
    }
}
