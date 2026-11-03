<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class CompanyAccountModel extends Model
{
    protected $table         = 'cmp_cont_company_accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $allowedFields = [
        'company_id', 'bank_name', 'account_type', 'account_number', 'account_holder', 'initial_balance', 'notes', 'is_active',
    ];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'bank_name'  => 'required|max_length[120]',
    ];
}
