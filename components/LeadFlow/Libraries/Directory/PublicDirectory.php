<?php

namespace Components\LeadFlow\Libraries\Directory;

use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Models\AreaModel;
use Components\LeadFlow\Models\JurisdictionModel;
use Components\LeadFlow\Models\ProfileModel;

/**
 * **La única definición de qué se ve en el directorio público.**
 *
 * De aquí beben la portada, el buscador, la landing por área, el perfil y el
 * formulario de consulta. Tener la regla en un solo sitio es lo que evita que una
 * pantalla enseñe un perfil que otra oculta — el mismo criterio que
 * `AutomationRegistry` para las automatizaciones y `ConversionSteps` para la
 * conversión.
 *
 * Son **dos condiciones y las dos tienen que cumplirse**:
 *
 *  1. el perfil está `published` (lo decide la firma);
 *  2. el plan de su empresa incluye el directorio (lo decide la plataforma).
 *
 * La segunda se resuelve con `PlanGuard`, que ya sabe leer el plan efectivo —con la
 * herencia del grupo— y cachea por petición. Se pregunta solo por las empresas que
 * aparecen en los resultados, no por todas: en un directorio la lista de empresas con
 * perfiles publicados es corta, y así no se duplica la lógica del plan aquí.
 */
class PublicDirectory
{
    public const PER_PAGE = 12;

    private PlanGuard $plan;
    private array $allowed = [];

    public function __construct()
    {
        $this->plan = new PlanGuard();
        Taxonomy::ensure();
    }

    /** ¿El plan de esta empresa permite salir en el directorio? */
    private function companyAllowed(int $companyId): bool
    {
        return $this->allowed[$companyId] ??= $this->plan->directoryEnabled($companyId);
    }

    /** Quita de una lista de perfiles los de empresas sin el servicio en su plan. */
    private function filterByPlan(array $rows): array
    {
        return array_values(array_filter($rows, fn ($r) => $this->companyAllowed((int) $r['company_id'])));
    }

    /**
     * Buscador.
     *
     * @param array $f q, area (id), jurisdiction (id), language, mode, city
     *
     * @return array{rows:array,total:int,page:int,pages:int}
     */
    public function search(array $f, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $page = max(1, $page);
        $ids  = $this->matchingIds($f);

        if ($ids === []) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        }

        $m = new ProfileModel();
        $rows = $m->whereIn('id', $ids)
            // Los destacados primero (§10 del insumo: es lo que compra el plan), y a
            // igualdad, los verificados y los más recientes: sin un criterio estable el
            // orden cambia entre páginas y se repiten fichas.
            ->orderBy('published_at', 'desc')->orderBy('id', 'desc')
            ->findAll();

        $rows = $this->filterByPlan($rows);
        $rows = $this->sortFeatured($rows);

        $total = count($rows);
        $pages = (int) ceil($total / $perPage);
        $page  = min($page, max(1, $pages));

        return [
            'rows'  => $this->decorate(array_slice($rows, ($page - 1) * $perPage, $perPage)),
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    /** Ids que cumplen los filtros, ya acotados a lo publicado. */
    private function matchingIds(array $f): array
    {
        $db = db_connect();
        $b  = $db->table('cmp_lfl_profiles p')->select('p.id')
            ->where('p.status', 'published')
            ->where('p.deleted_at', null);

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $b->groupStart()
                ->like('p.display_name', $q)
                ->orLike('p.headline', $q)
                ->orLike('p.bio', $q)
                ->groupEnd();
        }
        if ($city = trim((string) ($f['city'] ?? ''))) {
            $b->where('p.city', $city);
        }
        if ($lang = trim((string) ($f['language'] ?? ''))) {
            $b->like('p.languages', $lang);
        }
        if ($mode = trim((string) ($f['mode'] ?? ''))) {
            $b->like('p.consult_modes', $mode);
        }
        if ($area = (int) ($f['area'] ?? 0)) {
            $b->join('cmp_lfl_profile_areas pa', 'pa.profile_id = p.id')->where('pa.area_id', $area);
        }
        if ($jur = (int) ($f['jurisdiction'] ?? 0)) {
            $b->join('cmp_lfl_profile_jurisdictions pj', 'pj.profile_id = p.id')->where('pj.jurisdiction_id', $jur);
        }

        return array_map('intval', array_column($b->get()->getResultArray(), 'id'));
    }

    /** Los destacados arriba, conservando el orden dentro de cada grupo. */
    private function sortFeatured(array $rows): array
    {
        $featured = $others = [];
        foreach ($rows as $r) {
            if ($this->plan->featured((int) $r['company_id'])) {
                $r['is_featured'] = true;
                $featured[]       = $r;
            } else {
                $r['is_featured'] = false;
                $others[]         = $r;
            }
        }

        /*
         * Dentro de cada bloque, la calificación PONDERADA manda sobre el orden que
         * traían (fecha de publicación).
         *
         * Ponderada y no la media cruda: con la cruda, un 5,0 sacado de UNA opinión se
         * colocaría por encima de un 4,8 sacado de cuarenta, y el buscador premiaría
         * tener poca información en vez de hacerlo bien. `ProfileModel::rankScore()`
         * tira la nota hacia la base mientras haya pocas opiniones.
         *
         * **Esa nota no se enseña nunca**: solo ordena. Lo que el visitante ve es la
         * media real, o nada si todavía no hay opiniones.
         *
         * `usort` no es estable en PHP < 8.0; aquí sí lo es (8.2), así que a igualdad
         * de nota se conserva el orden por fecha que traían.
         */
        $porNota = static function (array &$lista): void {
            usort($lista, static fn (array $a, array $b) => ProfileModel::rankScore($b) <=> ProfileModel::rankScore($a));
        };
        $porNota($featured);
        $porNota($others);

        return array_merge($featured, $others);
    }

    /** Un perfil publicado por su slug, listo para pintar. */
    public function profile(string $slug): ?array
    {
        $row = (new ProfileModel())->where('slug', $slug)->where('status', 'published')->first();
        if (! $row || ! $this->companyAllowed((int) $row['company_id'])) {
            return null;
        }

        return $this->decorate([$row])[0] ?? null;
    }

    /**
     * Añade a cada fila lo que las tarjetas y el perfil necesitan: empresa, áreas,
     * jurisdicciones y las listas cortas ya partidas.
     */
    private function decorate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $db  = db_connect();
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);

        $areas = $jur = [];
        foreach ($db->table('cmp_lfl_profile_areas pa')->select('pa.profile_id, a.name, a.slug')
            ->join('cmp_lfl_areas a', 'a.id = pa.area_id')
            ->whereIn('pa.profile_id', $ids)->orderBy('a.sort', 'asc')->get()->getResultArray() as $r) {
            $areas[(int) $r['profile_id']][] = ['name' => $r['name'], 'slug' => $r['slug']];
        }
        foreach ($db->table('cmp_lfl_profile_jurisdictions pj')->select('pj.profile_id, j.name')
            ->join('cmp_lfl_jurisdictions j', 'j.id = pj.jurisdiction_id')
            ->whereIn('pj.profile_id', $ids)->orderBy('j.sort', 'asc')->get()->getResultArray() as $r) {
            $jur[(int) $r['profile_id']][] = $r['name'];
        }

        // Las valoraciones publicadas, de una sola consulta para todas las filas: una
        // por tarjeta serían N+1 en el buscador.
        $ratings = (new \Components\LeadFlow\Models\ReviewModel())->ratingsOf($ids);

        $companies = [];
        foreach ($db->table('companies')->select('id, name, slug, logo, brand_color')
            ->whereIn('id', array_unique(array_map(static fn ($r) => (int) $r['company_id'], $rows)))
            ->get()->getResultArray() as $c) {
            $companies[(int) $c['id']] = $c;
        }

        foreach ($rows as &$r) {
            $id              = (int) $r['id'];
            $r['areas']      = $areas[$id] ?? [];
            $r['jurisdictions'] = $jur[$id] ?? [];
            $r['languages_list'] = ProfileModel::listOf($r['languages']);
            $r['modes_list']     = ProfileModel::listOf($r['consult_modes']);
            $r['company']        = $companies[(int) $r['company_id']] ?? [];
            $r['is_verified']    = ! empty($r['verified_at']);
            $r['rating']         = $ratings[$id] ?? null;
            $r['is_featured'] ??= $this->plan->featured((int) $r['company_id']);
        }

        return $rows;
    }

    /**
     * Áreas con cuántos perfiles publicados tienen.
     *
     * **Solo las que tienen a alguien.** Una tarjeta de área que lleva a una lista
     * vacía es peor que no ofrecerla; la portada de Salud aplica el mismo criterio con
     * las especialidades.
     *
     * @return array<int,array> con `n`
     */
    public function areasWithCounts(): array
    {
        $db = db_connect();
        $counts = [];
        foreach ($db->table('cmp_lfl_profile_areas pa')
            ->select('pa.area_id, p.company_id, COUNT(*) AS n')
            ->join('cmp_lfl_profiles p', 'p.id = pa.profile_id')
            ->where('p.status', 'published')->where('p.deleted_at', null)
            ->groupBy(['pa.area_id', 'p.company_id'])->get()->getResultArray() as $r) {
            if (! $this->companyAllowed((int) $r['company_id'])) {
                continue;
            }
            $counts[(int) $r['area_id']] = ($counts[(int) $r['area_id']] ?? 0) + (int) $r['n'];
        }

        $out = [];
        foreach ((new AreaModel())->active() as $a) {
            $n = $counts[(int) $a['id']] ?? 0;
            if ($n > 0) {
                $out[] = $a + ['n' => $n];
            }
        }

        return $out;
    }

    /** Ciudades con perfiles publicados, para el selector del buscador. */
    public function cities(): array
    {
        $rows = (new ProfileModel())->select('city, company_id')
            ->where('status', 'published')->where('city IS NOT NULL', null, false)->findAll();

        $out = [];
        foreach ($this->filterByPlan($rows) as $r) {
            $city = trim((string) $r['city']);
            if ($city !== '') {
                $out[$city] = true;
            }
        }
        $out = array_keys($out);
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /** Los últimos perfiles publicados, para la portada. */
    public function latest(int $limit = 6): array
    {
        $rows = (new ProfileModel())->where('status', 'published')
            ->orderBy('published_at', 'desc')->orderBy('id', 'desc')->findAll($limit * 4);

        return $this->decorate(array_slice($this->sortFeatured($this->filterByPlan($rows)), 0, $limit));
    }

    /** Cuántos perfiles y cuántas firmas hay publicados (para las cifras de la portada). */
    public function totals(): array
    {
        $rows = (new ProfileModel())->select('id, company_id')->where('status', 'published')->findAll();
        $rows = $this->filterByPlan($rows);

        return [
            'profiles' => count($rows),
            'firms'    => count(array_unique(array_map(static fn ($r) => (int) $r['company_id'], $rows))),
        ];
    }

    /**
     * Las firmas con presencia en el directorio.
     *
     * **Una firma existe en el directorio si tiene al menos un perfil publicado**, no
     * por estar dada de alta en la plataforma: un micrositio vacío no le sirve a nadie
     * y de paso filtraría la lista de clientes del producto.
     *
     * @return array{rows:array,total:int,page:int,pages:int}
     */
    public function firms(int $page = 1, int $perPage = 12): array
    {
        $db   = db_connect();
        $rows = $db->table('cmp_lfl_profiles p')
            ->select('p.company_id, COUNT(*) AS profiles, MAX(p.published_at) AS last_published')
            ->where('p.status', 'published')->where('p.deleted_at', null)
            ->groupBy('p.company_id')->get()->getResultArray();

        $rows = array_values(array_filter($rows, fn ($r) => $this->companyAllowed((int) $r['company_id'])));
        if ($rows === []) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        }

        // Las más recientes primero: una firma que acaba de entrar merece verse.
        usort($rows, static fn ($a, $b) => strcmp((string) $b['last_published'], (string) $a['last_published']));

        $total = count($rows);
        $pages = (int) ceil($total / $perPage);
        $page  = max(1, min($page, $pages));
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $companies = [];
        foreach ($db->table('companies')->select('id, name, slug, logo, banner, brand_color, description, city, website')
            ->whereIn('id', array_map(static fn ($r) => (int) $r['company_id'], $slice))
            ->where('deleted_at', null)->get()->getResultArray() as $c) {
            $companies[(int) $c['id']] = $c;
        }

        // Una consulta para todas las firmas de la página, no una por tarjeta.
        $ratings = (new \Components\LeadFlow\Models\ReviewModel())
            ->ratingsOfCompanies(array_map(static fn ($r) => (int) $r['company_id'], $slice));

        $out = [];
        foreach ($slice as $r) {
            $c = $companies[(int) $r['company_id']] ?? null;
            // Sin `slug` no hay micrositio al que enlazar: se omite en vez de pintar un
            // enlace que devolvería 404.
            if (! $c || trim((string) $c['slug']) === '') {
                continue;
            }
            $out[] = $c + [
                'profiles' => (int) $r['profiles'],
                'areas'    => $this->areasOfCompany((int) $r['company_id']),
                'rating'   => $ratings[(int) $r['company_id']] ?? null,
            ];
        }

        return ['rows' => $out, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Las áreas de la firma CON su slug, para que la ficha pueda enlazarlas al
     * listado del área. El listado de firmas usa `areasOfCompany()`, que devuelve
     * solo nombres: cambiarle la forma rompería su vista.
     *
     * @return array<int,array{name:string,slug:string}>
     */
    private function areaLinksOfCompany(int $companyId): array
    {
        $out = [];
        foreach (db_connect()->table('cmp_lfl_profile_areas pa')
            ->select('a.name, a.slug')
            ->join('cmp_lfl_profiles p', 'p.id = pa.profile_id')
            ->join('cmp_lfl_areas a', 'a.id = pa.area_id')
            ->where('p.company_id', $companyId)->where('p.status', 'published')->where('p.deleted_at', null)
            ->orderBy('a.sort', 'asc')->get()->getResultArray() as $r) {
            $out[(string) $r['slug']] = ['name' => (string) $r['name'], 'slug' => (string) $r['slug']];
        }

        return array_values($out);
    }

    /** Las áreas en las que la firma tiene gente publicada, para resumirla. */
    private function areasOfCompany(int $companyId): array
    {
        return array_values(array_unique(array_column(db_connect()->table('cmp_lfl_profile_areas pa')
            ->select('a.name')
            ->join('cmp_lfl_profiles p', 'p.id = pa.profile_id')
            ->join('cmp_lfl_areas a', 'a.id = pa.area_id')
            ->where('p.company_id', $companyId)->where('p.status', 'published')->where('p.deleted_at', null)
            ->orderBy('a.sort', 'asc')->get()->getResultArray(), 'name')));
    }

    /**
     * El micrositio de una firma por el slug de `companies`.
     *
     * Le faltaba una pata al directorio: el buscador resuelve cómo se encuentra a *una
     * persona*, pero quien busca «el despacho de la 63» busca un sitio.
     *
     * @return array{company:array,profiles:array}|null
     */
    public function firm(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $company = db_connect()->table('companies')->where('slug', $slug)->where('deleted_at', null)
            ->get()->getRowArray();
        if (! $company || empty($company['is_active']) || ! $this->companyAllowed((int) $company['id'])) {
            return null;
        }

        $profiles = (new ProfileModel())->forCompany((int) $company['id'])
            ->where('status', 'published')->orderBy('user_id', 'asc')->orderBy('display_name', 'asc')->findAll();
        if (! $profiles) {
            return null;
        }

        $profiles = $this->decorate($profiles);
        $reviews  = new \Components\LeadFlow\Models\ReviewModel();

        return [
            'company'  => $company,
            'profiles' => $profiles,
            // Los servicios que la firma decidió enseñar. Activos Y públicos: estar
            // activo es para clasificar leads, ser público es para anunciarse.
            'services' => $this->publicServices((int) $company['id']),
            // Las áreas en las que tiene gente publicada, que es lo que resume a la
            // firma de un vistazo. Salen de los perfiles, no de una lista aparte que
            // habría que mantener a mano y acabaría mintiendo.
            'areas'    => $this->areaLinksOfCompany((int) $company['id']),
            'reviews'  => $reviews->publishedForCompany((int) $company['id']),
            'rating'   => $reviews->ratingOfCompany((int) $company['id']),
            // El desglose por estrella acompaña SIEMPRE a la media: una nota sin
            // saber de cuántas opiniones sale, y cómo se reparten, no dice nada.
            'breakdown' => $reviews->breakdownOfCompany((int) $company['id']),
        ];
    }

    /**
     * El catálogo público de una firma: lo que enseña su micrositio.
     *
     * Solo `is_active` **y** `is_public`, y solo las columnas que se pintan. El resto
     * de la fila —responsable, formulario, tipo de Matter— es configuración interna y
     * no tiene por qué viajar hasta una vista pública aunque esta no la use: lo que no
     * se carga no se puede filtrar por accidente.
     *
     * La columna `is_public` puede no existir si el componente viene de una instalación
     * anterior sin esta migración; en ese caso no se publica nada, que es el valor
     * seguro y el mismo que su defecto.
     */
    private function publicServices(int $companyId): array
    {
        $db = db_connect();
        if (! $db->tableExists('cmp_lfl_services') || ! $db->fieldExists('is_public', 'cmp_lfl_services')) {
            return [];
        }

        $rows = $db->table('cmp_lfl_services s')
            ->select('s.id, s.name, s.description, s.area_id, a.name AS area_name, a.slug AS area_slug')
            ->join('cmp_lfl_areas a', 'a.id = s.area_id', 'left')
            ->where('s.company_id', $companyId)
            ->where('s.is_active', 1)
            ->where('s.is_public', 1)
            ->where('s.deleted_at', null)
            ->orderBy('s.sort', 'asc')->orderBy('s.name', 'asc')
            ->get()->getResultArray();

        return $rows;
    }

    /**
     * Todo lo indexable del directorio, para el mapa del sitio.
     *
     * **Solo lo público y publicado**: portada, buscador, áreas con gente, micrositios y
     * perfiles. Nada con token ni nada en borrador — un sitemap es una invitación a
     * indexar, así que lo que entra ahí queda expuesto para siempre.
     *
     * @return array<int,array{loc:string,lastmod:?string}>
     */
    public function sitemap(int $limit = 5000): array
    {
        $urls = [
            ['loc' => base_url('/'), 'lastmod' => null],
            ['loc' => base_url('buscar'), 'lastmod' => null],
        ];

        foreach ($this->areasWithCounts() as $a) {
            $urls[] = ['loc' => base_url('area/' . rawurlencode((string) $a['slug'])), 'lastmod' => null];
        }

        $rows = (new ProfileModel())->select('slug, company_id, updated_at')
            ->where('status', 'published')->orderBy('published_at', 'desc')->findAll($limit);

        foreach ($this->filterByPlan($rows) as $p) {
            $urls[] = ['loc' => base_url('abogado/' . rawurlencode((string) $p['slug'])), 'lastmod' => $p['updated_at']];
            // La tarjeta también se indexa: es una página de presentación pública.
            $urls[] = ['loc' => base_url('abogado/' . rawurlencode((string) $p['slug']) . '/tarjeta'), 'lastmod' => $p['updated_at']];
        }

        $firms = $this->firms(1, $limit);
        foreach ($firms['rows'] as $c) {
            $urls[] = ['loc' => base_url('firma/' . rawurlencode((string) $c['slug'])), 'lastmod' => null];
        }

        return $urls;
    }

    /** @return array<int,string> para los selectores del buscador */
    public function areaOptions(): array
    {
        return (new AreaModel())->options();
    }

    /** @return array<int,string> */
    public function jurisdictionOptions(): array
    {
        return (new JurisdictionModel())->options();
    }
}
