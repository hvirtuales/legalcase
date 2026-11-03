<?php

namespace Components\Contabilidad\Models;

use App\Traits\Auditable;
use CodeIgniter\Model;

class BeneficiaryModel extends Model
{
    use Auditable;

    protected $table            = 'cmp_cont_beneficiaries';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'name', 'id_type', 'id_number', 'email', 'phone', 'address', 'city',
        'contact_name', 'notes', 'bank_name', 'bank_account_type', 'bank_account_number',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[200]',
        'id_number'  => 'permit_empty|max_length[50]',
        'email'      => 'permit_empty|valid_email',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre / razón social es requerido.'],
    ];

    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = ['setUpdatedBy'];
    protected $beforeDelete = ['setDeletedBy'];
    protected $afterInsert  = ['auditInsert'];
    protected $afterUpdate  = ['auditUpdate'];
    protected $afterDelete  = ['auditDelete'];
}
