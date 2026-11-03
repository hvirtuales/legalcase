<?php

namespace Components\Pqrsf\Models;

use CodeIgniter\Model;

/**
 * Los tipos de solicitud, por empresa.
 *
 * Antes eran una constante con sus cinco plazos incrustados en la configuración.
 * Ahora son datos: cada empresa puede renombrarlos, cambiar el plazo, ordenarlos,
 * desactivar los que no usa y **añadir los suyos**.
 *
 * La solicitud guarda el **código**, no el id: si un tipo se borra o se renombra,
 * lo ya radicado no cambia (y `mapFor()` cae al código como último recurso).
 */
class TypeModel extends Model
{
    /** Los cinco de siempre: [código, clave i18n, días]. */
    public const DEFAULTS = [
        ['peticion', 'Pqr.type_peticion', 15],
        ['queja', 'Pqr.type_queja', 15],
        ['reclamo', 'Pqr.type_reclamo', 15],
        ['sugerencia', 'Pqr.type_sugerencia', 30],
        ['felicitacion', 'Pqr.type_felicitacion', 30],
    ];

    protected $table            = 'cmp_pqr_types';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['company_id', 'code', 'name', 'days', 'sort_order', 'is_active'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'code'       => 'required|min_length[2]|max_length[20]|alpha_dash',
        'name'       => 'required|min_length[2]|max_length[60]',
        'days'       => 'required|is_natural_no_zero|less_than_equal_to[365]',
    ];

    protected $validationMessages = [
        'code' => [
            'required'   => 'El código es obligatorio.',
            'alpha_dash' => 'El código solo admite letras, números, guion y guion bajo.',
        ],
        'name' => ['required' => 'El nombre del tipo es obligatorio.'],
        'days' => ['required' => 'Indica en cuántos días se responde este tipo.'],
    ];

    /**
     * Tipos de la empresa, sembrando los cinco de siempre la primera vez.
     *
     * La siembra perezosa evita que una empresa creada después de instalar el
     * componente se quede sin tipos y con el formulario público vacío.
     */
    public function forCompany(int $companyId): array
    {
        $rows = $this->ordered($companyId);
        if ($rows) {
            return $rows;
        }

        $this->seed($companyId);

        return $this->ordered($companyId);
    }

    /** Siembra los cinco por defecto (no pisa los que ya existan). */
    public function seed(int $companyId): int
    {
        $have = array_column($this->where('company_id', $companyId)->findAll(), 'code');
        $made = 0;

        foreach (self::DEFAULTS as $i => [$code, $key, $days]) {
            if (in_array($code, $have, true)) {
                continue;
            }
            $ok = $this->insert([
                'company_id' => $companyId,
                'code'       => $code,
                'name'       => lang($key),
                'days'       => $days,
                'sort_order' => $i + 1,
                'is_active'  => 1,
            ]);
            $made += $ok ? 1 : 0;
        }

        return $made;
    }

    private function ordered(int $companyId): array
    {
        return $this->where('company_id', $companyId)
            ->orderBy('sort_order', 'asc')->orderBy('id', 'asc')
            ->findAll();
    }

    /** Solo los que se ofrecen hoy en el formulario público. */
    public function activeFor(int $companyId): array
    {
        return array_values(array_filter($this->forCompany($companyId), static fn ($t) => !empty($t['is_active'])));
    }

    /**
     * `[código => nombre]` de todos (activos o no), para poder mostrar el tipo de
     * una solicitud antigua aunque el tipo ya no se ofrezca.
     */
    public function mapFor(int $companyId): array
    {
        return array_column($this->forCompany($companyId), 'name', 'code');
    }

    /** Nombre legible del tipo; si desapareció, el propio código. */
    public function labelFor(int $companyId, string $code): string
    {
        return $this->mapFor($companyId)[$code] ?? $code;
    }

    /** Días de respuesta del tipo (15 si el tipo ya no existe). */
    public function daysFor(int $companyId, string $code): int
    {
        foreach ($this->forCompany($companyId) as $t) {
            if ($t['code'] === $code) {
                return max(1, (int) $t['days']);
            }
        }

        return 15;
    }

    /** ¿La empresa ofrece este código ahora mismo? */
    public function isValid(int $companyId, string $code): bool
    {
        foreach ($this->activeFor($companyId) as $t) {
            if ($t['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    /** Tipo de la empresa o null: el permiso no basta, hay que ser dueño. */
    public function own(int $companyId, int $id): ?array
    {
        return $this->where('company_id', $companyId)->find($id) ?: null;
    }

    /** ¿Otro tipo de la empresa ya usa este código? */
    public function codeTaken(int $companyId, string $code, ?int $exceptId = null): bool
    {
        $b = $this->where('company_id', $companyId)->where('code', $code);
        if ($exceptId) {
            $b->where('id !=', $exceptId);
        }

        return $b->countAllResults() > 0;
    }

    /** Cuántas solicitudes usan cada código (para no borrar a ciegas). */
    public function usageFor(int $companyId): array
    {
        $rows = $this->db->table('cmp_pqr_requests')
            ->select('type, COUNT(*) AS total')
            ->where('company_id', $companyId)
            ->where('deleted_at IS NULL')
            ->groupBy('type')
            ->get()->getResultArray();

        return array_column($rows, 'total', 'type');
    }
}
