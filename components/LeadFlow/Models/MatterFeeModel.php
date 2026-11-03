<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Las líneas de lo pactado en un expediente.
 *
 * `kind` separa lo que es ingreso de la firma (`fee`) de lo que no lo es: `filing` son
 * las tasas oficiales que el cliente paga al gobierno y que solo pasan por la firma,
 * `third_party` lo que cobra un tercero (traductor, médico) y `expense` los gastos
 * repercutidos. Sin esa distinción, «3.500 USD» se leería como honorarios cuando 1.200
 * son del gobierno, y el estado de cuenta estaría mintiendo sobre quién cobra qué.
 *
 * **No es facturación.** Aquí no hay impuestos, ni retenciones, ni consecutivo fiscal,
 * ni asiento: eso es el componente Contabilidad, y este no lo toca ni por FK.
 */
class MatterFeeModel extends Model
{
    use CompanyScoped;

    public const KINDS = ['fee', 'filing', 'third_party', 'expense'];

    /** El único que es ingreso de la firma. */
    public const INCOME = 'fee';

    protected $table          = 'cmp_lfl_matter_fees';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'company_id', 'matter_id', 'kind', 'concept', 'amount', 'currency',
        'due_at', 'sort', 'note',
    ];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'matter_id'  => 'required|is_natural_no_zero',
        'kind'       => 'required|in_list[fee,filing,third_party,expense]',
        'concept'    => 'required|max_length[160]',
        'amount'     => 'required|decimal|greater_than_equal_to[0]',
    ];

    protected $validationMessages = [
        'concept' => ['required' => 'Lfl.vFeeConcept'],
        'amount'  => ['required' => 'Lfl.vFeeAmount'],
    ];

    public function forMatter(int $matterId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('matter_id', $matterId)
            ->orderBy('sort', 'asc')->orderBy('id', 'asc')->findAll();
    }

    /**
     * Lo pactado, en total. Lo usa `MatterService::refreshTotals()`.
     *
     * **El `where('deleted_at', null)` es explícito y no sobra.** CI4 aplica el
     * filtro de soft-delete en `find()`/`findAll()`, pero `get()` va directo al
     * builder y se lo salta: sin esto, una línea borrada seguiría sumando y el total
     * del expediente no bajaría nunca.
     */
    public function totalFor(int $matterId, int $companyId): float
    {
        $r = $this->selectSum('amount', 'total')->forCompany($companyId)
            ->where('matter_id', $matterId)
            ->where($this->table . '.deleted_at', null)
            ->get()->getRowArray();

        return round((float) ($r['total'] ?? 0), 2);
    }

    /** Lo pactado, desglosado por tipo: es lo que se le enseña al cliente. */
    public function byKind(int $matterId, int $companyId): array
    {
        $out = array_fill_keys(self::KINDS, 0.0);
        foreach ($this->select('kind, SUM(amount) AS total')->forCompany($companyId)
            ->where('matter_id', $matterId)->groupBy('kind')->findAll() as $r) {
            $out[(string) $r['kind']] = round((float) $r['total'], 2);
        }

        return $out;
    }
}
