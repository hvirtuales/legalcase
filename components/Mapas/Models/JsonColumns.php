<?php

namespace Components\Mapas\Models;

/**
 * Serializa y deserializa las columnas que guardan una estructura en TEXT.
 *
 * Portabilidad §8: nada de tipo JSON nativo (no existe en los tres motores), así
 * que la geometría vive en un TEXT. Este trait hace que eso sea invisible para
 * el resto del código: el modelo entrega y recibe arrays.
 *
 * **Es una copia deliberada** del que estrenó el componente Páginas. Un
 * componente no puede depender de una clase de otro —soltar solo esta carpeta
 * en otra instalación reventaría con «class not found»—, que es el mismo motivo
 * por el que Mapas lleva su propio `PlanImage` en vez del `ImageOptimizer` del
 * núcleo (contrato §0.4).
 *
 * El modelo que lo use declara `protected array $jsonColumns = [...]` y registra
 * los tres callbacks:
 *
 *   protected $beforeInsert = ['encodeJson'];
 *   protected $beforeUpdate = ['encodeJson'];
 *   protected $afterFind    = ['decodeJson'];
 */
trait JsonColumns
{
    protected function encodeJson(array $data): array
    {
        foreach ($this->jsonColumns as $f) {
            if (isset($data['data'][$f]) && !is_string($data['data'][$f])) {
                $data['data'][$f] = json_encode(
                    $data['data'][$f],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }
        }

        return $data;
    }

    protected function decodeJson(array $data): array
    {
        $columns = $this->jsonColumns;

        $decode = static function ($row) use ($columns) {
            if (!is_array($row)) {
                return $row;
            }
            foreach ($columns as $f) {
                if (array_key_exists($f, $row)) {
                    $row[$f] = is_string($row[$f]) ? (json_decode($row[$f], true) ?: []) : ($row[$f] ?? []);
                }
            }

            return $row;
        };

        // `find()` entrega un registro; `findAll()`/`paginate()`, una lista.
        if ($data['singleton'] ?? false) {
            $data['data'] = $decode($data['data'] ?? null);
        } elseif (is_array($data['data'] ?? null)) {
            $data['data'] = array_map($decode, $data['data']);
        }

        return $data;
    }
}
