<?php

namespace Components\LeadFlow\Models\Concerns;

/**
 * Lo que comparten todas las tablas de la firma: **llevan `company_id` y ninguna
 * se consulta sin filtrarlo**.
 *
 * En un rasgo y no copiado veinte veces porque el aislamiento multi-empresa es
 * justo la comprobación que se olvida en la vigésima copia, y olvidarla no da
 * error: enseña los prospectos de otra firma.
 *
 * `owned()` es la otra mitad: tener `lfl.edit` no autoriza a tocar el registro de
 * otra empresa. Toda acción busca su fila por aquí antes de tocarla.
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

    /** Cuántas filas vivas tiene la empresa. */
    public function countFor(int $companyId): int
    {
        return $this->where($this->table . '.company_id', $companyId)->countAllResults();
    }
}
