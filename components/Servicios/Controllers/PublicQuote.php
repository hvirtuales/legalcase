<?php

namespace Components\Servicios\Controllers;

use App\Controllers\BaseController;
use Components\Servicios\Libraries\PdfService;
use Components\Servicios\Libraries\PublicLinkService;
use Components\Servicios\Models\SettingsModel;

/**
 * Cotización vista por el CLIENTE, sin cuenta en la plataforma.
 *
 * Estas rutas NO llevan `auth` ni empresa activa: todo se resuelve desde el token
 * de la URL. Por eso, cada método vuelve a validar el token y —si la hay— la
 * autorización por contraseña; nunca se confía en un id que venga del navegador.
 */
class PublicQuote extends BaseController
{
    private PublicLinkService $links;

    public function __construct()
    {
        $this->links = new PublicLinkService();
    }

    /**
     * Resuelve la cotización del token y decide qué toca mostrar.
     *
     * @return array{0:?array,1:?\CodeIgniter\HTTP\ResponseInterface}
     */
    private function resolve(string $token): array
    {
        $quote = $this->links->findByToken($token);
        if (!$quote) {
            return [null, $this->response->setStatusCode(404)->setBody(
                view('Components\Servicios\Views\public\invalid')
            )];
        }

        if ($this->links->needsPassword($quote) && !$this->links->sessionGranted($token)) {
            return [null, $this->response->setBody(
                view('Components\Servicios\Views\public\locked', ['token' => $token])
            )];
        }

        return [$quote, null];
    }

    /** GET servicios/p/{token} */
    public function show($token = null)
    {
        [$quote, $stop] = $this->resolve((string) $token);
        if ($stop) {
            return $stop;
        }

        $this->links->trackView($quote, (string) $this->request->getIPAddress());

        return view('Components\Servicios\Views\public\show', [
            'quote'    => $quote,
            'token'    => $token,
            'company'  => (new \App\Models\CompanyModel())->find((int) $quote['company_id']),
            'cfg'      => (new SettingsModel())->forCompany((int) $quote['company_id']),
            'decision' => $this->links->decision((int) $quote['id']),
        ]);
    }

    /** POST servicios/p/{token}/acceso — comprobación de la contraseña. */
    public function unlock($token = null)
    {
        $token = (string) $token;
        $quote = $this->links->findByToken($token);
        if (!$quote) {
            return $this->response->setStatusCode(404)->setBody(view('Components\Servicios\Views\public\invalid'));
        }

        $res = $this->links->checkPassword($quote, (string) $this->request->getPost('password'));
        if (!$res['ok']) {
            return $this->response->setBody(view('Components\Servicios\Views\public\locked', [
                'token' => $token,
                'error' => $res['message'],
            ]));
        }

        $this->links->grantSession($token);

        return redirect()->to('servicios/p/' . $token);
    }

    /** POST servicios/p/{token}/responder — aprobar, rechazar o pedir ajustes. */
    public function respond($token = null)
    {
        [$quote, $stop] = $this->resolve((string) $token);
        if ($stop) {
            return $stop;
        }

        $res = $this->links->respond(
            $quote,
            (string) $this->request->getPost('action'),
            (string) $this->request->getPost('message'),
            (string) $this->request->getIPAddress()
        );

        return redirect()->to('servicios/p/' . $token)
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** GET servicios/p/{token}/pdf — el cliente descarga su copia. */
    public function pdf($token = null)
    {
        [$quote, $stop] = $this->resolve((string) $token);
        if ($stop) {
            return $stop;
        }

        $pdf     = new PdfService();
        $company = (new \App\Models\CompanyModel())->find((int) $quote['company_id']);

        $html = view('Components\Servicios\Views\pdf\document', [
            'quote'   => $quote,
            'company' => $company,
            'cfg'     => (new SettingsModel())->forCompany((int) $quote['company_id']),
            'logo'    => $pdf->localImage($company['logo'] ?? null),
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $quote['number'] . '.pdf"')
            ->setBody($pdf->render($html));
    }
}
