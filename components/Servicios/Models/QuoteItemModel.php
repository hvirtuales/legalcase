<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

class QuoteItemModel extends Model
{
    protected $table            = 'cmp_quo_quote_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'quote_id', 'product_id', 'code', 'name', 'description', 'details_url',
        'unit_price', 'quantity', 'discount_type', 'discount_value', 'tax_percent',
        'line_subtotal', 'line_discount', 'line_base', 'line_tax', 'line_total', 'sort_order',
    ];

    protected $useTimestamps = false; // la línea vive y muere con su cotización

    protected $validationRules = [
        'quote_id' => 'required|is_natural_no_zero',
        'name'     => 'required|max_length[200]',
    ];

    /** Líneas de una cotización, en su orden. */
    public function forQuote(int $quoteId): array
    {
        return $this->where('quote_id', $quoteId)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->findAll();
    }
}
