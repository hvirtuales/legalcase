<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * El checklist de UN expediente: la copia congelada de la plantilla, con estado.
 *
 * ── Los cinco estados y por qué ──────────────────────────────────────────────
 *
 * `pending` no ha llegado · `received` llegó y falta revisarlo · `approved` revisado y
 * vale · `rejected` llegó pero no sirve (y la nota dice por qué) · `waived` el abogado
 * decide que en este caso no hace falta.
 *
 * `waived` es el que justifica que esto sea una tabla y no un cálculo: no se puede
 * deducir cruzando documentos con la plantilla, porque es una **decisión** sobre este
 * expediente. Sin él, un requisito eximido se quedaría contando como pendiente para
 * siempre y el «faltan 3 de 11» nunca llegaría a cero.
 *
 * `rejected` cuenta como pendiente a efectos de «cuántos faltan», que es lo correcto:
 * un pasaporte vencido no es un pasaporte entregado.
 */
class MatterRequirementModel extends Model
{
    use CompanyScoped;

    public const STATUSES = ['pending', 'received', 'approved', 'rejected', 'waived'];

    /** Los que ya no se esperan: cuentan como resueltos. */
    public const SETTLED = ['approved', 'waived'];

    protected $table          = 'cmp_lfl_matter_requirements';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'company_id', 'matter_id', 'requirement_id', 'label', 'document_type_id',
        'is_required', 'applies_to', 'sort', 'status', 'document_id', 'due_at',
        'received_at', 'reviewed_at', 'reviewed_by', 'note',
    ];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'matter_id'  => 'required|is_natural_no_zero',
        'label'      => 'required|max_length[120]',
        'status'     => 'required|in_list[pending,received,approved,rejected,waived]',
        'applies_to' => 'required|in_list[client,spouse,dependent,employer,other]',
    ];

    protected $validationMessages = [
        'label' => ['required' => 'Lfl.vRequirementLabel'],
    ];

    /** El checklist del expediente, en su orden. */
    public function forMatter(int $matterId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('matter_id', $matterId)
            ->orderBy('sort', 'asc')->orderBy('id', 'asc')->findAll();
    }

    /**
     * Cuántos obligatorios hay y cuántos están resueltos.
     *
     * Solo cuenta los **obligatorios**: un opcional que falta no es una carencia, y
     * meterlo en el contador haría que ningún expediente llegara nunca a estar completo.
     *
     * @return array{required:int, settled:int}
     */
    public function progress(int $matterId, int $companyId): array
    {
        $filas = $this->forCompany($companyId)->where('matter_id', $matterId)
            ->where('is_required', 1)->findAll();

        $resueltos = 0;
        foreach ($filas as $f) {
            if (in_array((string) $f['status'], self::SETTLED, true)) {
                $resueltos++;
            }
        }

        return ['required' => count($filas), 'settled' => $resueltos];
    }

    /** Los obligatorios que siguen sin resolverse, para decir cuáles faltan. */
    public function missing(int $matterId, int $companyId): array
    {
        return $this->forCompany($companyId)->where('matter_id', $matterId)
            ->where('is_required', 1)
            ->whereNotIn('status', self::SETTLED)
            ->orderBy('sort', 'asc')->findAll();
    }

    /**
     * Requisitos con plazo vencido o próximo, de toda la firma.
     *
     * Alimenta la bandeja de plazos: es la otra mitad de guardar una fecha de
     * vencimiento, igual que en Copropiedad.
     */
    public function dueSoon(int $companyId, int $days = 15): array
    {
        return $this->forCompany($companyId)
            ->where('due_at IS NOT NULL')
            ->where('due_at <=', date('Y-m-d H:i:s', strtotime('+' . $days . ' days')))
            ->whereNotIn('status', self::SETTLED)
            ->orderBy('due_at', 'asc')->findAll(200);
    }
}
