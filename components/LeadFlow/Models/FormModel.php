<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Formularios dinámicos. El `schema` es TEXT con JSON (sin JSON nativo, §8);
 * `fields()` lo devuelve ya decodificado para que el resto del código solo vea
 * arrays.
 */
class FormModel extends Model
{
    use CompanyScoped;

    public const STATUSES = ['draft', 'published', 'closed'];

    protected $table          = 'cmp_lfl_forms';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'name', 'description', 'token', 'service_id', 'campaign_id', 'is_public',
        'status', 'schema', 'ask_consultation', 'success_message', 'submissions', 'views', 'created_by'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|max_length[150]',
        'status'     => 'permit_empty|in_list[draft,published,closed]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vName'],
    ];

    public static function fields(?array $form): array
    {
        $d = json_decode((string) ($form['schema'] ?? ''), true);

        return is_array($d) ? $d : [];
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function byToken(string $token): ?array
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return $this->where('token', $token)->first();
    }

    /** @return array<int,string> */
    public function options(int $companyId): array
    {
        return array_column($this->forCompany($companyId)->orderBy('name', 'asc')->findAll(), 'name', 'id');
    }
}
