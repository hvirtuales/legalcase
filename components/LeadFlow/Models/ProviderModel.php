<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Libraries\Crypto;

/**
 * La app OAuth de la plataforma ante cada proveedor. **Global a propósito**: una
 * sola app registrada ante Docketwise para toda la plataforma (la configura el
 * Super Admin); cada firma autoriza su propia cuenta en `cmp_lfl_integrations`.
 */
class ProviderModel extends Model
{
    public const MODES = ['simulated', 'live'];

    protected $table         = 'cmp_lfl_providers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['provider', 'client_id', 'client_secret', 'redirect_uri', 'scope', 'mode'];

    /** La configuración del proveedor, con el secreto ya descifrado en `secret`. */
    public function config(string $provider): array
    {
        $row = $this->where('provider', $provider)->first() ?? [
            'provider' => $provider, 'client_id' => null, 'client_secret' => null,
            'redirect_uri' => null, 'scope' => 'public write', 'mode' => 'simulated',
        ];
        $row['secret'] = Crypto::dec($row['client_secret'] ?? null);
        $row['mode']   = in_array($row['mode'] ?? '', self::MODES, true) ? $row['mode'] : 'simulated';

        return $row;
    }

    /** Guarda. Un secreto vacío CONSERVA el anterior (el formulario nunca lo muestra). */
    public function saveConfig(string $provider, array $data): bool
    {
        $row = $this->where('provider', $provider)->first();

        $save = [
            'provider'     => $provider,
            'client_id'    => $data['client_id'] ?? null,
            'redirect_uri' => $data['redirect_uri'] ?? null,
            'scope'        => $data['scope'] ?: 'public write',
            'mode'         => in_array($data['mode'] ?? '', self::MODES, true) ? $data['mode'] : 'simulated',
        ];
        if (($data['secret'] ?? '') !== '') {
            $save['client_secret'] = Crypto::enc($data['secret']);
        }

        return $row ? $this->update((int) $row['id'], $save) : (bool) $this->insert($save);
    }
}
