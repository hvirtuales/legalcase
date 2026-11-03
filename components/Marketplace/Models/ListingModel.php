<?php

namespace Components\Marketplace\Models;

use CodeIgniter\Model;

class ListingModel extends Model
{
    /** Lo que la empresa OFRECE y lo que NECESITA. */
    public const TYPES    = ['product', 'service', 'need', 'opportunity'];
    public const OFFERS   = ['product', 'service'];
    public const DEMANDS  = ['need', 'opportunity'];
    public const STATUSES = ['draft', 'published', 'paused', 'closed'];
    public const PRICE_TYPES = ['quote', 'fixed', 'from'];

    protected $table            = 'cmp_mkt_listings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'user_id', 'category_id', 'type', 'title', 'summary', 'description',
        'image', 'price_type', 'price_amount', 'price_currency', 'city', 'status',
        'valid_until', 'views',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'type'       => 'required|in_list[product,service,need,opportunity]',
        'title'      => 'required|min_length[5]|max_length[180]',
        'summary'    => 'permit_empty|max_length[255]',
        'price_type' => 'permit_empty|in_list[quote,fixed,from]',
        'status'     => 'permit_empty|in_list[draft,published,paused,closed]',
        'city'       => 'permit_empty|max_length[100]',
    ];

    protected $validationMessages = [
        'title' => [
            'required'   => 'El título es obligatorio.',
            'min_length' => 'El título debe tener al menos 5 caracteres.',
        ],
        'type' => ['in_list' => 'Tipo de publicación inválido.'],
    ];

    /** ¿La publicación es oferta (lo que la empresa da) o demanda (lo que busca)? */
    public static function side(string $type): string
    {
        return in_array($type, self::OFFERS, true) ? 'offer' : 'demand';
    }
}
