<?php

namespace Components\Paginas\Controllers;

use App\Controllers\BaseController;
use App\Models\CompanyModel;
use Components\Paginas\Libraries\PageService;
use Components\Paginas\Libraries\PlanGuard;
use Components\Paginas\Libraries\Renderer;
use Components\Paginas\Models\PageModel;
use Components\Paginas\Models\SubmissionModel;

/**
 * La página publicada: `/pagina/{empresa}/{pagina}`.
 *
 * Sin sesión —es una web pública—, pero **sí con reglas**: solo responde si la
 * página está publicada y si el plan de la empresa incluye el servicio. Ese
 * segundo control es enforcement de verdad (contrato §11.3): si estuviera solo
 * en el menú, una empresa cuyo plan ya no incluye páginas seguiría con sus URL
 * públicas vivas.
 *
 * CSRF sigue activo (el filtro es global), así que el formulario de contacto
 * lleva su token como cualquier otro POST del proyecto.
 */
class PublicPage extends BaseController
{
    private PageModel $pages;
    private PageService $svc;

    public function __construct()
    {
        $this->pages = new PageModel();
        $this->svc   = new PageService();
    }

    /**
     * Resuelve empresa + página publicada, o null si algo no cuadra.
     *
     * @return array{company:array,page:array}|null
     */
    private function resolve(?string $companySlug, ?string $pageSlug): ?array
    {
        $companySlug = trim((string) $companySlug);
        $pageSlug    = trim((string) $pageSlug);
        if ($companySlug === '' || $pageSlug === '') {
            return null;
        }

        $company = (new CompanyModel())->where('slug', $companySlug)->first();
        if (!$company) {
            return null;
        }

        $page = $this->pages->published((int) $company['id'], $pageSlug);
        if (!$page) {
            return null;
        }

        // El plan manda también sobre lo ya publicado.
        if (!(new PlanGuard())->serviceEnabled((int) $company['id'])) {
            return null;
        }

        return ['company' => $company, 'page' => $page];
    }

    public function show($companySlug = null, $pageSlug = null)
    {
        $found = $this->resolve($companySlug, $pageSlug);
        if (!$found) {
            return $this->notFound();
        }

        ['company' => $company, 'page' => $page] = $found;

        $this->pages->countView((int) $page['id']);

        $settings = is_array($page['settings'] ?? null) ? $page['settings'] : [];
        $pageUrl  = base_url('pagina/' . rawurlencode($company['slug']) . '/' . rawurlencode($page['slug']));
        $renderer = new Renderer($settings, [
            // Los bloques de empresa leen de aquí en vez de pedir los datos otra
            // vez; `pageUrl` es lo que codifica el QR «esta página».
            'company'    => $company,
            'pageUrl'    => $pageUrl,
            'formAction' => $pageUrl . '/enviar',
            'csrf'       => csrf_field(),
            // Tras enviar, el acuse se pinta dentro del propio bloque.
            'sentBlock'      => (string) (session()->getFlashdata('pag_sent') ?? ''),
            'formErrorBlock' => (string) (session()->getFlashdata('pag_error_block') ?? ''),
            'formError'      => (string) (session()->getFlashdata('pag_error') ?? ''),
        ]);

        return view('Components\Paginas\Views\public\page', [
            'page'     => $page,
            'company'  => $company,
            'settings' => $settings,
            'body'     => $renderer->render(is_array($page['blocks'] ?? null) ? $page['blocks'] : []),
        ]);
    }

    /** POST del formulario de contacto de la página. */
    public function submit($companySlug = null, $pageSlug = null)
    {
        $found = $this->resolve($companySlug, $pageSlug);
        if (!$found) {
            return $this->notFound();
        }

        ['company' => $company, 'page' => $page] = $found;
        $back = 'pagina/' . rawurlencode($company['slug']) . '/' . rawurlencode($page['slug']);

        $uid   = (string) $this->request->getPost('block');
        $block = $uid !== ''
            ? $this->svc->findBlock(is_array($page['blocks'] ?? null) ? $page['blocks'] : [], $uid)
            : null;

        // El bloque tiene que existir EN ESTA página y ser un formulario: sin
        // esto, un `block` inventado en el POST crearía mensajes fantasma.
        if (!$block || ($block['type'] ?? '') !== 'form') {
            return redirect()->to($back)->with('pag_error', lang('Pag.formError'))
                ->with('pag_error_block', $uid);
        }

        $result = $this->svc->collectSubmission($block, $this->request->getPost() ?? []);

        if (!$result['ok']) {
            return redirect()->to($back)
                ->with('pag_error', $result['error'])
                ->with('pag_error_block', $uid);
        }

        (new SubmissionModel())->insert([
            'company_id' => (int) $company['id'],
            'page_id'    => (int) $page['id'],
            'block_uid'  => $uid,
            'form_title' => mb_substr($result['title'], 0, 180),
            'data'       => $result['data'],
            'ip'         => (string) $this->request->getIPAddress(),
            'is_read'    => 0,
        ]);

        return redirect()->to($back . '#' . $uid)->with('pag_sent', $uid);
    }

    /**
     * 404 con el lienzo del componente, no el del núcleo: la página pública es
     * autosuficiente (§7.3) y su error también.
     */
    private function notFound()
    {
        return $this->response->setStatusCode(404)
            ->setBody(view('Components\Paginas\Views\public\not_found'));
    }
}
