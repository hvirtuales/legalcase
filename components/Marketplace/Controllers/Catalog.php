<?php

namespace Components\Marketplace\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Marketplace\Libraries\MarketService;
use Components\Marketplace\Models\ListingModel;

/**
 * Catálogo común: la oferta y la demanda de todas las empresas de la plataforma.
 * Es donde se cruzan; el aislamiento por empresa aplica a «Mis publicaciones»,
 * no aquí.
 */
class Catalog extends BaseController
{
    private MarketService $market;

    public function __construct()
    {
        $this->market = new MarketService();
    }

    public function index()
    {
        $req     = $this->request;
        $filters = [
            'side'     => (string) $req->getGet('side'),
            'type'     => (string) $req->getGet('type'),
            'category' => (string) $req->getGet('category'),
            'tag'      => (string) $req->getGet('tag'),
        ];

        $dt = (new DataTable(new ListingModel(), $req))
            ->searchable([
                'cmp_mkt_listings.title', 'cmp_mkt_listings.summary',
                'cmp_mkt_listings.description', 'cmp_mkt_listings.city',
                'companies.name',
            ])
            ->sortable(['cmp_mkt_listings.created_at', 'cmp_mkt_listings.title', 'cmp_mkt_listings.views'])
            ->defaultSort('cmp_mkt_listings.created_at', 'desc')
            ->query(fn ($m) => $this->market->applyCatalogFilters($m, $filters))
            ->paginate(12);

        // Etiquetas de las publicaciones visibles (para pintarlas en la tarjeta).
        $tags = [];

        foreach ($dt['rows'] as $row) {
            $tags[(int) $row['id']] = $this->market->tagsOf((int) $row['id']);
        }

        return view('Components\Marketplace\Views\catalog', [
            'title'       => lang('Mkt.catalog'),
            'dt'          => $dt,
            'tags'        => $tags,
            'filters'     => array_filter($filters),
            'categories'  => $this->market->categories(),
            'popularTags' => $this->market->popularTags(),
            'counts'      => $this->market->countsByType(),
            'extraParams' => array_filter($filters),
        ]);
    }

    public function show($id = null)
    {
        $listing = $this->market->listingWithRelations((int) $id);
        $me      = (int) session()->get('active_company_id');

        // Solo se ve publicada, salvo que sea de la propia empresa (previsualización).
        if (!$listing || ($listing['status'] !== 'published' && (int) $listing['company_id'] !== $me)) {
            return redirect()->to('marketplace')->with('error', lang('Mkt.errListingUnavailable'));
        }

        if ((int) $listing['company_id'] !== $me) {
            $this->market->incrementViews((int) $id);
        }

        return view('Components\Marketplace\Views\show', [
            'title'   => $listing['title'],
            'listing' => $listing,
            'isMine'  => (int) $listing['company_id'] === $me,
            'canAsk'  => $me > 0 && (int) $listing['company_id'] !== $me,
        ]);
    }
}
