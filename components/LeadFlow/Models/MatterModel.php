<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * El expediente: el caso ya contratado, dentro de la plataforma.
 *
 * Uno por lead (`UNIQUE(lead_id)`), como `cmp_lfl_conversions` y `cmp_lfl_reviews`.
 * Un segundo trámite del mismo cliente es un segundo lead, y así la atribución y el
 * scoring siguen contando una venta por intención.
 *
 * ── El `status` no se escribe a mano ─────────────────────────────────────────
 *
 * Sale del `kind` de la etapa procesal vía `MatterStageModel::statusFor()`, igual que
 * `leads.status` sale del kind de la etapa comercial. Está en `allowedFields` porque
 * el servicio lo escribe, pero **el servicio es el único que lo calcula**.
 *
 * ── Los rollups son caché, no verdad ─────────────────────────────────────────
 *
 * `fee_total`, `paid_total`, `docs_required` y `docs_received` los recalcula
 * `MatterService::refreshTotals()` / `refreshChecklist()` desde las filas hijas. Están
 * desnormalizados para que el listado no haga cuatro subconsultas por fila. **El
 * saldo no se guarda**: una columna derivada de dos columnas derivadas es la que se
 * queda desfasada sin que nadie lo note.
 *
 * ── Ojo con los nombres de método ────────────────────────────────────────────
 *
 * `CodeIgniter\Model` ya declara `find()`, `set()`, `insert()` y `update()`: una firma
 * distinta rompe la clase al cargarla. Por eso «buscar el expediente de un lead» es
 * `ofLead()` y no `find()`, igual que `CaseStatusModel::lookup()` y
 * `PlatformModel::value()`.
 */
class MatterModel extends Model
{
    use CompanyScoped;

    /** Derivados del kind de la etapa; nunca se eligen en un formulario. */
    public const STATUSES = ['active', 'on_hold', 'closed', 'cancelled'];

    /**
     * Motivos de cierre. Obligatorio al cerrar, como `lost_reason` al perder un lead.
     *
     * `won` y `denied` son el resultado del trámite; `withdrawn` lo retiró el cliente;
     * `transferred` se fue a otra firma. Separarlos importa porque solo el primero es
     * un caso ganado, y mezclarlos haría ilegible cualquier medición posterior.
     */
    public const CLOSE_REASONS = ['won', 'denied', 'withdrawn', 'transferred', 'other'];

    protected $table          = 'cmp_lfl_matters';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'company_id', 'lead_id', 'number', 'title', 'stage_id', 'stage_entered_at', 'status',
        'opened_at', 'opened_by', 'closed_at', 'close_reason', 'close_note',
        'service_id', 'lawyer_user_id', 'assigned_user_id', 'summary',
        'client_first_name', 'client_last_name', 'client_email', 'client_phone', 'client_whatsapp',
        'client_country', 'client_city', 'service_name', 'matter_type_name', 'lawyer_name', 'currency',
        'fee_total', 'paid_total', 'docs_required', 'docs_received', 'next_deadline_at',
    ];

    protected $validationRules = [
        'company_id'   => 'required|is_natural_no_zero',
        'lead_id'      => 'required|is_natural_no_zero',
        'number'       => 'required|max_length[30]',
        'status'       => 'required|in_list[active,on_hold,closed,cancelled]',
        'close_reason' => 'permit_empty|in_list[won,denied,withdrawn,transferred,other]',
        'title'        => 'permit_empty|max_length[160]',
    ];

    protected $validationMessages = [
        'number' => ['required' => 'Lfl.vMatterNumber'],
    ];

    /**
     * El expediente de un lead, o null.
     *
     * No lleva `company_id` porque `lead_id` ya es único en toda la tabla y el lead del
     * que se parte vino de `LeadService::visible()`, que ya comprobó la empresa. Pedir
     * la empresa otra vez daría una falsa sensación de comprobación en el único sitio
     * donde no hace falta.
     */
    public function ofLead(int $leadId): ?array
    {
        return $leadId > 0 ? $this->where('lead_id', $leadId)->first() : null;
    }

    /** ¿Ese lead ya tiene expediente? Cuenta también los borrados: el UNIQUE no distingue. */
    public function existsForLead(int $leadId): bool
    {
        return $leadId > 0 && $this->withDeleted()->where('lead_id', $leadId)->countAllResults() > 0;
    }

    /** Cuántos expedientes vivos tiene la firma por `status`. */
    public function countsByStatus(int $companyId): array
    {
        $out = array_fill_keys(self::STATUSES, 0);
        $rows = $this->select('status, COUNT(*) AS n')
            ->where('company_id', $companyId)
            ->groupBy('status')->findAll();
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }

        return $out;
    }

    /** El nombre del cliente tal como quedó congelado al abrir. */
    public static function clientName(array $matter): string
    {
        $n = trim(($matter['client_first_name'] ?? '') . ' ' . ($matter['client_last_name'] ?? ''));

        return $n !== '' ? $n : '—';
    }

    /** Lo pactado menos lo cobrado. Se calcula, no se guarda. */
    public static function balance(array $matter): float
    {
        return round((float) ($matter['fee_total'] ?? 0) - (float) ($matter['paid_total'] ?? 0), 2);
    }

    /** ¿Está cerrado o cancelado? Lo usan las guardas del servicio y las vistas. */
    public static function isClosed(array $matter): bool
    {
        return in_array((string) ($matter['status'] ?? ''), ['closed', 'cancelled'], true);
    }
}
