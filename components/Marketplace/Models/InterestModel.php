<?php

namespace Components\Marketplace\Models;

use CodeIgniter\Model;

class InterestModel extends Model
{
    public const STATUSES = ['new', 'contacted', 'closed'];

    protected $table            = 'cmp_mkt_interests';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['listing_id', 'company_id', 'user_id', 'message', 'status'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'listing_id' => 'required|is_natural_no_zero',
        'company_id' => 'required|is_natural_no_zero',
        'message'    => 'required|min_length[10]|max_length[1000]',
        'status'     => 'permit_empty|in_list[new,contacted,closed]',
    ];

    protected $validationMessages = [
        'message' => [
            'required'   => 'Cuéntale a la empresa qué necesitas.',
            'min_length' => 'El mensaje debe tener al menos 10 caracteres.',
        ],
    ];
}
