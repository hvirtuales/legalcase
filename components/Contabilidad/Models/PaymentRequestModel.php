<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class PaymentRequestModel extends Model
{
    protected $table         = 'cmp_cont_payment_requests';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'reference', 'collaborator_name', 'collaborator_id_type', 'collaborator_id',
        'collaborator_email', 'collaborator_phone', 'collaborator_city', 'collaborator_address',
        'collaborator_contact', 'collaborator_bank', 'collaborator_account_type', 'collaborator_account',
        'concept', 'period', 'items_json', 'amount', 'iva_amount', 'retencion_rate', 'retencion_amount',
        'total_amount', 'cert_no_iva', 'cert_art383', 'signature_data', 'signed_at', 'status',
        'admin_notes', 'reviewed_by', 'reviewed_at', 'voucher_id',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
