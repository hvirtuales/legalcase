<?php

namespace Components\Formularios\Models;

use CodeIgniter\Model;

/**
 * Respuestas recibidas.
 *
 * `data` (idCampo => valor) y `labels` (idCampo => etiqueta congelada) van como
 * array en el código y JSON en TEXT.
 */
class ResponseModel extends Model
{
    public const STATUSES = ['partial', 'complete'];

    use JsonColumns;

    protected $table            = 'cmp_frm_responses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'form_id', 'company_id', 'token', 'reference', 'form_version', 'status',
        'data', 'labels', 'user_id', 'ip', 'user_agent', 'device',
        'duration_seconds', 'is_read',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected array $jsonColumns = ['data', 'labels'];

    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
    protected $afterFind    = ['decodeJson'];

    public function byToken(string $token): ?array
    {
        $token = trim($token);

        return $token === '' ? null : $this->where('token', $token)->first();
    }

    /** Cuántas respuestas completas lleva el formulario (para el cupo y el cierre). */
    public function countFor(int $formId): int
    {
        return $this->where('form_id', $formId)->countAllResults();
    }

    /**
     * Siguiente consecutivo del formulario, dentro de una transacción ya abierta.
     *
     * La app original leía el último número sin reservarlo, así que dos personas
     * respondiendo a la vez se llevaban el mismo. Aquí se cuenta con la fila
     * bloqueada por la transacción de `ResponseService::submit()`.
     */
    public function nextReference(int $formId): string
    {
        // Se toma el MAXIMO del propio radicado y no el del `id`: el id es el
        // autoincremento GLOBAL de la tabla, así que el primer radicado de un
        // formulario salía con el número de todas las respuestas de todos los
        // formularios (FRM-000027 en la primera respuesta). El máximo de la
        // cadena vale porque el número va rellenado a seis dígitos con ceros,
        // y ordenar así es portable en los tres motores.
        //
        // No se filtran las borradas a propósito: un radicado dado no se
        // reutiliza, aunque su respuesta se haya eliminado.
        $row = $this->db->table($this->table)
            ->selectMax('reference', 'last')
            ->where('form_id', $formId)
            ->get()->getRowArray();

        $last = (string) ($row['last'] ?? '');
        $next = $last !== '' ? ((int) substr($last, 4)) + 1 : 1;

        return 'FRM-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
