<?php

namespace Components\Contabilidad\Models;

use App\Traits\Auditable;
use CodeIgniter\Model;

class VoucherModel extends Model
{
    use Auditable;

    protected $table            = 'cmp_cont_vouchers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'type_id', 'number', 'date', 'beneficiary_name', 'beneficiary_id',
        'description', 'payment_method', 'source_account_label', 'source_type', 'source_id',
        'subtotal', 'total_iva', 'total_retencion', 'total_other_taxes', 'total_amount', 'status',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'type_id'    => 'required|is_natural_no_zero',
        'date'       => 'required|valid_date[Y-m-d]',
    ];

    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = ['setUpdatedBy'];
    protected $beforeDelete = ['setDeletedBy'];
    protected $afterInsert  = ['auditInsert'];
    protected $afterUpdate  = ['auditUpdate'];
    protected $afterDelete  = ['auditDelete'];
}
