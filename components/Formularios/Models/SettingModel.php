<?php

namespace Components\Formularios\Models;

use CodeIgniter\Model;

/** Configuración del componente por empresa (una fila). */
class SettingModel extends Model
{
    protected $table            = 'cmp_frm_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'notify_email', 'captcha_enabled', 'require_consent',
        'policy_text', 'policy_version', 'retention_days',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    /**
     * La configuración de la empresa, creándola con los valores por defecto la
     * primera vez (auto-siembra perezosa, como los tipos de PQRSF).
     */
    public function forCompany(int $companyId): array
    {
        $row = $this->where('company_id', $companyId)->first();
        if ($row) {
            return $row;
        }

        $this->insert([
            'company_id'      => $companyId,
            'notify_email'    => '',
            'captcha_enabled' => 1,
            'require_consent' => 1,
            'policy_text'     => '',
            'policy_version'  => '1.0',
            'retention_days'  => 0,
        ]);

        return $this->where('company_id', $companyId)->first() ?? [];
    }
}
