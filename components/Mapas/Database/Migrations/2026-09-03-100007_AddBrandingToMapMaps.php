<?php

namespace Components\Mapas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La marca de la página pública, por mapa.
 *
 * Hasta aquí la cabecera enseñaba siempre el logo de la EMPRESA. Pero un mismo
 * grupo puede publicar varios mapas —dos centros comerciales, una feria— y cada
 * uno tiene su identidad; y hay sitios donde la cabecera sobra del todo, porque
 * el mapa se proyecta en un tótem y lo único que hace es robarle alto al plano.
 *
 * `logo` vacío = el de la empresa, que es el comportamiento que ya había: quien
 * no toque nada no nota el cambio.
 */
class AddBrandingToMapMaps extends Migration
{
    private const TABLE = 'cmp_map_maps';

    private const COLUMNS = [
        'logo'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        'show_header' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
    ];

    public function up()
    {
        if (!$this->db->tableExists(self::TABLE)) {
            return;
        }
        foreach (self::COLUMNS as $name => $def) {
            if ($this->db->fieldExists($name, self::TABLE)) {
                continue;
            }
            $this->forge->addColumn(self::TABLE, [$name => $def]);
            // `fieldExists()` cachea los nombres de columna: sin esto la
            // siguiente vuelta pregunta por la lista de ANTES del ALTER.
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if (!$this->db->tableExists(self::TABLE)) {
            return;
        }
        foreach (array_keys(self::COLUMNS) as $name) {
            if ($this->db->fieldExists($name, self::TABLE)) {
                $this->forge->dropColumn(self::TABLE, $name);
                $this->db->resetDataCache();
            }
        }
    }
}
