<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class VoucherEntryModel extends Model
{
    protected $table         = 'cmp_cont_voucher_entries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['voucher_id', 'account_code', 'account_name', 'debit', 'credit', 'description', 'sort_order'];
    protected $useTimestamps = false;
}
