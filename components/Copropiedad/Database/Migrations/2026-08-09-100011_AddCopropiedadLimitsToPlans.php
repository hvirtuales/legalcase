<?php

namespace Components\Copropiedad\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Añade a `plans` la bandera y el cupo del componente (contrato §11).
 *
 * La migración **vive en la carpeta del componente**, no en el núcleo: la columna es
 * suya, así que se instala y se revierte con él. Dos reglas para no pisar al núcleo:
 * solo **añade** columnas propias —nunca redefine `plans`— y comprueba
 * `tableExists()` al entrar y `fieldExists()` antes de cada `addColumn`, porque el
 * núcleo crea la tabla antes que nosotros.
 *
 * `max_units` cuenta **inmuebles**, que es la unidad con la que se cobra este tipo de
 * software (un conjunto de 300 apartamentos paga más que uno de 40). `0` = ilimitado.
 *
 * > **Ojo con `fieldExists()`:** cachea los nombres de columna, así que tras un
 * > `ALTER` hay que llamar a `resetDataCache()` o la siguiente comprobación recibe la
 * > lista de antes. Es el mismo tropiezo que hizo perder en silencio un índice del
 * > núcleo.
 */
class AddCopropiedadLimitsToPlans extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        if (! $this->db->fieldExists('copropiedad_enabled', 'plans')) {
            $this->forge->addColumn('plans', [
                'copropiedad_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->db->resetDataCache();
        }

        if (! $this->db->fieldExists('max_units', 'plans')) {
            $this->forge->addColumn('plans', [
                'max_units' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            ]);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        foreach (['copropiedad_enabled', 'max_units'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
                $this->db->resetDataCache();
            }
        }
    }
}
