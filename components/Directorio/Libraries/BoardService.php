<?php

namespace Components\Directorio\Libraries;

use App\Libraries\TenantGuard;
use Components\Directorio\Models\BoardModel;

/**
 * Qué empresas salen en una vista, y con qué datos.
 *
 * Es el único sitio que resuelve el contenido de una vista, para que la
 * pantalla interna y la página pública **no puedan discrepar**: lo que se
 * previsualiza al montarla es exactamente lo que verá el visitante.
 *
 * **Las empresas del grupo se le piden a `TenantGuard`** (contrato tenant
 * §4.1), nunca consultando `companies` por `parent_id` a mano: si mañana la
 * pertenencia al grupo se calcula de otra forma, este componente no se entera.
 *
 * El componente **lee** `companies` y no escribe en ella jamás (§8).
 */
class BoardService
{
    private TenantGuard $guard;

    public function __construct()
    {
        $this->guard = new TenantGuard();
    }

    /**
     * Las empresas de una vista, ya listas para pintar.
     *
     * Devuelve solo empresas **activas y vivas** del grupo: una dada de baja no
     * puede seguir en un directorio público.
     *
     * @return array<int,array>
     */
    public function companiesFor(array $board): array
    {
        $ids = $this->guard->childIdsOfTenants([(int) $board['tenant_id']]);
        if (!$ids) {
            return [];
        }

        $db = db_connect();
        $q  = $db->table('companies c')
            ->select('c.id, c.name, c.slug, c.logo, c.banner, c.description, c.address, c.city,
                      c.website, c.contact_email, c.contact_phone, c.whatsapp, c.brand_color,
                      c.latitude, c.longitude, c.sector_id,
                      s.name AS sector_name, s.color AS sector_color, s.icon AS sector_icon')
            ->join('sectors s', 's.id = c.sector_id', 'left')
            ->whereIn('c.id', $ids)
            ->where('c.is_active', 1)
            ->where('c.deleted_at IS NULL');

        if (($board['mode'] ?? 'sector') === 'manual') {
            $elegidas = $this->pickedIds((int) $board['id']);
            if (!$elegidas) {
                return [];
            }
            $q->whereIn('c.id', $elegidas);
        } elseif (!empty($board['sector_id'])) {
            $q->where('c.sector_id', (int) $board['sector_id']);
        } else {
            // Modo «sector» sin sector elegido = todas las del grupo. Es lo que
            // se espera de una vista recién creada: enseña algo desde el minuto
            // uno en vez de una página vacía que parece rota.
            $q->where('1=1');
        }

        return $q->orderBy('c.name', 'ASC')->get()->getResultArray();
    }

    /** Ids de las empresas marcadas a mano en una vista. */
    public function pickedIds(int $boardId): array
    {
        $filas = db_connect()->table('cmp_dir_board_companies')
            ->select('company_id')
            ->where('board_id', $boardId)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn ($f) => (int) $f['company_id'], $filas);
    }

    /**
     * Guarda la selección manual de una vista.
     *
     * **Solo admite empresas del propio grupo**: los ids llegan del navegador y
     * sin este filtro se podría meter en la vista la empresa de otro tenant
     * marcando una casilla inventada.
     */
    public function savePicked(int $boardId, int $tenantId, array $companyIds): void
    {
        $permitidas = $this->guard->childIdsOfTenants([$tenantId]);
        $limpias    = array_values(array_intersect(
            array_map('intval', $companyIds),
            $permitidas
        ));

        $db = db_connect();
        $db->table('cmp_dir_board_companies')->where('board_id', $boardId)->delete();

        if (!$limpias) {
            return;
        }

        $filas = [];
        $orden = 0;

        foreach ($limpias as $id) {
            $filas[] = ['board_id' => $boardId, 'company_id' => $id, 'sort_order' => $orden += 10];
        }
        $db->table('cmp_dir_board_companies')->insertBatch($filas);
    }

    /**
     * Todas las empresas del grupo, para el selector del formulario.
     *
     * @return array<int,array>
     */
    public function tenantCompanies(int $tenantId): array
    {
        $ids = $this->guard->childIdsOfTenants([$tenantId]);
        if (!$ids) {
            return [];
        }

        return db_connect()->table('companies c')
            ->select('c.id, c.name, c.city, c.logo, c.sector_id, s.name AS sector_name')
            ->join('sectors s', 's.id = c.sector_id', 'left')
            ->whereIn('c.id', $ids)
            ->where('c.deleted_at IS NULL')
            ->orderBy('c.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Los sectores que de verdad usan las empresas del grupo.
     *
     * Ofrecer el catálogo entero en el formulario invita a crear vistas vacías;
     * aquí solo salen los sectores en los que el grupo tiene alguien, con
     * cuántos, que es lo que hace falta para decidir.
     *
     * @return array<int,array{id:int,name:string,color:?string,icon:?string,total:int}>
     */
    public function sectorsInUse(int $tenantId): array
    {
        $ids = $this->guard->childIdsOfTenants([$tenantId]);
        if (!$ids) {
            return [];
        }

        $filas = db_connect()->table('companies c')
            ->select('s.id, s.name, s.color, s.icon, COUNT(c.id) AS total')
            ->join('sectors s', 's.id = c.sector_id', 'inner')
            ->whereIn('c.id', $ids)
            ->where('c.deleted_at IS NULL')
            ->where('c.is_active', 1)
            ->groupBy('s.id, s.name, s.color, s.icon')
            ->orderBy('s.name', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn ($f) => [
            'id'    => (int) $f['id'],
            'name'  => (string) $f['name'],
            'color' => $f['color'],
            'icon'  => $f['icon'],
            'total' => (int) $f['total'],
        ], $filas);
    }

    /**
     * Cuántas empresas tendría cada vista **y cuántas de ellas saldrían en el
     * mapa**.
     *
     * Lo segundo es lo que de verdad hacía falta: una empresa sin coordenadas
     * aparece en el listado y **no** en el mapa, así que una vista entera puede
     * salir publicada con el mapa vacío sin que nada lo avise. Aquí se ve antes
     * de publicar, y en la pantalla donde además se puede arreglar.
     *
     * @return array<int,array{total:int,located:int}>
     */
    public function countsFor(array $boards): array
    {
        $out = [];

        foreach ($boards as $b) {
            $empresas = $this->companiesFor($b);
            $ubicadas = array_filter(
                $empresas,
                static fn ($c) => $c['latitude'] !== null && $c['longitude'] !== null
            );
            $out[(int) $b['id']] = [
                'total'   => count($empresas),
                'located' => count($ubicadas),
            ];
        }

        return $out;
    }
}
