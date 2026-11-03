<?php

namespace Components\Formularios\Models;

/**
 * Serializa y deserializa las columnas que guardan un árbol u objeto en TEXT.
 *
 * Portabilidad (contrato 8): nada de tipo JSON nativo, que no existe en los tres
 * motores, así que el árbol del formulario vive en un TEXT. Este trait hace que
 * eso sea invisible para el resto del código: el modelo entrega y recibe arrays.
 *
 * Copia propia y no un `use` del de Páginas: un componente no depende de otro
 * (contrato 0.4).
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

        if ($data['singleton'] ?? false) {
            $data['data'] = $decode($data['data'] ?? null);
        } elseif (is_array($data['data'] ?? null)) {
            $data['data'] = array_map($decode, $data['data']);
        }

        return $data;
    }
}
