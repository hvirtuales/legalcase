<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

/** PUC global (sin company_id). */
class PucAccountModel extends Model
{
    protected $table         = 'cmp_cont_puc_accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['code', 'name', 'type', 'nature', 'level', 'parent_code', 'description', 'is_active'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $validationRules = [
        'code'   => 'required|max_length[20]',
        'name'   => 'required|max_length[200]',
        'type'   => 'required|in_list[activo,pasivo,patrimonio,ingreso,gasto,costo]',
        'nature' => 'required|in_list[debito,credito]',
        'level'  => 'permit_empty|is_natural_no_zero',
    ];
}
