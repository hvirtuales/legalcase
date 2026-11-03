<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Los pagos recibidos de un expediente.
 *
 * Tabla y no una columna «cobrado» porque un caso se paga a plazos: guardar solo el
 * total pierde cuándo, cómo y con qué referencia, que es justo lo que hay que poder
 * enseñar cuando un cliente dice que ya pagó.
 *
 * `fee_id` es opcional a propósito: un abono a cuenta no corresponde a ninguna línea
 * concreta, y obligar a elegir una haría que se imputara a la primera que hubiera.
 */
class MatterPaymentModel extends Model
{
    use CompanyScoped;

    public const METHODS = ['cash', 'transfer', 'card', 'check', 'other'];

    protected $table          = 'cmp_lfl_matter_payments';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'company_id', 'matter_id', 'fee_id', 'amount', 'currency', 'paid_at',
        'method', 'reference', 'note', 'recorded_by',
    ];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'matter_id'  => 'required|is_natural_no_zero',
        'amount'     => 'required|decimal|greater_than[0]',
        'method'     => 'required|in_list[cash,transfer,card,check,other]',
    ];

    protected $validationMessages = [
        'amount' => ['required' => 'Lfl.vPaymentAmount', 'greater_than' => 'Lfl.vPaymentAmount'],
    ];

    public function forMatter(int $matterId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('matter_id', $matterId)
            ->orderBy('paid_at', 'desc')->orderBy('id', 'desc')->findAll();
    }

    /**
     * Lo cobrado, en total. Lo usa `MatterService::refreshTotals()`.
     *
     * El `where('deleted_at', null)` es explícito: `get()` va directo al builder y se
     * salta el filtro de soft-delete que CI4 sí aplica en `find()`/`findAll()`. Sin
     * él, anular un pago no bajaría lo cobrado y el saldo mentiría a favor de la firma.
     */
    public function totalFor(int $matterId, int $companyId): float
    {
        $r = $this->selectSum('amount', 'total')->forCompany($companyId)
            ->where('matter_id', $matterId)
            ->where($this->table . '.deleted_at', null)
            ->get()->getRowArray();

        return round((float) ($r['total'] ?? 0), 2);
    }
}
