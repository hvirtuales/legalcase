<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class VoucherItemTaxModel extends Model
{
    protected $table         = 'cmp_cont_voucher_item_taxes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['item_id', 'tax_config_id', 'name', 'rate', 'amount', 'is_additive'];
    protected $useTimestamps = false;
}
