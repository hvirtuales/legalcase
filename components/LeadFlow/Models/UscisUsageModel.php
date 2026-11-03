<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;

/**
 * Cuántas llamadas a USCIS lleva hoy cada firma.
 *
 * **Cuenta llamadas de red, no consultas de usuario.** Un acierto de caché no gasta
 * cupo y no se cuenta: confundir las dos cosas daría un consumo inflado y cortaría el
 * servicio mucho antes de tiempo.
 *
 * El corte propio está por DEBAJO del de USCIS a propósito. Si dejáramos que cortara
 * USCIS, la firma se enteraría con un 429 en la cara de un cliente; cortando antes, la
 * última parte del cupo queda de reserva y el visitante recibe el último estado
 * conocido con su fecha en vez de un error.
 */
class UscisUsageModel extends Model
{
    protected $table         = 'cmp_lfl_uscis_usage';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'day', 'calls'];

    /** El tope real de USCIS. */
    public const HARD_LIMIT = 1000;

    /**
     * El tope propio: 900.
     *
     * Las 100 de margen no son miedo, son para que una firma que agota el cupo con su
     * tráfico público conserve con qué atender lo de dentro —la precalificación de un
     * lead, por ejemplo— el resto del día.
     */
    public const DAILY_LIMIT = 900;

    public function used(int $companyId): int
    {
        $row = $this->where('company_id', $companyId)->where('day', date('Y-m-d'))->first();

        return (int) ($row['calls'] ?? 0);
    }

    public function left(int $companyId): int
    {
        return max(0, self::DAILY_LIMIT - $this->used($companyId));
    }

    /**
     * Apunta una llamada.
     *
     * Se incrementa con `SET calls = calls + 1` y no leyendo y escribiendo: dos
     * consultas a la vez leerían el mismo valor y una de las dos se perdería, que es
     * exactamente como un contador de cupo deja de servir.
     */
    public function record(int $companyId): void
    {
        $hoy = date('Y-m-d');
        $row = $this->where('company_id', $companyId)->where('day', $hoy)->first();

        if ($row) {
            $this->db->table($this->table)->where('id', (int) $row['id'])
                ->set('calls', 'calls + 1', false)
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->update();

            return;
        }

        $this->insert(['company_id' => $companyId, 'day' => $hoy, 'calls' => 1]);
    }
}
