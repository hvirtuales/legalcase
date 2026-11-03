<?php

namespace Components\LegalCase\Controllers;

use App\Controllers\BaseController;
use App\Models\CompanyModel;
use Components\LegalCase\Libraries\Audit;
use Components\LegalCase\Libraries\ClientView;
use Components\LegalCase\Libraries\DocumentService;
use Components\LegalCase\Libraries\MessageService;
use Components\LegalCase\Libraries\Portal;
use Components\LegalCase\Models\PortalAccountModel;
use Components\LegalCase\Models\SettingsModel;

/**
 * Portal del cliente (`/legalcase-portal`).
 *
 * Sin `auth` del núcleo: la identidad es la cuenta del portal, revalidada en CADA método
 * por `Portal::current()`. Los datos salen solo por `ClientView`, que acota cada lectura
 * a los asuntos donde la cuenta tiene `portal_access` y devuelve listas blancas de campos.
 *
 * Las vistas son autosuficientes (contrato §7.3): CSS y SVG en línea, tipografía del
 * sistema, sin `tailwind.css` ni CDN.
 */
class ClientPortal extends BaseController
{
    private Portal $portal;
    private ClientView $view;

    public function __construct()
    {
        $this->portal = new Portal();
        $this->view   = new ClientView();
    }

    /** Fija el idioma de la cuenta para la petición y aplica cabeceras de privacidad. */
    private function prepare(?array $account): void
    {
        $locale = $account['locale'] ?? (session('language') ?: 'es');
        $this->request->setLocale(in_array($locale, ['es', 'en'], true) ? $locale : 'es');
        // Contenido privado: ni caché compartida ni indexación ni incrustación en otro sitio.
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $this->response->setHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->response->setHeader('X-Frame-Options', 'DENY');
        $this->response->setHeader('Referrer-Policy', 'no-referrer');
    }

    private function page(string $view, array $data = [], ?array $account = null)
    {
        $company = $account ? ((new CompanyModel())->find((int) $account['company_id']) ?? []) : [];

        return view('Components\LegalCase\Views\portal\\' . $view, $data + [
            'account' => $account,
            'company' => $company,
            'flash'   => ['ok' => session()->getFlashdata('lgc_ok'), 'err' => session()->getFlashdata('lgc_err')],
        ]);
    }

    private function requireAccount()
    {
        $account = $this->portal->current();
        $this->prepare($account);

        return $account;
    }

    // ------------------------------------------------------------------ acceso

    public function index()
    {
        $account = $this->requireAccount();
        if (!$account) {
            return $this->page('login', ['title' => lang('Lgc.ptLoginTitle')]);
        }
        $settings = (new SettingsModel())->forCompanyOrDefault((int) $account['company_id']);

        return $this->page('matters', [
            'title'   => lang('Lgc.ptMyMatters'),
            'matters' => $this->view->matters($account),
            'help'    => (string) ($settings['portal_help'] ?? ''),
        ], $account);
    }

    /** POST — pide un enlace. Siempre la misma respuesta (no revela si el correo existe). */
    public function requestLink()
    {
        $this->prepare(null);
        $ipKey = 'lgcportal_' . hash('sha256', (string) $this->request->getIPAddress());
        if (service('throttler')->check($ipKey, 5, MINUTE) === false) {
            return $this->page('login', ['title' => lang('Lgc.ptLoginTitle'), 'error' => lang('Lgc.ptTooMany')]);
        }
        $this->portal->requestLink((string) $this->request->getPost('email'));

        return $this->page('sent', ['title' => lang('Lgc.ptSentTitle')]);
    }

    /** GET — el enlace del correo: confirma con un botón (los escáneres no lo consumen). */
    public function confirm($token = null)
    {
        $this->prepare(null);
        if (!$this->portal->peek((string) $token)) {
            return $this->page('invalid', ['title' => lang('Lgc.ptInvalidTitle')]);
        }

        return $this->page('confirm', ['title' => lang('Lgc.ptConfirmTitle'), 'token' => (string) $token]);
    }

    public function enter($token = null)
    {
        $this->prepare(null);
        $account = $this->portal->consume((string) $token);
        if (!$account) {
            return $this->page('invalid', ['title' => lang('Lgc.ptInvalidTitle')]);
        }
        $this->portal->login($account);

        return redirect()->to(Portal::url());
    }

    public function logout()
    {
        $this->portal->logout();

        return redirect()->to(Portal::url());
    }

    // ------------------------------------------------------------------ asunto

    public function matter($id = null)
    {
        $account = $this->requireAccount();
        if (!$account) {
            return redirect()->to(Portal::url());
        }
        $matter = $this->view->matter($account, (int) $id);
        if (!$matter) {
            return $this->response->setStatusCode(404)->setBody($this->page('invalid', ['title' => lang('Lgc.ptNotFound'), 'notFound' => true], $account));
        }
        Audit::log((int) $account['company_id'], (int) $matter['id'], 'portal.viewed', 'matter', (int) $matter['id'], [], 'portal', (int) $account['id']);

        return $this->page('matter', ['title' => $matter['label'], 'matter' => $matter] + $this->view->detail($account, $matter), $account);
    }

    public function message($id = null)
    {
        $account = $this->requireAccount();
        if (!$account) {
            return redirect()->to(Portal::url());
        }
        $matter = $this->view->matter($account, (int) $id);
        if (!$matter || $matter['status'] === 'closed') {
            return redirect()->to(Portal::url())->with('lgc_err', lang('Lgc.ptNotFound'));
        }
        $threadId = (int) $this->request->getPost('thread_id') ?: null;
        $r = (new MessageService())->clientWrites($account, $matter, $threadId, (string) $this->request->getPost('subject'), (string) $this->request->getPost('body'));

        return redirect()->to(Portal::url('asunto/' . (int) $matter['id']) . '#mensajes')->with($r->ok ? 'lgc_ok' : 'lgc_err', $r->message);
    }

    public function upload($requestId = null)
    {
        $account = $this->requireAccount();
        if (!$account) {
            return redirect()->to(Portal::url());
        }
        $req = $this->view->request($account, (int) $requestId);
        if (!$req) {
            return redirect()->to(Portal::url())->with('lgc_err', lang('Lgc.ptNotFound'));
        }
        $matter = $this->view->matter($account, (int) $req['matter_id']);
        $back   = Portal::url('asunto/' . (int) $matter['id']) . '#pendientes';

        $file = $this->request->getFile('file');
        if (!$file) {
            return redirect()->to($back)->with('lgc_err', lang('Lgc.errFile'));
        }
        $r = (new DocumentService())->store($file, (int) $account['company_id'], (int) $matter['id'], [
            'source' => 'client', 'classification' => 'review', 'request_id' => (int) $req['id'], 'account_id' => (int) $account['id'],
        ]);
        if ($r->ok) {
            (new MessageService())->markSubmitted($req, $matter, (int) $account['id']);
        }

        return redirect()->to($back)->with($r->ok ? 'lgc_ok' : 'lgc_err', $r->ok ? lang('Lgc.ptDelivered') : $r->message);
    }

    public function download($id = null)
    {
        $account = $this->requireAccount();
        if (!$account) {
            return redirect()->to(Portal::url());
        }
        $doc = $this->view->document($account, (int) $id);
        $path = $doc ? (new DocumentService())->absolutePath($doc) : '';
        if (!$doc || !is_file($path)) {
            return $this->response->setStatusCode(404)->setBody($this->page('invalid', ['title' => lang('Lgc.ptNotFound'), 'notFound' => true], $account));
        }
        Audit::log((int) $account['company_id'], (int) $doc['matter_id'], 'portal.downloaded', 'document', (int) $doc['id'], [], 'portal', (int) $account['id']);

        return $this->response->download($path, null)->setFileName($doc['name'])
            ->setHeader('X-Content-Type-Options', 'nosniff')->setHeader('Cache-Control', 'no-store, private');
    }

    /** POST — idioma y avisos por correo (consentimiento revocable, §6.13-6). */
    public function preferences()
    {
        $account = $this->requireAccount();
        if (!$account) {
            return redirect()->to(Portal::url());
        }
        $locale = (string) $this->request->getPost('locale');
        $data   = [
            'locale'       => in_array($locale, ['es', 'en'], true) ? $locale : $account['locale'],
            'notify_email' => $this->request->getPost('notify_email') ? 1 : 0,
        ];
        (new PortalAccountModel())->update((int) $account['id'], $data);
        Audit::log((int) $account['company_id'], null, 'portal.preferences', 'account', (int) $account['id'], $data, 'portal', (int) $account['id']);

        $this->request->setLocale($data['locale']);

        return redirect()->to(Portal::url())->with('lgc_ok', lang('Lgc.ptPrefsSaved', [], $data['locale']));
    }
}
