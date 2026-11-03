<?php

namespace Components\Formularios\Models;

use CodeIgniter\Model;

/** Archivos y firmas de una respuesta. Sin soft-delete: al borrar se va el archivo. */
class AttachmentModel extends Model
{
    protected $table            = 'cmp_frm_attachments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'response_id', 'form_id', 'company_id', 'field_id',
        'original_name', 'stored_name', 'mime', 'size', 'created_at',
    ];

    protected $useTimestamps = false;

    public function forResponse(int $responseId): array
    {
        return $this->where('response_id', $responseId)->orderBy('id', 'asc')->findAll();
    }

    /** Los de un campo concreto de una respuesta. */
    public function forField(int $responseId, string $fieldId): array
    {
        return $this->where('response_id', $responseId)->where('field_id', $fieldId)->findAll();
    }
}
