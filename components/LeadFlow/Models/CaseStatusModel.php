<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Caché de estados de caso de USCIS, por firma.
 *
 * Lo que guarda es la respuesta de USCIS tal cual llegó, normalizada. **No se
 * interpreta nada**: ni se traduce, ni se clasifica en «bien/mal», ni se deriva un
 * «lleva mucho tiempo». Eso sería opinar sobre un trámite legal, que es justo lo que la
 * plataforma promete no hacer.
 */
class CaseStatusModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_case_status';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'company_id', 'receipt', 'form_type', 'status', 'status_es',
        'detail', 'detail_es', 'submitted_at', 'case_updated', 'history', 'checked_at',
    ];

    /**
     * Cuánto vale un dato antes de volver a preguntar.
     *
     * 12 horas. Un trámite de USCIS no cambia de estado varias veces al día, y con el
     * cupo en 1.000 consultas diarias un TTL corto se lo come en tráfico repetido:
     * cien personas consultando su caso dos veces al día ya serían 200 llamadas.
     */
    public const TTL_HOURS = 12;

    /**
     * La fila cacheada de un recibo, fresca o no.
     *
     * Se llama `lookup()` y no `find()`: **`CodeIgniter\Model` ya declara `find()`**
     * con otra firma, y redeclararlo rompe la clase entera al cargarla. Es la misma
     * piedra de `Model::set()` y de `Appointments::result()`.
     */
    public function lookup(int $companyId, string $receipt): ?array
    {
        return $this->forCompany($companyId)->where('receipt', strtoupper(trim($receipt)))->first();
    }

    /** ¿Esta fila sigue valiendo, o hay que volver a preguntar? */
    public static function isFresh(?array $row): bool
    {
        if (! $row || empty($row['checked_at'])) {
            return false;
        }

        return strtotime((string) $row['checked_at']) > time() - self::TTL_HOURS * 3600;
    }

    /**
     * Guarda —o actualiza— lo que devolvió USCIS.
     *
     * `updated` lleva un nombre distinto en la tabla (`case_updated`) a propósito: el
     * modelo usa timestamps y `updated_at` ya significa «cuándo tocamos esta fila».
     * Mezclarlos haría que la fecha del caso se pisara sola en cada guardado.
     */
    public function store(int $companyId, array $case): array
    {
        $receipt = strtoupper(trim((string) $case['receipt']));
        $data    = [
            'company_id'   => $companyId,
            'receipt'      => $receipt,
            'form_type'    => $case['form_type'] ?? null,
            'status'       => $case['status'] ?? null,
            'status_es'    => $case['status_es'] ?? null,
            'detail'       => $case['detail'] ?? null,
            'detail_es'    => $case['detail_es'] ?? null,
            'submitted_at' => $case['submitted_at'] ?? null,
            'case_updated' => $case['updated_at'] ?? null,
            'history'      => json_encode($case['history'] ?? [], JSON_UNESCAPED_UNICODE),
            'checked_at'   => date('Y-m-d H:i:s'),
        ];

        $row = $this->lookup($companyId, $receipt);
        if ($row) {
            $this->update((int) $row['id'], $data);
        } else {
            $this->insert($data);
        }

        return $this->lookup($companyId, $receipt) ?? $data;
    }

    /**
     * Cuántos días se conserva un estado antes de borrarlo.
     *
     * **La caché no es un archivo.** Lo que guarda es el número de recibo de alguien y
     * en qué punto está su trámite migratorio: un dato personal sensible que existe
     * aquí por una razón concreta —no volver a preguntarle lo mismo a USCIS— y que
     * deja de tener razón de ser en cuanto esa persona no vuelve.
     *
     * 30 días cubre de sobra el uso real —quien sigue su caso lo mira cada pocos
     * días— y pone un límite a lo que se acumula. Sin él, una plataforma con tráfico
     * acabaría guardando indefinidamente el historial migratorio de miles de personas
     * que solo pasaron una vez por la página de una firma.
     */
    public const KEEP_DAYS = 30;

    /**
     * Borra los estados que nadie ha consultado en `KEEP_DAYS`.
     *
     * Lo llama `leadflow:tick`. Borrado de verdad, no soft-delete: una fila marcada
     * como borrada sigue guardando el recibo y el estado, que es justo lo que se
     * quería dejar de guardar.
     */
    public function purgeStale(): int
    {
        $limite = date('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400);

        $this->db->table($this->table)->where('checked_at <', $limite)->delete();

        return $this->db->affectedRows();
    }

    /**
     * Una fila de la caché con la forma que devuelve el adaptador.
     *
     * Así la vista no tiene que saber si el dato vino de la red o de la caché: lo
     * único que cambia es `checked_at`, que se enseña cuando el dato no es de ahora.
     */
    public static function toCase(array $row): array
    {
        return [
            'receipt'      => (string) $row['receipt'],
            'form_type'    => $row['form_type'] ?? null,
            'status'       => $row['status'] ?? null,
            'status_es'    => $row['status_es'] ?? null,
            'detail'       => $row['detail'] ?? null,
            'detail_es'    => $row['detail_es'] ?? null,
            'submitted_at' => $row['submitted_at'] ?? null,
            'updated_at'   => $row['case_updated'] ?? null,
            'history'      => json_decode((string) ($row['history'] ?? '[]'), true) ?: [],
        ];
    }
}
