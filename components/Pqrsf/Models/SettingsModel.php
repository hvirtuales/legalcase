<?php

namespace Components\Pqrsf\Models;

use CodeIgniter\Model;

/** Configuración del componente por empresa (1 fila). */
class SettingsModel extends Model
{
    protected $table            = 'cmp_pqr_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'prefix', 'last_number',
        'allow_anonymous', 'captcha_enabled',
        'privacy_url', 'privacy_text', 'terms_url', 'terms_text', 'policy_version',
        'ai_enabled', 'ai_tone', 'ai_instructions', 'ack_template',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Configuración de la empresa, creándola con los valores por defecto la
     * primera vez. Se insertan explícitos: pasar solo `company_id` deja al
     * validador rechazando la fila en silencio.
     */
    public function forCompany(int $companyId): array
    {
        $row = $this->where('company_id', $companyId)->first();
        if ($row) {
            return $row;
        }

        $this->insert([
            'company_id'      => $companyId,
            'prefix'          => 'PQR',
            'last_number'     => 0,
            'allow_anonymous' => 1,
            'captcha_enabled' => 1,
            'policy_version'  => 'v1',
            'ai_enabled'      => 1,
            'ai_tone'         => 'formal',
        ]);

        return $this->where('company_id', $companyId)->first();
    }
}
