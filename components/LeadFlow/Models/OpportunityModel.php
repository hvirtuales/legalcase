<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;

/**
 * Una solicitud de orientación que todavía no tiene dueño.
 *
 * **Global a propósito** (ver la migración `CreateLflOpportunityTables`): la oportunidad
 * no es de ninguna firma hasta que alguien la reclama. Lo que aísla es el reclamo.
 *
 * El modelo **no expone los datos de contacto por descuido**: `forBoard()` selecciona
 * solo las columnas del caso, y quien quiera el contacto tiene que pedir la fila entera
 * a conciencia. Es la diferencia entre un olvido y una decisión.
 */
class OpportunityModel extends Model
{
    public const STATUSES = ['open', 'claimed', 'closed', 'expired'];

    /** Reclamos que admite una oportunidad: lo que se le promete al prospecto. */
    public const MAX_CLAIMS = 3;

    /** Días que sigue abierta si nadie la reclama. */
    public const DAYS_OPEN = 30;

    protected $table          = 'cmp_lfl_opportunities';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'token', 'area_id', 'jurisdiction_id', 'language', 'city', 'summary',
        'first_name', 'last_name', 'email', 'email_norm', 'phone', 'phone_norm',
        'consent_at', 'consent_ip', 'status', 'claims_count', 'max_claims', 'expires_at',
    ];

    protected $validationRules = [
        'token'      => 'required|max_length[64]',
        'area_id'    => 'required|is_natural_no_zero',
        'first_name' => 'required|max_length[80]',
        'last_name'  => 'required|max_length[80]',
        'status'     => 'required|in_list[open,claimed,closed,expired]',
    ];
    protected $validationMessages = [
        'first_name' => ['required' => 'Lfl.vNamesRequired'],
        'last_name'  => ['required' => 'Lfl.vNamesRequired'],
    ];

    /** Las columnas del CASO, sin una sola del contacto. */
    private const BOARD_FIELDS = 'cmp_lfl_opportunities.id, cmp_lfl_opportunities.area_id,
        cmp_lfl_opportunities.jurisdiction_id, cmp_lfl_opportunities.language, cmp_lfl_opportunities.city,
        cmp_lfl_opportunities.summary, cmp_lfl_opportunities.status, cmp_lfl_opportunities.claims_count,
        cmp_lfl_opportunities.max_claims, cmp_lfl_opportunities.created_at, cmp_lfl_opportunities.expires_at';

    /** Consulta base de la bandeja: solo el caso, nunca la persona. */
    public function forBoard()
    {
        return $this->select(self::BOARD_FIELDS);
    }

    public function byToken(string $token): ?array
    {
        $token = trim($token);

        return $token === '' ? null : $this->where('token', $token)->first();
    }

    /** ¿Sigue admitiendo reclamos? */
    public static function isOpen(array $row): bool
    {
        return in_array($row['status'], ['open', 'claimed'], true)
            && (int) $row['claims_count'] < (int) $row['max_claims']
            && (empty($row['expires_at']) || strtotime((string) $row['expires_at']) > time());
    }
}
