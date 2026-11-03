<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

class QuoteModel extends Model
{
    public const STATUSES     = ['pendiente', 'aprobada', 'facturada', 'rechazada'];
    public const PAYMENT_FORMS = ['Contado', 'Crédito'];
    public const PAYMENT_METHODS = ['Consignación bancaria', 'Efectivo', 'Transferencia bancaria'];
    public const CURRENCIES   = ['COP', 'USD', 'EUR'];
    public const DOC_TYPES    = ['Cotización', 'Presupuesto', 'Propuesta', 'Oferta Comercial', 'Proforma'];

    protected $table            = 'cmp_quo_quotes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'catalog_id', 'number', 'doc_type',
        'client_id', 'client_name', 'client_nit', 'client_city', 'client_address',
        'client_contact', 'client_phone', 'client_email',
        'seller_id', 'seller_name', 'seller_position', 'seller_email', 'seller_phone',
        'seller_card_url', 'seller_photo', 'seller_signature',
        'issue_date', 'validity_days', 'payment_form', 'payment_method', 'currency',
        'intro_html', 'terms_html', 'show_detail',
        'subtotal', 'discount_total', 'taxable_base', 'tax_total',
        'retention_percent', 'retention_total', 'total',
        'status', 'created_by',
        // Enlace público para el cliente
        'public_token', 'public_password', 'public_enabled',
        'client_response', 'client_responded_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'number'     => 'required|max_length[40]',
        'doc_type'   => 'permit_empty|max_length[40]',
        'status'     => 'permit_empty|in_list[pendiente,aprobada,facturada,rechazada]',
        'currency'   => 'permit_empty|in_list[COP,USD,EUR]',
    ];

    protected $validationMessages = [
        'number' => ['required' => 'La cotización necesita un número.'],
    ];
}
