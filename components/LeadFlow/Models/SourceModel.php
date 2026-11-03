<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/** Fuentes de captación. El `kind` alimenta el scoring (referido) y la analítica. */
class SourceModel extends Model
{
    use CompanyScoped;

    public const KINDS = ['web', 'meta', 'google', 'whatsapp', 'referral', 'other'];

    protected $table          = 'cmp_lfl_sources';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'name', 'kind', 'utm_source', 'is_active'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|max_length[80]',
        'kind'       => 'required|in_list[web,meta,google,whatsapp,referral,other]',
        'utm_source' => 'permit_empty|max_length[80]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vName'],
    ];

    /** @return array<int,string> */
    public function options(int $companyId, bool $activeOnly = true): array
    {
        $b = $this->forCompany($companyId)->orderBy('name', 'asc');
        if ($activeOnly) {
            $b->where('is_active', 1);
        }

        return array_column($b->findAll(), 'name', 'id');
    }

    /** La fuente cuyo `utm_source` coincide (sin distinguir mayúsculas). */
    public function byUtm(int $companyId, string $utm): ?array
    {
        $utm = mb_strtolower(trim($utm));
        if ($utm === '') {
            return null;
        }
        foreach ($this->forCompany($companyId)->where('is_active', 1)->findAll() as $s) {
            if ($s['utm_source'] !== null && mb_strtolower($s['utm_source']) === $utm) {
                return $s;
            }
        }

        return null;
    }

    public function byKind(int $companyId, string $kind): ?array
    {
        return $this->forCompany($companyId)->where('kind', $kind)->orderBy('id', 'asc')->first();
    }
}
