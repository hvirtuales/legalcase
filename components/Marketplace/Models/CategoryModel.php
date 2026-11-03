<?php

namespace Components\Marketplace\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table            = 'cmp_mkt_categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['name', 'slug', 'icon', 'sort_order', 'is_active'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name' => 'required|max_length[120]',
        'slug' => 'required|max_length[140]',
    ];

    /** Categorías activas, en su orden de presentación. */
    public function active(): array
    {
        return $this->where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->findAll();
    }
}
