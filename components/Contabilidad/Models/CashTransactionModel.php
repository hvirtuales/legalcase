<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class CashTransactionModel extends Model
{
    protected $table         = 'cmp_cont_cash_transactions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $updatedField  = ''; // la tabla solo tiene created_at

    protected $allowedFields = ['cash_box_id', 'type', 'concept', 'amount', 'balance_after', 'date', 'reference', 'voucher_id'];
}
