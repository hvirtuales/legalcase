<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class CashBoxModel extends Model
{
    protected $table         = 'cmp_cont_cash_boxes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $allowedFields = ['company_id', 'name', 'description', 'initial_balance'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|max_length[120]',
    ];
}
