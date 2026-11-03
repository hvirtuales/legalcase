<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * La opinión de un cliente sobre la ATENCIÓN que recibió.
 *
 * Tres notas y ninguna sobre el resultado del caso: ver la cabecera de la migración
 * `CreateLflReviewTables`, donde está escrito por qué.
 *
 * El modelo no publica nada por su cuenta: `published()` es la única consulta que
 * alimenta las páginas públicas, y filtra por estado. Lo demás —invitar, moderar,
 * responder— lo decide `Libraries\Directory\ReviewService`.
 */
class ReviewModel extends Model
{
    public const STATUSES = ['pending', 'published', 'rejected'];

    /** Las tres dimensiones de la atención, con su clave de idioma. */
    public const SCORES = [
        'score_clarity'  => 'revClarity',
        'score_response' => 'revResponse',
        'score_care'     => 'revCare',
    ];

    protected $table          = 'cmp_lfl_reviews';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'company_id', 'profile_id', 'lead_id', 'token', 'author_name',
        'score_clarity', 'score_response', 'score_care', 'score', 'comment',
        'status', 'moderated_at', 'moderated_by', 'reject_note', 'reply', 'replied_at', 'consent_ip',
    ];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'lead_id'    => 'required|is_natural_no_zero',
        'token'      => 'required|max_length[64]',
        'status'     => 'required|in_list[pending,published,rejected]',
        'comment'    => 'permit_empty|max_length[2000]',
    ];

    use CompanyScoped;

    public function byToken(string $token): ?array
    {
        $token = trim($token);

        return $token === '' ? null : $this->where('token', $token)->first();
    }

    public function ofLead(int $leadId): ?array
    {
        return $this->where('lead_id', $leadId)->first();
    }

    /** Las publicadas de un perfil, las más recientes primero. */
    public function published(int $profileId, int $limit = 20): array
    {
        return $this->where('profile_id', $profileId)->where('status', 'published')
            ->orderBy('created_at', 'desc')->findAll($limit);
    }

    /**
     * Media y número de opiniones publicadas de un perfil.
     *
     * **Devuelve `null` sin opiniones, y eso importa**: las vistas no pintan estrellas
     * vacías. Cinco huecos se leen como un cero, y quien acaba de empezar no merece esa
     * lectura — es la misma decisión que toma la tarjeta de Salud.
     *
     * @return array{avg:float,count:int}|null
     */
    public function ratingOf(int $profileId): ?array
    {
        $row = $this->select('AVG(score) AS avg, COUNT(*) AS n')
            ->where('profile_id', $profileId)->where('status', 'published')
            ->where('score IS NOT NULL', null, false)
            ->get()->getRowArray();

        return ($row && (int) $row['n'] > 0)
            ? ['avg' => round((float) $row['avg'], 2), 'count' => (int) $row['n']]
            : null;
    }

    /** Lo mismo para varios perfiles de una vez, para no consultar por tarjeta. */
    public function ratingsOf(array $profileIds): array
    {
        $profileIds = array_values(array_filter(array_map('intval', $profileIds)));
        if ($profileIds === []) {
            return [];
        }

        $out = [];
        foreach ($this->select('profile_id, AVG(score) AS avg, COUNT(*) AS n')
            ->whereIn('profile_id', $profileIds)->where('status', 'published')
            ->where('score IS NOT NULL', null, false)
            ->groupBy('profile_id')->findAll() as $r) {
            $out[(int) $r['profile_id']] = ['avg' => round((float) $r['avg'], 2), 'count' => (int) $r['n']];
        }

        return $out;
    }

    /**
     * Las opiniones PUBLICADAS de una firma, para su ficha pública.
     *
     * Son las de sus abogados: la opinión siempre es sobre quien atendió, y la firma la
     * hereda. No se mezcla con `forCompanyList()`, que es la cola interna e incluye
     * pendientes y rechazadas — eso no puede salir nunca a una página pública.
     */
    public function publishedForCompany(int $companyId, int $limit = 20): array
    {
        return $this->forCompany($companyId)->where('status', 'published')
            ->orderBy('created_at', 'desc')->findAll($limit);
    }

    /**
     * Media y número de opiniones publicadas de toda la firma.
     *
     * Mismo criterio que `ratingOf()`: **`null` cuando no hay ninguna**, para que la
     * ficha no pinte estrellas vacías.
     *
     * @return array{avg:float,count:int}|null
     */
    public function ratingOfCompany(int $companyId): ?array
    {
        $row = $this->forCompany($companyId)->select('AVG(score) AS avg, COUNT(*) AS n')
            ->where('status', 'published')
            ->where('score IS NOT NULL', null, false)
            ->get()->getRowArray();

        return ($row && (int) $row['n'] > 0)
            ? ['avg' => round((float) $row['avg'], 2), 'count' => (int) $row['n']]
            : null;
    }

    /**
     * Cuántas opiniones publicadas hay de cada estrella, de 5 a 1.
     *
     * Devuelve SIEMPRE las cinco claves, incluidas las que valen cero: el desglose se
     * pinta como cinco barras, y una fila que desaparece porque nadie puso 2 estrellas
     * se lee como si la escala tuviera cuatro valores.
     *
     * @return array<int,int>
     */
    public function breakdownOf(int $profileId): array
    {
        return $this->breakdown($this->where('profile_id', $profileId));
    }

    /** Lo mismo para toda la firma: el desglose de sus abogados. */
    public function breakdownOfCompany(int $companyId): array
    {
        return $this->breakdown($this->forCompany($companyId));
    }

    /**
     * El desglose sobre un constructor ya acotado.
     *
     * La media se redondea al entero que la persona eligió —los tres criterios se
     * promedian en `score`, así que un 4,67 cuenta como 5 estrellas—, porque es lo que
     * se ve: la fila de 5 tiene que sumar las opiniones que el lector reconocería como
     * de cinco estrellas.
     *
     * @return array<int,int>
     */
    private function breakdown($builder): array
    {
        $out = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($builder->select('score')->where('status', 'published')
            ->where('score IS NOT NULL', null, false)->findAll() as $r) {
            $n = (int) round((float) $r['score']);
            if ($n >= 1 && $n <= 5) {
                $out[$n]++;
            }
        }

        return $out;
    }

    /**
     * Media y número de opiniones de VARIAS firmas de una vez.
     *
     * Para el listado de firmas: una consulta en lugar de una por tarjeta.
     *
     * @return array<int,array{avg:float,count:int}>
     */
    public function ratingsOfCompanies(array $companyIds): array
    {
        $companyIds = array_values(array_filter(array_map('intval', $companyIds)));
        if ($companyIds === []) {
            return [];
        }

        $out = [];
        foreach ($this->select('company_id, AVG(score) AS avg, COUNT(*) AS n')
            ->whereIn('company_id', $companyIds)->where('status', 'published')
            ->where('score IS NOT NULL', null, false)
            ->groupBy('company_id')->findAll() as $r) {
            $out[(int) $r['company_id']] = ['avg' => round((float) $r['avg'], 2), 'count' => (int) $r['n']];
        }

        return $out;
    }

    /**
     * Vuelca la calificación del perfil en su propia fila.
     *
     * Hay que llamarlo **cada vez que cambia algo que afecta al promedio**: al
     * responder el cliente y al publicar o rechazar la plataforma. Una copia
     * desnormalizada que nadie actualiza es peor que no tenerla, porque el orden del
     * buscador diría una cosa y la ficha otra.
     *
     * Se escribe por Query Builder y no por `ProfileModel`: así no se disparan sus
     * timestamps ni su validación por un dato que no tocó una persona.
     */
    public function recalcProfile(?int $profileId): void
    {
        $profileId = (int) $profileId;
        if ($profileId <= 0) {
            return;
        }

        $fila = $this->builder()
            ->select('COUNT(*) AS n, AVG(score) AS p')
            ->where('profile_id', $profileId)
            ->where('status', 'published')
            ->where('score IS NOT NULL', null, false)
            ->where('deleted_at', null)
            ->get()->getRowArray();

        $n = (int) ($fila['n'] ?? 0);

        $this->db->table('cmp_lfl_profiles')->where('id', $profileId)->update([
            'rating_count' => $n,
            // Sin opiniones queda en NULL, no en 0: «no se sabe» y «lo puntuaron con un
            // cero» son cosas distintas, y las vistas las pintan distinto.
            'rating_avg'   => $n > 0 ? round((float) $fila['p'], 2) : null,
        ]);
    }

    /** Las de la firma, pendientes primero: es su cola de respuesta. */
    public function forCompanyList(int $companyId, int $limit = 100): array
    {
        return $this->forCompany($companyId)
            ->orderBy('status', 'asc')->orderBy('created_at', 'desc')->findAll($limit);
    }
}
