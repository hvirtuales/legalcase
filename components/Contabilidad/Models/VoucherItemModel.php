<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class VoucherItemModel extends Model
{
    protected $table         = 'cmp_cont_voucher_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'voucher_id', 'description', 'amount', 'iva_rate', 'iva_amount',
        'retencion_rate', 'retencion_amount', 'sort_order',
    ];
    protected $useTimestamps = false;
}
