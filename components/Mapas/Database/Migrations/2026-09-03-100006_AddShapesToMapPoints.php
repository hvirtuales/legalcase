<?php

namespace Components\Mapas\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lo que convierte un «punto» en cualquier cosa dibujada sobre el plano.
 *
 * Hasta aquí el componente solo sabía de PINES: un punto exacto con su `x`/`y`.
 * El recorrido de una evacuación, la superficie de un local, la zona de
 * restaurantes o un rótulo sobre el plano no son un punto, y no había forma de
 * representarlos.
 *
 * Se amplía la tabla que ya existe en vez de crear una `cmp_map_shapes` aparte:
 * el directorio público, el buscador, el filtro por categoría, la ficha
 * emergente, la columna del editor y los cuatro endpoints de `Points` **ya
 * operan sobre puntos**. Con una tabla hermana habría que duplicar seis columnas
 * descriptivas y partir en dos cada una de esas piezas.
 *
 * `x`/`y` **no cambian de sitio, cambian de papel**: en un pin son el punto; en
 * una forma son su ANCLA (el centroide), y por eso `attachCard()`,
 * `focusPoint()`, `forLevel()` y el directorio siguen funcionando sin tocarse.
 *
 * Las filas que ya existen quedan como `pin` por el `default`: no hay migración
 * de datos que hacer.
 */
class AddShapesToMapPoints extends Migration
{
    private const TABLE = 'cmp_map_points';

    private const COLUMNS = [
        // El vocabulario vive en `PointModel::KINDS`, no en un ENUM: el
        // contrato §8 lo prohíbe porque no existe en los tres motores.
        'kind'     => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pin'],

        // Los vértices, en TEXT + json_encode. Tampoco JSON nativo, por lo
        // mismo. El formato es `[[x,y],…]` en porcentaje del plano, y es el
        // `kind` el que le da el significado (ver `PointModel::KINDS`).
        'geometry' => ['type' => 'TEXT', 'null' => true],

        // La presentación del elemento: relleno, transparencia, color y grosor
        // del borde, tipo de trazo, tamaño del texto…
        //
        // **Una sola columna y no seis**, y en JSON, porque es un juego ABIERTO:
        // son propiedades que solo se pintan —nunca se filtra ni se ordena por
        // ellas—, así que no ganan nada siendo columnas, y cada una nueva
        // costaría una migración. Vacío = lo hereda todo de su categoría, que
        // sigue siendo el comportamiento por defecto y el que mantiene un plano
        // legible sin que nadie toque nada.
        'style'    => ['type' => 'TEXT', 'null' => true],
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
