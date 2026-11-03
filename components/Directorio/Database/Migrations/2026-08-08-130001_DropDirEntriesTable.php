<?php

namespace Components\Directorio\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Retira `cmp_dir_entries`: las entradas escritas a mano.
 *
 * Eran el componente original —un listado de locales que se tecleaba uno a
 * uno—, y quedaron sin uso en cuanto las **vistas** pasaron a armarse con las
 * empresas reales del grupo: los mismos datos, ya cargados, sin teclear nada y
 * sin la garantía de que un día dejen de coincidir con la ficha de la empresa.
 *
 * `down()` **no la recrea** a propósito. Una migración simétrica devolvería una
 * tabla sin modelo, sin controlador y sin vistas —el código se fue con ella—,
 * así que lo único que conseguiría es dejar una tabla huérfana en la base. La
 * migración que la creó sigue en la carpeta y su `down()` la borra con
 * `IF EXISTS`, así que revertir el componente entero (`--drop-data`) sigue
 * funcionando sin tropezar.
 */
class DropDirEntriesTable extends Migration
{
    public function up()
    {
        // `true` = IF EXISTS: en una instalación nueva la tabla nunca llegó a
        // crearse si se instala el componente ya en v3, y esto no debe fallar.
        $this->forge->dropTable('cmp_dir_entries', true);
    }

    public function down()
    {
        // A propósito, nada. Ver el comentario de la clase.
    }
}
