<?php

namespace Components\LegalCase\Models;

use CodeIgniter\Model;

/**
 * Base de los modelos del componente.
 *
 * Centraliza el aislamiento por empresa: `forCompany()` añade el `where` con el nombre
 * de tabla calificado (para que siga valiendo con JOIN) y `own()` resuelve un id
 * **solo si es de esa empresa**. Así un id cambiado a mano en un POST devuelve null
 * en vez del registro de otro despacho.
 */
abstract class BaseLgcModel extends Model
{
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $dateFormat     = 'datetime';

    public function forCompany(int $companyId): static
    {
        $this->where($this->table . '.company_id', $companyId);

        return $this;
    }

    /** El registro si pertenece a la empresa; null en cualquier otro caso. */
    public function own(int $companyId, int $id): ?array
    {
        if ($id <= 0 || $companyId <= 0) {
            return null;
        }

        return $this->where($this->table . '.company_id', $companyId)->find($id) ?: null;
    }
}
