<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/** Tipos de documento preliminar (pasaporte, identificación, I-94…). */
class DocumentTypeModel extends Model
{
    use CompanyScoped;

    protected $table          = 'cmp_lfl_document_types';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'name', 'sort', 'is_active'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|max_length[80]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vName'],
    ];

    /** @return array<int,string> */
    public function options(int $companyId): array
    {
        return array_column(
            $this->forCompany($companyId)->where('is_active', 1)->orderBy('sort', 'asc')->orderBy('name', 'asc')->findAll(),
            'name',
            'id'
        );
    }
}
