<?php

namespace Components\LegalCase\Models;

/** Personas naturales o jurídicas: clientes, contrapartes, autoridades, testigos… El papel lo da cada asunto. */
class ContactModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_contacts';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'kind', 'name', 'doc_type', 'doc_number', 'email', 'phone', 'address', 'city', 'country', 'locale', 'notes', 'created_by'];

    public const KINDS     = ['person', 'org'];
    public const DOC_TYPES = ['CC', 'CE', 'NIT', 'PA', 'TI', 'SSN', 'EIN', 'OTHER'];

    protected $validationRules = [
        'name'    => 'required|max_length[190]',
        'kind'    => 'required|in_list[person,org]',
        'email'   => 'permit_empty|valid_email|max_length[190]',
        'country' => 'permit_empty|alpha|exact_length[2]',
        'locale'  => 'permit_empty|in_list[es,en]',
    ];
    protected $validationMessages = [
        'name'  => ['required' => 'Lgc.vContactName'],
        'email' => ['valid_email' => 'Lgc.vEmail'],
    ];

    /** [id => nombre] para selectores. */
    public function mapFor(int $companyId): array
    {
        $out = [];
        foreach ($this->forCompany($companyId)->orderBy('name', 'ASC')->findAll(500) as $c) {
            $out[(int) $c['id']] = $c['name'];
        }

        return $out;
    }
}
