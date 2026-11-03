<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\BelongsToUnit;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Autorizaciones especiales.
 *
 * **«Vencida» se calcula, «revocada» se decide.** Por eso `expireOverdue()` solo
 * mueve `vigente → vencida` y jamás toca una revocada: si el calendario pudiera
 * pisar una revocación, una autorización quitada a mano volvería a leerse como
 * simplemente caducada, que es una historia distinta ante una reclamación.
 */
class AuthorizationModel extends Model
{
    use BelongsToUnit;
    use CompanyScoped;

    public const KINDS = [
        'ingreso', 'recogida_menor', 'visitante_frecuente', 'mudanza',
        'remodelacion', 'mascota', 'parqueadero', 'domicilio', 'otro',
    ];

    public const STATUSES = ['vigente', 'vencida', 'revocada'];

    protected $table            = 'cmp_cop_authorizations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'unit_id', 'kind', 'person_id', 'authorized_name',
        'authorized_doc', 'authorized_phone', 'granted_by_id',
        'start_date', 'end_date', 'status', 'details',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'unit_id'    => 'required|is_natural_no_zero',
        'kind'       => 'required|in_list[ingreso,recogida_menor,visitante_frecuente,mudanza,remodelacion,mascota,parqueadero,domicilio,otro]',
        'status'     => 'required|in_list[vigente,vencida,revocada]',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'unit_id' => ['required' => lang('Cop.vNeedUnit')],
            'kind'    => ['in_list' => lang('Cop.vAuthKind')],
            'status'  => ['in_list' => lang('Cop.vAuthStatus')],
        ];
    }

    /** Hay que autorizar a alguien: o del censo, o con nombre y apellidos. */
    public static function hasSubject(array $data): bool
    {
        return ! empty($data['person_id']) || trim((string) ($data['authorized_name'] ?? '')) !== '';
    }

    /**
     * Cierra por calendario las que ya pasaron de fecha.
     *
     * Se hace al entrar al listado y al panel, no con una tarea programada: un
     * componente drop-in no puede dar por hecho que la instalación tenga cron, y el
     * coste es un `UPDATE` sobre un índice.
     */
    public function expireOverdue(int $companyId): int
    {
        $today = date('Y-m-d');

        $this->builder()
            ->where('company_id', $companyId)
            ->where('status', 'vigente')      // nunca toca una revocada
            ->where('end_date IS NOT NULL')
            ->where('end_date <', $today)
            ->where('deleted_at', null)
            ->update(['status' => 'vencida', 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->db->affectedRows();
    }

    /** Cuántas hay de cada estado (para las tarjetas del listado). */
    public function statusCounts(int $companyId): array
    {
        $rows = $this->select('status, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('deleted_at', null)
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $out = array_fill_keys(self::STATUSES, 0);

        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['total'];
        }

        return $out;
    }

    /** Cómo se llama el autorizado, venga del censo o de fuera. */
    public function subjectName(array $row): string
    {
        if (! empty($row['authorized_name'])) {
            return (string) $row['authorized_name'];
        }
        if (! empty($row['person_id'])) {
            $p = (new PersonModel())->find((int) $row['person_id']);

            return $p ? PersonModel::fullName($p) : '';
        }

        return '';
    }
}
