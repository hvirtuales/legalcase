<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

class SettingsModel extends Model
{
    protected $table            = 'cmp_quo_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'doc_type', 'prefix', 'initial_number', 'last_number', 'last_prefix',
        'default_currency', 'default_validity_days', 'default_retention',
        'terms_html', 'cover_enabled', 'cover_title', 'cover_html', 'show_detail_default',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'     => 'required|is_natural_no_zero',
        'doc_type'       => 'required|max_length[40]',
        'prefix'         => 'required|max_length[10]|alpha_numeric_punct',
        'initial_number' => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'prefix' => ['required' => 'El prefijo del documento es obligatorio.'],
    ];

    /**
     * Ajustes de la empresa, creando la fila con los valores por defecto si falta.
     * Se pasan explícitos porque `doc_type` y `prefix` son obligatorios: con solo
     * `company_id` la validación rechazaría el insert.
     */
    public function forCompany(int $companyId): array
    {
        $row = $this->where('company_id', $companyId)->first();
        if ($row) {
            return $row;
        }

        $this->insert([
            'company_id'            => $companyId,
            'doc_type'              => 'Cotización',
            'prefix'                => 'COT',
            'initial_number'        => 1,
            'default_currency'      => 'COP',
            'default_validity_days' => 15,
            'show_detail_default'   => 1,
        ]);

        return $this->where('company_id', $companyId)->first() ?? [
            'id'                    => 0,
            'company_id'            => $companyId,
            'doc_type'              => 'Cotización',
            'prefix'                => 'COT',
            'initial_number'        => 1,
            'last_number'           => 0,
            'last_prefix'           => '',
            'default_currency'      => 'COP',
            'default_validity_days' => 15,
            'default_retention'     => 0,
            'terms_html'            => '',
            'cover_enabled'         => 0,
            'cover_title'           => '',
            'cover_html'            => '',
            'show_detail_default'   => 1,
        ];
    }
}
