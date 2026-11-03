<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

class ClientModel extends Model
{
    protected $table            = 'cmp_quo_clients';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'price_tier', 'name', 'nit', 'city', 'address',
        'contact_name', 'phone', 'email', 'email2', 'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[200]',
        'nit'        => 'permit_empty|max_length[50]',
        'email'      => 'permit_empty|valid_email',
        'email2'     => 'permit_empty|valid_email',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'La razón social es obligatoria.'],
    ];

    /** Ciudades distintas de los clientes de la empresa, para el filtro. */
    public function citiesFor(int $companyId): array
    {
        $rows = $this->select('city')
            ->where('company_id', $companyId)
            ->where('city !=', '')
            ->groupBy('city')
            ->orderBy('city', 'asc')
            ->findAll();

        return array_column($rows, 'city');
    }
}
