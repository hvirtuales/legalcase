<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class VoucherTypeModel extends Model
{
    protected $table         = 'cmp_cont_voucher_types';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'name', 'prefix', 'current_sequence'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
