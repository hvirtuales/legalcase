<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * El perfil publicable de un abogado o de una firma.
 *
 * `user_id` nulo = el perfil de la firma; con valor = el de uno de sus abogados. La
 * firma es siempre la empresa de la plataforma, así que el aislamiento es el de
 * siempre: `company_id` y `owned()`.
 *
 * **El estado manda sobre la visibilidad, no el plan.** Un perfil en `draft` no sale
 * al directorio ni con el mejor plan, y uno `published` deja de salir si el plan de
 * la empresa no incluye el servicio — las dos condiciones las resuelve
 * `Libraries\Directory\PublicDirectory` en un solo sitio.
 */
class ProfileModel extends Model
{
    use CompanyScoped;

    /**
     * Cuántas opiniones hacen falta para que el promedio propio pese la mitad.
     *
     * Con menos, la nota del perfil tira hacia `RANK_BASE`. Es lo que impide que un
     * 5,0 sacado de UNA opinión se coloque por encima de un 4,8 sacado de cuarenta: la
     * media cruda premia a quien tiene poca información, no a quien lo hace bien.
     */
    public const RANK_TRUST = 5;

    /**
     * La nota que se le supone a quien todavía no tiene ninguna opinión.
     *
     * No es 0 a propósito: con 0, estrenar perfil equivaldría a la peor calificación
     * posible y nadie nuevo aparecería jamás. 3,8 lo deja por debajo de quien tiene
     * buenas opiniones y por encima de quien las tiene malas, que es exactamente lo
     * que se sabe de él: nada.
     *
     * **Solo se usa para ORDENAR, nunca se muestra.** Enseñarle a un visitante un 3,8
     * inventado junto al nombre de un abogado sería afirmar algo que nadie dijo.
     */
    public const RANK_BASE = 3.8;

    /**
     * La nota ponderada con la que se ordena. Ver `RANK_TRUST` y `RANK_BASE`.
     *
     * Se calcula en PHP y no en SQL porque el buscador ordena sobre las filas ya
     * traídas; los campos salen desnormalizados de la propia fila.
     */
    public static function rankScore(array $row): float
    {
        $n = (int) ($row['rating_count'] ?? 0);
        $p = (float) ($row['rating_avg'] ?? 0);
        $m = self::RANK_TRUST;

        return ($n / ($n + $m)) * $p + ($m / ($n + $m)) * self::RANK_BASE;
    }

    public const KINDS    = ['lawyer', 'firm'];
    public const STATUSES = ['draft', 'published', 'suspended'];

    /** Modalidades de atención (§8 del insumo). */
    public const MODES = ['video', 'phone', 'in_person', 'chat'];

    protected $table          = 'cmp_lfl_profiles';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'company_id', 'user_id', 'kind', 'slug', 'display_name', 'headline', 'bio', 'photo',
        'email', 'phone', 'whatsapp', 'website', 'city', 'languages', 'consult_modes',
        'experience_years', 'bar_number', 'bar_state', 'bar_url', 'verified_at', 'verified_by',
        'status', 'published_at', 'seo_description', 'leads_count', 'views_count',
        // Copia desnormalizada de la calificación: la escribe `ReviewModel::recalcProfile()`.
        'rating_avg', 'rating_count',
    ];

    protected $validationRules = [
        'company_id'       => 'required|is_natural_no_zero',
        'kind'             => 'required|in_list[lawyer,firm]',
        'slug'             => 'required|max_length[120]|regex_match[/^[a-z0-9][a-z0-9-]*$/]',
        'display_name'     => 'required|max_length[150]',
        'headline'         => 'permit_empty|max_length[180]',
        'email'            => 'permit_empty|valid_email|max_length[150]',
        'website'          => 'permit_empty|max_length[255]',
        'experience_years' => 'permit_empty|is_natural',
        'status'           => 'required|in_list[draft,published,suspended]',
    ];
    protected $validationMessages = [
        'display_name' => ['required' => 'Lfl.vName'],
        'slug'         => ['regex_match' => 'Lfl.vSlug'],
        'email'        => ['valid_email' => 'Lfl.vEmail'],
    ];

    /** Los perfiles de una firma, los publicados primero. */
    public function forCompanyList(int $companyId): array
    {
        return $this->forCompany($companyId)
            ->orderBy('status', 'asc')->orderBy('display_name', 'asc')->findAll();
    }

    public function bySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }

    /** El perfil de este usuario en esta firma, si lo tiene. */
    public function ofUser(int $companyId, int $userId): ?array
    {
        return $this->forCompany($companyId)->where('user_id', $userId)->first();
    }

    public function publishedCount(int $companyId): int
    {
        return $this->forCompany($companyId)->where('status', 'published')->countAllResults();
    }

    /**
     * Un slug libre a partir de un nombre.
     *
     * El slug es único en TODA la plataforma porque vive en la URL pública
     * (`/abogado/{slug}`), así que la comprobación no se acota por empresa. Se busca
     * el primer sufijo libre en vez de confiar en el azar: la URL la va a leer una
     * persona.
     */
    public function freeSlug(string $name, ?int $exceptId = null): string
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(
            // Sin tildes: la URL tiene que poder escribirse a mano y dictarse por teléfono.
            strtr($name, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
                'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n', 'ü' => 'u'])
        ))), '-');
        $base = mb_substr($base, 0, 100) ?: 'perfil';

        $slug = $base;
        $n    = 1;
        while (true) {
            $b = $this->withDeleted()->where('slug', $slug);
            if ($exceptId !== null) {
                $b->where('id !=', $exceptId);
            }
            if ($b->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . '-' . (++$n);
        }
    }

    /**
     * Las iniciales para el avatar: **la primera letra de las dos primeras palabras**.
     *
     * Existe aquí y no repetida en cada vista porque las tres la necesitan —tarjeta de
     * resultado, perfil y tarjeta digital— y cortar los dos primeros caracteres del
     * nombre daba «JA» para «Jaime Zuluaga», que no es una inicial: es una sílaba.
     */
    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name)) ?: [];
        $out   = '';
        foreach ($words as $w) {
            $letter = preg_replace('/[^\p{L}]/u', '', $w);
            if ($letter !== '') {
                $out .= mb_substr($letter, 0, 1);
            }
            if (mb_strlen($out) === 2) {
                break;
            }
        }

        return mb_strtoupper($out !== '' ? $out : '?');
    }

    /** @return string[] la lista guardada como texto separado por comas */
    public static function listOf(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn ($v) => $v !== ''));
    }
}
