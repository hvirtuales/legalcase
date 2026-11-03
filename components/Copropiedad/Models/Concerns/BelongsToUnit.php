<?php

namespace Components\Copropiedad\Models\Concerns;

/**
 * Lo que comparten mascotas, vehículos, contactos y autorizaciones: **todos cuelgan
 * de un inmueble**, y en cualquier listado hay que enseñar de cuál.
 *
 * Sin este `join`, cada listado global («todos los vehículos del conjunto») haría una
 * consulta por fila para averiguar la torre y el apartamento. Con doscientos
 * vehículos eso son doscientas consultas para pintar una tabla.
 */
trait BelongsToUnit
{
    /**
     * Consulta base con el inmueble y su torre ya unidos.
     *
     * Los alias van con prefijo `unit_`/`block_` para que no choquen con las columnas
     * propias de la tabla (`name`, `code`… los tienen casi todas).
     */
    public function withUnit(int $companyId)
    {
        return $this->select($this->table . '.*, cmp_cop_units.code AS unit_code,'
                . ' cmp_cop_units.kind AS unit_kind, cmp_cop_blocks.name AS block_name')
            ->join('cmp_cop_units', 'cmp_cop_units.id = ' . $this->table . '.unit_id', 'left')
            ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
            ->where($this->table . '.company_id', $companyId);
    }

    /** Lo que cuelga de un inmueble concreto (para su ficha). */
    public function forUnit(int $unitId, int $companyId): array
    {
        return $this->where($this->table . '.company_id', $companyId)
            ->where($this->table . '.unit_id', $unitId)
            ->findAll();
    }
}
