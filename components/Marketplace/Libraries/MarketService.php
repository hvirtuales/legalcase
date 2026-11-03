<?php

namespace Components\Marketplace\Libraries;

use Components\Marketplace\Models\CategoryModel;
use Components\Marketplace\Models\InterestModel;
use Components\Marketplace\Models\ListingModel;
use Components\Marketplace\Models\TagModel;

/**
 * Reglas del marketplace: búsqueda del catálogo, etiquetas, tarjetas y el cruce
 * oferta/demanda. Los controladores quedan delgados.
 */
class MarketService
{
    private ListingModel $listings;
    private CategoryModel $categories;
    private TagModel $tags;
    private InterestModel $interests;

    public function __construct()
    {
        $this->listings   = new ListingModel();
        $this->categories = new CategoryModel();
        $this->tags       = new TagModel();
        $this->interests  = new InterestModel();
    }

    // ------------------------------------------------------------------ etiquetas

    /**
     * Reemplaza las etiquetas de una publicación a partir del texto del formulario
     * («diseño web, ecommerce»). Crea las que no existan y descarta duplicadas.
     *
     * @return int etiquetas asociadas
     */
    public function syncTags(int $listingId, string $raw): int
    {
        $db = db_connect();
        $db->table('cmp_mkt_listing_tags')->where('listing_id', $listingId)->delete();

        $ids = [];
        foreach (preg_split('/[,;]+/', $raw) ?: [] as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $id = $this->tags->idFor($name);
            if ($id && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            if (count($ids) >= 10) {
                break; // tope sano por publicación
            }
        }

        foreach ($ids as $id) {
            $db->table('cmp_mkt_listing_tags')->insert(['listing_id' => $listingId, 'tag_id' => $id]);
        }

        return count($ids);
    }

    /** Etiquetas de una publicación. @return array<int,array{id:int,name:string,slug:string}> */
    public function tagsOf(int $listingId): array
    {
        return db_connect()->table('cmp_mkt_listing_tags lt')
            ->select('t.id, t.name, t.slug')
            ->join('cmp_mkt_tags t', 't.id = lt.tag_id')
            ->where('lt.listing_id', $listingId)
            ->orderBy('t.name', 'asc')
            ->get()->getResultArray();
    }

    /** Texto plano de las etiquetas, para rellenar el formulario de edición. */
    public function tagsText(int $listingId): string
    {
        return implode(', ', array_column($this->tagsOf($listingId), 'name'));
    }

    /** Etiquetas más usadas en el catálogo publicado (nube de filtros). */
    public function popularTags(int $limit = 15): array
    {
        return db_connect()->table('cmp_mkt_listing_tags lt')
            ->select('t.name, t.slug, COUNT(lt.listing_id) AS total')
            ->join('cmp_mkt_tags t', 't.id = lt.tag_id')
            ->join('cmp_mkt_listings l', 'l.id = lt.listing_id')
            ->where('l.status', 'published')
            ->where('l.deleted_at', null)
            ->groupBy('t.id, t.name, t.slug')
            ->orderBy('total', 'desc')
            ->orderBy('t.name', 'asc')
            ->limit($limit)
            ->get()->getResultArray();
    }

    // ------------------------------------------------------------------ catálogo

    /**
     * Categorías activas para los selectores y filtros. Si la tabla está vacía
     * (primer uso tras instalar), siembra las base — así el componente es usable
     * desde el minuto uno sin pasos manuales.
     */
    public function categories(): array
    {
        $rows = $this->categories->active();
        if (!$rows && $this->categories->countAllResults() === 0) {
            (new CatalogService())->seedCategories();
            $rows = $this->categories->active();
        }

        return $rows;
    }

    /**
     * Aplica los filtros del catálogo sobre el modelo (lo usa DataTable):
     * tipo, lado (oferta/demanda), categoría y etiqueta.
     */
    public function applyCatalogFilters($model, array $f): void
    {
        $model->select('cmp_mkt_listings.*, companies.name AS company_name, companies.logo AS company_logo, companies.banner AS company_banner')
            ->join('companies', 'companies.id = cmp_mkt_listings.company_id')
            ->where('cmp_mkt_listings.status', 'published');

        // Vigencia: sin fecha o aún vigente
        $model->groupStart()
            ->where('cmp_mkt_listings.valid_until', null)
            ->orWhere('cmp_mkt_listings.valid_until >=', date('Y-m-d H:i:s'))
            ->groupEnd();

        if (!empty($f['type']) && in_array($f['type'], ListingModel::TYPES, true)) {
            $model->where('cmp_mkt_listings.type', $f['type']);
        } elseif (($f['side'] ?? '') === 'offer') {
            $model->whereIn('cmp_mkt_listings.type', ListingModel::OFFERS);
        } elseif (($f['side'] ?? '') === 'demand') {
            $model->whereIn('cmp_mkt_listings.type', ListingModel::DEMANDS);
        }

        if (!empty($f['category'])) {
            $model->where('cmp_mkt_listings.category_id', (int) $f['category']);
        }

        if (!empty($f['tag'])) {
            $ids = array_column(
                db_connect()->table('cmp_mkt_listing_tags lt')
                    ->select('lt.listing_id')
                    ->join('cmp_mkt_tags t', 't.id = lt.tag_id')
                    ->where('t.slug', $f['tag'])
                    ->get()->getResultArray(),
                'listing_id'
            );
            // Sin coincidencias: fuerza un resultado vacío en vez de ignorar el filtro.
            $model->whereIn('cmp_mkt_listings.id', $ids ?: [0]);
        }
    }

    /** Conteo por tipo del catálogo publicado (para las pestañas). */
    public function countsByType(): array
    {
        $rows = db_connect()->table('cmp_mkt_listings')
            ->select('type, COUNT(*) AS total')
            ->where('status', 'published')
            ->where('deleted_at', null)
            ->groupBy('type')
            ->get()->getResultArray();

        $out = ['all' => 0];
        foreach (ListingModel::TYPES as $t) {
            $out[$t] = 0;
        }
        foreach ($rows as $r) {
            $out[$r['type']] = (int) $r['total'];
            $out['all'] += (int) $r['total'];
        }

        return $out;
    }

    /** Publicación + empresa + categoría + etiquetas, lista para la vista. */
    public function listingWithRelations(int $id): ?array
    {
        $row = $this->listings->find($id);
        if (!$row) {
            return null;
        }
        $company  = (new \App\Models\CompanyModel())->find((int) $row['company_id']);
        $category = $row['category_id'] ? $this->categories->find((int) $row['category_id']) : null;

        return $row + [
            'company'  => $company,
            'category' => $category,
            'tags'     => $this->tagsOf($id),
        ];
    }

    public function incrementViews(int $id): void
    {
        db_connect()->table('cmp_mkt_listings')->where('id', $id)->set('views', 'views + 1', false)->update();
    }

    // ------------------------------------------------------------------ intereses

    /**
     * Registra el interés de una empresa en una publicación ajena.
     *
     * @return array{ok:bool,message:string}
     */
    public function expressInterest(int $listingId, int $companyId, int $userId, string $message): array
    {
        $listing = $this->listings->find($listingId);
        if (!$listing || $listing['status'] !== 'published') {
            return ['ok' => false, 'message' => lang('Mkt.errListingUnavailable')];
        }
        if ((int) $listing['company_id'] === $companyId) {
            return ['ok' => false, 'message' => lang('Mkt.errOwnListing')];
        }
        $already = $this->interests
            ->where('listing_id', $listingId)
            ->where('company_id', $companyId)
            ->where('status !=', 'closed')
            ->first();
        if ($already) {
            return ['ok' => false, 'message' => lang('Mkt.errAlreadyInterested')];
        }

        $ok = $this->interests->insert([
            'listing_id' => $listingId,
            'company_id' => $companyId,
            'user_id'    => $userId,
            'message'    => $message,
            'status'     => 'new',
        ]);

        return $ok
            ? ['ok' => true, 'message' => lang('Mkt.okInterestSent')]
            : ['ok' => false, 'message' => implode(' ', $this->interests->errors())];
    }

    /** Interesados en las publicaciones de una empresa (su bandeja). */
    public function inboxFor(int $companyId, string $status = ''): array
    {
        $b = db_connect()->table('cmp_mkt_interests i')
            ->select('i.*, l.title AS listing_title, l.type AS listing_type, c.name AS from_company, c.contact_email, c.contact_phone, c.whatsapp')
            ->join('cmp_mkt_listings l', 'l.id = i.listing_id')
            ->join('companies c', 'c.id = i.company_id')
            ->where('l.company_id', $companyId)
            ->where('l.deleted_at', null);

        if (in_array($status, InterestModel::STATUSES, true)) {
            $b->where('i.status', $status);
        }

        return $b->orderBy('i.id', 'desc')->get()->getResultArray();
    }

    /** Cuántos interesados sin atender tiene la empresa. */
    public function newInterestCount(int $companyId): int
    {
        return (int) db_connect()->table('cmp_mkt_interests i')
            ->join('cmp_mkt_listings l', 'l.id = i.listing_id')
            ->where('l.company_id', $companyId)
            ->where('l.deleted_at', null)
            ->where('i.status', 'new')
            ->countAllResults();
    }

    /**
     * Cambia el estado de un interés, comprobando que la publicación sea de la
     * empresa que lo intenta (el permiso no basta: hay que ser el dueño).
     */
    public function changeInterestStatus(int $interestId, int $companyId, string $status): array
    {
        if (!in_array($status, InterestModel::STATUSES, true)) {
            return ['ok' => false, 'message' => lang('Mkt.errBadStatus')];
        }
        $row = db_connect()->table('cmp_mkt_interests i')
            ->select('i.id')
            ->join('cmp_mkt_listings l', 'l.id = i.listing_id')
            ->where('i.id', $interestId)
            ->where('l.company_id', $companyId)
            ->get()->getRowArray();

        if (!$row) {
            return ['ok' => false, 'message' => lang('Mkt.errInterestNotFound')];
        }
        $this->interests->update($interestId, ['status' => $status]);

        return ['ok' => true, 'message' => lang('Mkt.okInterestUpdated')];
    }
}
