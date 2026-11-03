<?php

namespace Components\Mailer\Models;

use CodeIgniter\Model;

class SuppressionModel extends Model
{
    protected $table         = 'cmp_mailer_suppressions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'email', 'reason', 'notes'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $updatedField  = ''; // solo created_at

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'email'      => 'required|valid_email|max_length[190]',
    ];
    protected $validationMessages = [
        'email' => ['valid_email' => 'Mailer.vrEmailValid'],
    ];
}
