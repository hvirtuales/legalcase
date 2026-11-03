<?php

namespace Components\Mailer\Models;

use CodeIgniter\Model;

class ListModel extends Model
{
    protected $table         = 'cmp_mailer_lists';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'name', 'description', 'public_token', 'public_enabled', 'consent_text'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[150]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Mailer.vrList'],
    ];
}
