<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Etapas procesales del expediente.
 *
 * ── Por qué no se reutilizan las del pipeline ────────────────────────────────
 *
 * `cmp_lfl_stages` mide una VENTA y por eso `LeadService::advanceTo()` solo avanza:
 * un lead no vuelve de «propuesta» a «contactado». Un trámite sí retrocede — una
 * solicitud de evidencia adicional te devuelve a recolección documental, y eso no es
 * una corrección, es el curso normal del caso. Mezclar las dos en una tabla obligaría
 * a que el mismo `kind` significara cosas distintas según quién pregunte.
 *
 * ── El `kind` es lo que significa, el nombre es lo que la firma lee ──────────
 *
 * De `kind` sale `matters.status`, igual que `leads.status` sale del kind de la etapa
 * comercial. `filed` está separado de `open` porque radicar es el punto sin vuelta
 * atrás del trámite: antes se prepara, después se espera. `waiting` deja claro que la
 * pelota está en la autoridad y no en la firma, que es lo que hay que poder filtrar
 * cuando alguien pregunta «¿qué casos están parados por mi culpa?».
 */
class MatterStageModel extends Model
{
    use CompanyScoped;

    public const KINDS = ['open', 'filed', 'waiting', 'closed', 'cancelled'];

    /** Los colores que existen de verdad en `app.css`; una variante inventada no pinta. */
    public const COLORS = ['primary', 'info', 'success', 'warning', 'danger', 'secondary', 'light'];

    /**
     * Etapas de sistema: código => [kind, color, días esperados].
     *
     * `sla_days` a null donde el plazo no depende de la firma: nadie puede prometer
     * cuánto tarda USCIS en citar a biométricos, y poner un número inventado haría
     * que la vista de estancados señalara casos que van perfectamente.
     */
    public const SYSTEM = [
        'intake'      => ['open', 'info', 7],
        'contract'    => ['open', 'info', 7],
        'documents'   => ['open', 'primary', 15],
        'preparation' => ['open', 'primary', 15],
        'filed'       => ['filed', 'warning', null],
        'evidence'    => ['waiting', 'warning', 30],
        'biometrics'  => ['waiting', 'warning', null],
        'interview'   => ['waiting', 'warning', null],
        'decision'    => ['open', 'success', null],
        'closed'      => ['closed', 'success', null],
        'withdrawn'   => ['cancelled', 'secondary', null],
    ];

    protected $table          = 'cmp_lfl_matter_stages';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'name', 'code', 'kind', 'sort', 'color', 'sla_days', 'is_active'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|max_length[80]',
        'kind'       => 'required|in_list[open,filed,waiting,closed,cancelled]',
        'sla_days'   => 'permit_empty|is_natural_no_zero',
    ];

    // La clave a secas: un `lang()` aquí fijaría el idioma de toda la petición.
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vMatterStageName'],
    ];

    public function ordered(int $companyId, bool $activeOnly = false): array
    {
        $b = $this->forCompany($companyId)->orderBy('sort', 'asc')->orderBy('id', 'asc');
        if ($activeOnly) {
            $b->where('is_active', 1);
        }

        return $b->findAll();
    }

    /** @return array<int,array> id => etapa */
    public function mapFor(int $companyId): array
    {
        $out = [];
        foreach ($this->ordered($companyId) as $s) {
            $out[(int) $s['id']] = $s;
        }

        return $out;
    }

    public function byCode(int $companyId, string $code): ?array
    {
        return $this->forCompany($companyId)->where('code', $code)->first();
    }

    /**
     * El `status` que le corresponde al expediente según el kind de su etapa.
     *
     * Una sola traducción kind => status, para que no haya dos sitios que opinen.
     */
    public static function statusFor(string $kind): string
    {
        return match ($kind) {
            'waiting'   => 'on_hold',
            'closed'    => 'closed',
            'cancelled' => 'cancelled',
            default     => 'active',
        };
    }

    /** Las etapas que cierran el expediente: exigen motivo de cierre. */
    public static function isTerminal(string $kind): bool
    {
        return $kind === 'closed' || $kind === 'cancelled';
    }
}
