<?php

namespace Components\Copropiedad\Models\Concerns;

/**
 * Lo que comparten las diez tablas del componente: **todas llevan `company_id` y
 * ninguna se consulta sin filtrarlo**.
 *
 * Está en un rasgo y no copiado diez veces porque el aislamiento multi-empresa es
 * justo la clase de comprobación que se olvida en la décima copia — y olvidarla no
 * da error, enseña los datos de otro conjunto.
 *
 * `owned()` es la contrapartida en el otro sentido: tener el permiso `cop.edit` no
 * autoriza a editar el inmueble de **otra** empresa (contrato §9). Todo controlador
 * pasa por aquí antes de tocar una fila, y hay prueba explícita de que alterar el id
 * en el POST no sirve de nada.
 */
trait CompanyScoped
{
    /** Consulta ya acotada a la empresa. */
    public function forCompany(int $companyId): self
    {
        return $this->where($this->table . '.company_id', $companyId);
    }

    /**
     * La fila, **solo si es de esa empresa**. `null` si no existe o es de otra:
     * las dos cosas se contestan igual para no confirmar que el id existe.
     */
    public function owned(int $id, int $companyId): ?array
    {
        if ($id <= 0 || $companyId <= 0) {
            return null;
        }
        $row = $this->find($id);

        return $row && (int) $row['company_id'] === $companyId ? $row : null;
    }

    /** Cuántas filas vivas tiene la empresa (para cupos y totales del panel). */
    public function countFor(int $companyId): int
    {
        return $this->where($this->table . '.company_id', $companyId)->countAllResults();
    }
}
