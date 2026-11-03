<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;

/**
 * Denuncias sobre una firma o un abogado del directorio.
 *
 * **Nunca se publican.** Este modelo no tiene —ni debe tener— un método que devuelva
 * denuncias para una vista pública: la única salida es la bandeja de la plataforma.
 * Si algún día hiciera falta enseñarlas, sería una decisión de producto que hay que
 * tomar a la vista, no un método que aparece sin que nadie lo note.
 *
 * Tampoco entran en ninguna media: la calificación la calcula `ReviewModel` sobre
 * `cmp_lfl_reviews`, que es otra tabla. Esa separación es lo que impide que denunciar
 * sirva para bajarle la nota a nadie.
 */
class ComplaintModel extends Model
{
    protected $table          = 'cmp_lfl_complaints';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'company_id', 'profile_id', 'category', 'reason', 'contact',
        'status', 'ip_hash', 'handled_at', 'handled_by', 'handled_note',
    ];

    /** Pendiente de revisar, atendida, o descartada por no proceder. */
    public const PENDING   = 'pending';
    public const HANDLED   = 'handled';
    public const DISMISSED = 'dismissed';
    public const STATUSES  = [self::PENDING, self::HANDLED, self::DISMISSED];

    /**
     * Las categorías, cerradas.
     *
     * Son las que la plataforma puede hacer algo con ellas. «Otro» existe porque una
     * lista cerrada sin salida obliga a encajar a la fuerza lo que no encaja, y
     * entonces la categoría deja de significar nada.
     */
    public const CATEGORIES = ['impersonation', 'misleading', 'credentials', 'privacy', 'conduct', 'other'];

    /** Mínimo del motivo: una denuncia sin explicar no le sirve a nadie. */
    public const MIN_REASON = 20;
    public const MAX_REASON = 2000;

    public static function categoryOf($value): string
    {
        $v = trim((string) $value);

        return in_array($v, self::CATEGORIES, true) ? $v : 'other';
    }

    /**
     * La huella de quien denuncia.
     *
     * Se guarda el hash y nunca la IP: sirve igual para el tope por firma —solo hay
     * que compararla consigo misma— y no deja almacenado un dato personal de alguien
     * que puede estar denunciando a su propio abogado. La sal sale de la clave de
     * cifrado de la instalación.
     */
    public static function hashIp(?string $ip): string
    {
        $sal = (string) (config('Encryption')->key ?? 'lfl');

        return hash('sha256', $sal . '|' . (string) $ip);
    }

    /**
     * ¿Esta huella ya denunció a esta firma?
     *
     * Cuenta **también lo descartado**: si solo mirara lo pendiente, quien viera su
     * denuncia descartada podría volver a mandarla tantas veces como quisiera, y la
     * bandeja de la plataforma sería el ataque.
     */
    public function already(int $companyId, string $ipHash): bool
    {
        return $this->withDeleted()
            ->where('company_id', $companyId)->where('ip_hash', $ipHash)
            ->countAllResults() > 0;
    }

    /** La bandeja de la plataforma: lo pendiente primero, y dentro lo más reciente. */
    public function tray(int $limit = 200): array
    {
        return $this->orderBy("CASE WHEN status = 'pending' THEN 0 ELSE 1 END", '', false)
            ->orderBy('created_at', 'desc')->orderBy('id', 'desc')
            ->findAll($limit);
    }

    public function countPending(): int
    {
        return $this->where('status', self::PENDING)->countAllResults();
    }
}
