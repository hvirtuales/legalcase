<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/** Campañas. `utm_campaign` es lo que ata un lead de una landing con su campaña. */
class CampaignModel extends Model
{
    use CompanyScoped;

    protected $table          = 'cmp_lfl_campaigns';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'source_id', 'name', 'utm_campaign', 'budget', 'starts_on', 'ends_on', 'notes', 'is_active'];

    protected $validationRules = [
        'company_id'   => 'required|is_natural_no_zero',
        'name'         => 'required|max_length[120]',
        'utm_campaign' => 'permit_empty|max_length[120]',
        'budget'       => 'permit_empty|decimal',
        'starts_on'    => 'permit_empty|valid_date[Y-m-d]',
        'ends_on'      => 'permit_empty|valid_date[Y-m-d]',
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

    public function byUtm(int $companyId, string $utm): ?array
    {
        $utm = mb_strtolower(trim($utm));
        if ($utm === '') {
            return null;
        }
        foreach ($this->forCompany($companyId)->where('is_active', 1)->findAll() as $c) {
            if ($c['utm_campaign'] !== null && mb_strtolower($c['utm_campaign']) === $utm) {
                return $c;
            }
        }

        return null;
    }
}
