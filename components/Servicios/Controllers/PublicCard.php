<?php

namespace Components\Servicios\Controllers;

use App\Controllers\BaseController;
use Components\Servicios\Libraries\QrService;
use Components\Servicios\Libraries\SellerCardService;

/**
 * Tarjeta digital del vendedor, vista por CUALQUIERA con el enlace.
 *
 * Sin `auth` ni empresa activa: todo se resuelve desde el token de la URL, que
 * el controlador vuelve a validar en cada método (nunca se confía en un id que
 * venga del navegador).
 */
class PublicCard extends BaseController
{
    private SellerCardService $cards;

    public function __construct()
    {
        $this->cards = new SellerCardService();
    }

    /** GET servicios/tarjeta/{token} */
    public function show($token = null)
    {
        [$seller, $company] = $this->cards->resolve((string) $token);
        if (!$seller) {
            return $this->response->setStatusCode(404)->setBody(
                view('Components\Servicios\Views\public\card_invalid', ['pageTitle' => lang('Quo.pubInvalidTitle')])
            );
        }

        $url = $this->cards->publicUrl($seller);

        // Foto del vendedor (o logo de la empresa) para que el enlace se
        // previsualice con cara al compartirlo por WhatsApp o redes.
        $image = $seller['photo'] ?: ($company['logo'] ?? '');

        return view('Components\Servicios\Views\public\card', [
            'pageTitle' => $seller['name'] . ' · ' . $company['name'],
            'seller'    => $seller,
            'company'   => $company,
            'palette'   => $this->cards->palette($company['brand_color'] ?? null),
            'wa'        => $this->cards->whatsappNumber($seller['phone'] ?: ($company['whatsapp'] ?? '')),
            'url'       => $url,
            'qr'        => (new QrService())->dataUri($url, 4),
            'og'        => [
                'title'       => $seller['name'],
                'description' => lang('Quo.cardMetaDesc', [
                    $seller['position'] ?: $company['name'],
                    $company['name'],
                ]),
                'url'         => $url,
                'image'       => $image ? media_url($image) : null,
            ],
        ]);
    }

    /** GET servicios/tarjeta/{token}/vcf — «Guardar contacto». */
    public function vcf($token = null)
    {
        [$seller, $company] = $this->cards->resolve((string) $token);
        if (!$seller) {
            return redirect()->to('/');
        }

        $file = url_title($seller['name'], '-', true) . '.vcf';

        return $this->response
            ->setContentType('text/vcard; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file . '"')
            ->setBody($this->cards->vcard($seller, $company));
    }
}
