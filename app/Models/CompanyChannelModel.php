<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Canales de comunicación por empresa (1 fila por `company_id`) — capacidad de
 * NÚCLEO (la usan todos los componentes). Cifra en reposo los secretos
 * (`smtp_pass`, `wa_token`, `sms_auth_token`) con el `encrypter` del núcleo y un
 * marcador `enc:v1:` (convive con texto plano heredado).
 */
class CompanyChannelModel extends Model
{
    protected $table         = 'company_channels';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $allowedFields = [
        'company_id', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_sender_name', 'smtp_sender_email', 'smtp_security',
        'wa_provider', 'wa_endpoint', 'wa_phone_id', 'wa_token',
        'sms_provider', 'sms_endpoint', 'sms_account_sid', 'sms_auth_token', 'sms_sender_id',
    ];

    public const SECRETS = ['smtp_pass', 'wa_token', 'sms_auth_token'];
    private const MARK    = 'enc:v1:';

    private function enc(?string $v): ?string
    {
        if ($v === null || $v === '' || strncmp($v, self::MARK, strlen(self::MARK)) === 0) {
            return $v;
        }

        return self::MARK . base64_encode(service('encrypter')->encrypt($v));
    }

    private function dec(?string $v): ?string
    {
        if ($v === null || $v === '' || strncmp($v, self::MARK, strlen(self::MARK)) !== 0) {
            return $v;
        }
        try {
            return service('encrypter')->decrypt(base64_decode(substr($v, strlen(self::MARK))));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Config de la empresa con secretos DESCIFRADOS (uso interno: test/envío). */
    public function getForCompany(int $companyId): array
    {
        $row = $this->where('company_id', $companyId)->first() ?: [];
        foreach (self::SECRETS as $k) {
            if (isset($row[$k])) {
                $row[$k] = $this->dec($row[$k]);
            }
        }

        return $row;
    }

    /** Upsert por empresa. Los secretos vacíos NO se sobrescriben (se conservan). */
    public function saveForCompany(int $companyId, array $data): void
    {
        $existing = $this->where('company_id', $companyId)->first();
        foreach (self::SECRETS as $k) {
            if (($data[$k] ?? '') === '') {
                unset($data[$k]);
            } else {
                $data[$k] = $this->enc($data[$k]);
            }
        }
        $data['company_id'] = $companyId;
        if ($existing) {
            $this->update($existing['id'], $data);
        } else {
            $this->insert($data);
        }
    }
}
