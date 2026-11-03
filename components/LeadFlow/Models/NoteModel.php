<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Notas del lead. `is_transferable` decide si la nota se puede ofrecer al
 * convertir: una nota interna («no pagó la última vez») no debe viajar sola al
 * expediente jurídico.
 */
class NoteModel extends Model
{
    use CompanyScoped;

    protected $table          = 'cmp_lfl_notes';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'lead_id', 'body', 'is_transferable', 'created_by'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'lead_id'    => 'required|is_natural_no_zero',
        'body'       => 'required|max_length[5000]',
    ];
    protected $validationMessages = [
        'body' => ['required' => 'Lfl.vNoteBody'],
    ];

    public function forLead(int $leadId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('lead_id', $leadId)->orderBy('id', 'desc')->findAll();
    }
}
