<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Etapas del pipeline.
 *
 * El `kind` es lo que le da significado a una etapa, no su nombre (que la firma
 * puede cambiar): `open` (en curso), `won` (ganado), `converted` (ya en
 * Docketwise) y `lost` (los estados alternativos: no calificado, sin respuesta,
 * no-show, perdido, duplicado). El `code` identifica las etapas de sistema para
 * que las reglas —agendar una consulta, convertir— sepan a cuál mover.
 */
class StageModel extends Model
{
    use CompanyScoped;

    public const KINDS  = ['open', 'won', 'converted', 'lost'];
    public const COLORS = ['primary', 'info', 'success', 'warning', 'danger', 'secondary', 'light'];

    /** Etapas de sistema: código => [kind, probabilidad, color]. En este orden se siembran. */
    public const SYSTEM = [
        'new'                    => ['open', 5, 'info'],
        'contacted'              => ['open', 10, 'info'],
        'prequalification'       => ['open', 20, 'primary'],
        'qualified'              => ['open', 35, 'primary'],
        'consultation_scheduled' => ['open', 50, 'warning'],
        'consultation_done'      => ['open', 60, 'warning'],
        'proposal'               => ['open', 75, 'warning'],
        'follow_up'              => ['open', 80, 'warning'],
        'won'                    => ['won', 100, 'success'],
        'converted'              => ['converted', 100, 'success'],
        'not_qualified'          => ['lost', 0, 'secondary'],
        'no_response'            => ['lost', 0, 'secondary'],
        'no_show'                => ['lost', 0, 'secondary'],
        'lost'                   => ['lost', 0, 'danger'],
        'duplicate'              => ['lost', 0, 'light'],
    ];

    protected $table          = 'cmp_lfl_stages';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'name', 'code', 'kind', 'sort', 'probability', 'color', 'is_active'];

    protected $validationRules = [
        'company_id'  => 'required|is_natural_no_zero',
        'name'        => 'required|max_length[80]',
        'kind'        => 'required|in_list[open,won,converted,lost]',
        'probability' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vStageName'],
    ];

    public function ordered(int $companyId, bool $activeOnly = false): array
    {
        $b = $this->forCompany($companyId)->orderBy('sort', 'asc')->orderBy('id', 'asc');
        if ($activeOnly) {
            $b->where('is_active', 1);
        }

        return $b->findAll();
    }

    /** @return array<int,array> id => etapa */
    public function mapFor(int $companyId): array
    {
        $out = [];
        foreach ($this->ordered($companyId) as $s) {
            $out[(int) $s['id']] = $s;
        }

        return $out;
    }

    public function byCode(int $companyId, string $code): ?array
    {
        return $this->forCompany($companyId)->where('code', $code)->first();
    }
}
