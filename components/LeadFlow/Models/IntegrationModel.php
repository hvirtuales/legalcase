<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Libraries\Crypto;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * La conexión de una firma con su cuenta de Docketwise. Los tokens se guardan
 * **cifrados** (§20, §31) y nunca salen hacia una vista.
 */
class IntegrationModel extends Model
{
    use CompanyScoped;

    public const STATUSES = ['disconnected', 'pending', 'connected', 'error'];

    protected $table         = 'cmp_lfl_integrations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'provider', 'mode', 'status', 'access_token', 'refresh_token', 'expires_at',
        'oauth_state', 'connected_at', 'connected_by', 'contacts_synced_at', 'last_error'];

    /** La fila de la firma (se crea desconectada si no existe). */
    public function of(int $companyId, string $provider): array
    {
        $row = $this->where('company_id', $companyId)->where('provider', $provider)->first();
        if ($row) {
            return $row;
        }
        $this->insert(['company_id' => $companyId, 'provider' => $provider, 'status' => 'disconnected', 'mode' => 'simulated']);

        return $this->where('company_id', $companyId)->where('provider', $provider)->first();
    }

    public function isConnected(int $companyId, string $provider): bool
    {
        $row = $this->where('company_id', $companyId)->where('provider', $provider)->first();

        return $row && $row['status'] === 'connected';
    }

    public function storeTokens(int $id, array $tokens): void
    {
        $expires = isset($tokens['expires_in']) ? date('Y-m-d H:i:s', time() + (int) $tokens['expires_in']) : null;

        $this->update($id, [
            'access_token'  => Crypto::enc((string) ($tokens['access_token'] ?? '')),
            'refresh_token' => Crypto::enc((string) ($tokens['refresh_token'] ?? '')) ?? null,
            'expires_at'    => $expires,
        ]);
    }

    public static function accessToken(array $row): ?string
    {
        return Crypto::dec($row['access_token'] ?? null);
    }

    public static function refreshToken(array $row): ?string
    {
        return Crypto::dec($row['refresh_token'] ?? null);
    }
}
