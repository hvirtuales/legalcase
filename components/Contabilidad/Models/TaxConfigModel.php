<?php

namespace Components\Contabilidad\Models;

use App\Traits\Auditable;
use CodeIgniter\Model;

class TaxConfigModel extends Model
{
    use Auditable;

    protected $table          = 'cmp_cont_tax_configs';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields  = true;

    protected $allowedFields = [
        'company_id', 'name', 'code', 'category', 'rate', 'is_additive', 'is_active', 'sort_order',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|max_length[120]',
        'code'       => 'required|max_length[20]',
        'category'   => 'required|in_list[iva,retencion,other]',
        'rate'       => 'permit_empty|decimal',
    ];

    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = ['setUpdatedBy'];
    protected $beforeDelete = ['setDeletedBy'];
    protected $afterInsert  = ['auditInsert'];
    protected $afterUpdate  = ['auditUpdate'];
    protected $afterDelete  = ['auditDelete'];
}
