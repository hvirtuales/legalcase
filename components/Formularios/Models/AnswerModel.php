<?php

namespace Components\Formularios\Models;

use CodeIgniter\Model;

/**
 * Índice de respuestas por campo. Derivado y desechable: se reconstruye desde
 * `cmp_frm_responses.data`, que es la fuente de verdad.
 *
 * Sin soft-delete ni `updated_at`: una fila de índice no se edita, se rehace.
 */
class AnswerModel extends Model
{
    protected $table            = 'cmp_frm_answers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'response_id', 'form_id', 'company_id', 'field_id', 'field_type',
        'option_value', 'value_text', 'value_num', 'value_date', 'created_at',
    ];

    /** La tabla no tiene `updated_at`, y CI4 exige ambos campos o ninguno. */
    protected $useTimestamps = false;

    /** Rehace el índice de una respuesta: borra lo suyo y vuelve a insertar. */
    public function replaceFor(int $responseId, array $rows): void
    {
        $this->where('response_id', $responseId)->delete();
        if ($rows) {
            $this->insertBatch($rows);
        }
    }

    /**
     * Conteo por opción de un campo: la agregación que hace portable el informe.
     *
     * @return array<string,int> valor => veces
     */
    public function countByOption(int $formId, string $fieldId): array
    {
        $rows = $this->db->table($this->table)
            ->select('option_value, COUNT(*) AS total')
            ->where('form_id', $formId)
            ->where('field_id', $fieldId)
            ->where('option_value IS NOT NULL')
            ->groupBy('option_value')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['option_value']] = (int) $r['total'];
        }

        return $out;
    }

    /** @return array{count:int,avg:float,min:float,max:float} */
    public function numericStats(int $formId, string $fieldId): array
    {
        $row = $this->db->table($this->table)
            ->select('COUNT(value_num) AS n, AVG(value_num) AS avg_v, MIN(value_num) AS min_v, MAX(value_num) AS max_v')
            ->where('form_id', $formId)
            ->where('field_id', $fieldId)
            ->where('value_num IS NOT NULL')
            ->get()->getRowArray();

        return [
            'count' => (int) ($row['n'] ?? 0),
            'avg'   => round((float) ($row['avg_v'] ?? 0), 2),
            'min'   => (float) ($row['min_v'] ?? 0),
            'max'   => (float) ($row['max_v'] ?? 0),
        ];
    }

    /** Últimos textos escritos en un campo abierto. */
    public function latestTexts(int $formId, string $fieldId, int $limit = 20): array
    {
        return $this->db->table($this->table)
            ->select('value_text, created_at')
            ->where('form_id', $formId)
            ->where('field_id', $fieldId)
            ->where('value_text IS NOT NULL')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()->getResultArray();
    }
}
