<?php

namespace Components\Mapas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sub-elementos: un elemento puede colgar de otro.
 *
 * El caso que lo pide es la RUTA. Un local quiere ofrecer un «cómo llegar», y
 * esa ruta no es un elemento más del plano —dibujada siempre, junto a las otras
 * cuarenta, convierte el mapa en una maraña—: es algo **de** ese local, que
 * aparece cuando alguien lo pide desde su ficha. Lo mismo vale para la entrada
 * de servicio de un almacén o la zona de espera de una clínica.
 *
 * `parent_id` **sin clave foránea**, a propósito y por dos motivos: la tabla usa
 * soft-delete, así que un `ON DELETE CASCADE` casi nunca dispararía —quien borra
 * los hijos es `MapService::deletePoint()`, a mano—; y es una auto-referencia,
 * que es justo donde una FK estorba al copiar un mapa entero (hay que insertar
 * el padre antes que el hijo y con el id nuevo). Es la misma decisión que tomó
 * Copropiedad con `parent_unit_id`.
 *
 * **Un solo nivel de anidación.** Un hijo no puede tener hijos: lo comprueba
 * `Points::parentIfSent()` en el servidor, y de ello dependen el pintado, la
 * ficha y la cascada del directorio, que asumen dos alturas y no un árbol.
 */
class AddParentToMapPoints extends Migration
{
    private const TABLE = 'cmp_map_points';

    public function up()
    {
        if (!$this->db->tableExists(self::TABLE) || $this->db->fieldExists('parent_id', self::TABLE)) {
            return;
        }

        $this->forge->addColumn(self::TABLE, [
            'parent_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);

        // `fieldExists()` cachea los nombres de columna: sin refrescar, el
        // índice se crearía sobre la lista de ANTES del ALTER y se perdería en
        // silencio. Ya pasó una vez con `companies_parent_id`.
        $this->db->resetDataCache();

        // Los hijos se piden SIEMPRE por el padre —al pintar la ficha y al
        // armar la cascada del directorio—, así que la columna se lee mucho más
        // de lo que se escribe.
        $this->forge->addKey('parent_id');
        $this->forge->processIndexes(self::TABLE);
    }

    public function down()
    {
        if ($this->db->tableExists(self::TABLE) && $this->db->fieldExists('parent_id', self::TABLE)) {
            $this->forge->dropColumn(self::TABLE, 'parent_id');
            $this->db->resetDataCache();
        }
    }
}
