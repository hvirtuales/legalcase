<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Documentos preliminares del lead (§24). Privados: el archivo vive en
 * `writable/uploads/leadflow/<empresa>/` y la descarga pasa por controlador.
 *
 * `is_authorized` es la llave de la transferencia: solo lo autorizado se ofrece
 * al convertir y solo eso viaja a Docketwise.
 */
class DocumentModel extends Model
{
    use CompanyScoped;

    public const MAX_BYTES = 8 * 1024 * 1024;
    public const MIMES     = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    protected $table          = 'cmp_lfl_documents';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'lead_id', 'document_type_id', 'original_name', 'storage_key', 'mime_type',
        'size', 'hash', 'is_authorized', 'source', 'uploaded_by'];

    public function forLead(int $leadId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('lead_id', $leadId)->orderBy('id', 'desc')->findAll();
    }
}
