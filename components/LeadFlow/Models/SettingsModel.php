<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;

/** Ajustes de la firma (1 fila por empresa, creada con valores por defecto). */
class SettingsModel extends Model
{
    protected $table         = 'cmp_lfl_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'currency', 'no_response_days', 'consent_text', 'public_intro',
        // Conexión con USCIS, por firma: el cupo de 1.000 consultas al día es de
        // cada una. El secret va cifrado (lo cifra `UscisService`, no el modelo).
        'uscis_client_id', 'uscis_client_secret', 'uscis_mode'];

    public const CURRENCIES = ['USD', 'COP', 'MXN', 'EUR', 'CAD'];

    public function forCompany(int $companyId): array
    {
        $row = $this->where('company_id', $companyId)->first();
        if ($row) {
            return $row;
        }

        $this->insert(['company_id' => $companyId, 'currency' => 'USD', 'no_response_days' => 7]);

        return $this->where('company_id', $companyId)->first() ?? [
            'company_id' => $companyId, 'currency' => 'USD', 'no_response_days' => 7,
            'consent_text' => null, 'public_intro' => null,
        ];
    }

    public function saveFor(int $companyId, array $data): bool
    {
        $row = $this->forCompany($companyId);

        return isset($row['id']) ? $this->update((int) $row['id'], $data) : (bool) $this->insert($data + ['company_id' => $companyId]);
    }
}
