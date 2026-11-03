<?php

namespace Components\Servicios\Controllers;

use App\Controllers\BaseController;
use Components\Servicios\Libraries\CatalogService;
use Components\Servicios\Libraries\PdfService;
use Components\Servicios\Libraries\QrService;

/**
 * La vitrina que ve el CLIENTE, sin cuenta en la plataforma.
 *
 * Sin `auth` ni empresa activa: todo se resuelve desde el token de la URL, que
 * además dice **qué vendedor** atiende el pedido. Los precios se releen aquí en
 * cada paso; del carrito solo se acepta producto y cantidad.
 */
class PublicCatalog extends BaseController
{
    private CatalogService $service;

    public function __construct()
    {
        $this->service = new CatalogService();
    }

    /** GET servicios/catalogo/{token} */
    public function show($token = null)
    {
        [$assignment, $catalog, $seller, $company] = $this->service->resolve((string) $token);
        if (!$assignment) {
            return $this->response->setStatusCode(404)->setBody(
                view('Components\Servicios\Views\public\catalog_invalid', ['pageTitle' => lang('Quo.pubInvalidTitle')])
            );
        }

        $this->service->trackVisit($assignment);
        $url  = $this->service->publicUrl($assignment);
        $page = $this->service->page($catalog);

        return view('Components\Servicios\Views\public\catalog', [
            'pageTitle' => $catalog['name'] . ' · ' . $company['name'],
            'url'       => $url,
            'qr'        => (new QrService())->dataUri($url, 5),
            'catalog'   => $catalog,
            'seller'    => $seller,
            'company'   => $company,
            'token'     => $assignment['token'],
            'page'      => $page,
            'items'     => $page['rows'],
            'cats'      => $this->service->categories($catalog),
            'hasLoose'  => $this->service->hasUncategorized($catalog),
            'perPage'   => CatalogService::PER_PAGE,
            'og'        => [
                'title'       => $catalog['name'],
                'description' => $catalog['headline'] ?: $company['name'],
                'url'         => $url,
                'image'       => !empty($company['logo']) ? media_url($company['logo']) : null,
            ],
        ]);
    }

    /**
     * GET servicios/catalogo/{token}/items — una página de productos en JSON.
     *
     * La vitrina la usa para el desplazamiento infinito y para filtrar: con
     * miles de referencias, ni se pintan todas de una ni se filtra en el
     * navegador.
     */
    public function items($token = null)
    {
        [$assignment, $catalog] = $this->service->resolve((string) $token);
        if (!$assignment) {
            return $this->response->setStatusCode(404)->setJSON(['rows' => [], 'pages' => 0]);
        }

        $rawCat = $this->request->getGet('cat');
        $res    = $this->service->page(
            $catalog,
            (string) $this->request->getGet('q'),
            $rawCat === null || $rawCat === '' ? null : (int) $rawCat,
            (int) $this->request->getGet('page') ?: 1
        );

        // Se devuelve lo justo para pintar la tarjeta.
        $res['rows'] = array_map(fn ($p) => [
            'id'          => $p['id'],
            'code'        => $p['code'],
            'name'        => $p['name'],
            'description' => $p['description'],
            'category_id' => $p['category_id'],
            'photo'       => $p['photo'] ? media_url($p['photo']) : '',
            'price'       => $this->netPrice($p),
            'before'      => $this->netPrice($p) < (float) $p['unit_price'] ? (float) $p['unit_price'] : 0,
        ], $res['rows']);

        return $this->response->setJSON($res);
    }

    /** Precio con el descuento del valor ya aplicado. */
    private function netPrice(array $p): float
    {
        $unit = (float) $p['unit_price'];
        $disc = $p['discount_type'] === '$'
            ? min((float) $p['discount_value'], $unit)
            : $unit * ((float) $p['discount_value'] / 100);

        return max(0, $unit - $disc);
    }

    /** POST servicios/catalogo/{token}/pedido */
    public function order($token = null)
    {
        [$assignment, $catalog, $seller, $company] = $this->service->resolve((string) $token);
        if (!$assignment) {
            return redirect()->to('/');
        }

        // Del carrito solo se toman ids y cantidades: el precio lo pone el servidor.
        $cart = [];

        foreach ((array) $this->request->getPost('qty') as $productId => $qty) {
            $qty = (float) str_replace(',', '.', (string) $qty);
            if ($qty > 0) {
                $cart[(int) $productId] = $qty;
            }
        }

        $buyer = [
            'name'    => (string) $this->request->getPost('name'),
            'nit'     => (string) $this->request->getPost('nit'),
            'phone'   => (string) $this->request->getPost('phone'),
            'email'   => (string) $this->request->getPost('email'),
            'city'    => (string) $this->request->getPost('city'),
            'address' => (string) $this->request->getPost('address'),
            'notes'   => (string) $this->request->getPost('notes'),
        ];

        $res = $this->service->placeOrder($catalog, $assignment, $seller, $company, $buyer, $cart);

        if (!$res['ok']) {
            return redirect()->back()->withInput()->with('error', $res['message']);
        }

        return view('Components\Servicios\Views\public\catalog_done', [
            'pageTitle' => lang('Quo.orderDoneTitle'),
            'catalog'   => $catalog,
            'company'   => $company,
            'seller'    => $seller,
            'quote'     => $res['quote'],
            'whatsapp'  => $res['whatsapp'],
            'backUrl'   => $this->service->publicUrl($assignment),
        ]);
    }
    /** GET servicios/catalogo/{token}/pdf — el catálogo para imprimir o guardar. */
    public function pdf($token = null)
    {
        [$assignment, $catalog, $seller, $company] = $this->service->resolve((string) $token);
        if (!$assignment) {
            return redirect()->to('/');
        }

        $pdf   = new PdfService();
        $url   = $this->service->publicUrl($assignment);
        $items = $this->service->items($catalog);

        // dompdf no sale a la red: las fotos van por ruta local (o no van).
        foreach ($items as $i => $item) {
            $items[$i]['pdf_photo'] = $pdf->localImage($item['photo'] ?? null);
        }

        $html = view('Components\Servicios\Views\pdf\catalog', [
            'catalog' => $catalog,
            'company' => $company,
            'seller'  => $seller,
            'items'   => $items,
            'logo'    => $pdf->localImage($company['logo'] ?? null),
            'qr'      => (new QrService())->dataUri($url, 4),
            'url'     => $url,
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . url_title($catalog['name'], '-', true) . '.pdf"')
            ->setBody($pdf->render($html));
    }
}
