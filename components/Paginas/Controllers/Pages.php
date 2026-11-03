<?php

namespace Components\Paginas\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataTable;
use Components\Paginas\Libraries\BlockRegistry;
use Components\Paginas\Libraries\DemoData;
use Components\Paginas\Libraries\PageService;
use Components\Paginas\Libraries\PlanGuard;
use Components\Paginas\Libraries\Renderer;
use Components\Paginas\Libraries\SectionLibrary;
use Components\Paginas\Models\BlockModel;
use Components\Paginas\Models\PageModel;
use Components\Paginas\Models\SubmissionModel;

/**
 * Páginas de la empresa activa: listado, ajustes, editor, publicación y bajas.
 *
 * El controlador es fino a propósito: el saneado del árbol, la dirección única y
 * el duplicado están en `PageService`, y el plan en `PlanGuard`. Aquí solo se
 * resuelve el contexto (empresa activa), se comprueba la **pertenencia** —tener
 * el permiso no autoriza a tocar la página de otra empresa— y se redirige.
 */
class Pages extends BaseController
{
    private PageModel $model;
    private PageService $svc;

    public function __construct()
    {
        $this->model = new PageModel();
        $this->svc   = new PageService();
    }

    /** Empresa activa. null = contexto Global (sin empresa). */
    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** Página de la empresa activa, o null: el permiso no basta, hay que ser dueño. */
    private function own(int $id): ?array
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return null;
        }
        $row = $this->model->find($id);

        return $row && (int) $row['company_id'] === $companyId ? $row : null;
    }

    // ------------------------------------------------------------- listado

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('Components\Paginas\Views\index', ['title' => lang('Pag.pages'), 'dt' => null]);
        }

        $status = (string) $this->request->getGet('status');
        $guard  = new PlanGuard();
        $svc    = $this->svc;

        $dt = (new DataTable($this->model, $this->request))
            ->searchable(['title', 'slug'])
            ->sortable(['title', 'slug', 'status', 'views', 'created_at'])
            ->defaultSort('created_at', 'desc')
            ->query(static function ($m) use ($companyId, $status) {
                $m->where('company_id', $companyId);
                if (in_array($status, PageModel::STATUSES, true)) {
                    $m->where('status', $status);
                }
            })
            ->paginate(10);

        return view('Components\Paginas\Views\index', [
            'title'       => lang('Pag.pages'),
            'dt'          => $dt,
            'status'      => $status,
            'extraParams' => array_filter(['status' => $status ?: null]),
            'planEnabled' => $guard->serviceEnabled(),
            'quota'       => $guard->pagesLeft(),
            'unread'      => (new SubmissionModel())->unreadCount($companyId),
            'companySlug' => $this->companySlug($companyId),
            'countBlocks' => static fn (array $p): int => $svc->countBlocks(is_array($p['blocks'] ?? null) ? $p['blocks'] : []),
            'showDemo'    => (new DemoData())->isEmpty($companyId),
            // El diálogo de «Nueva página» ofrece empezar en blanco o partir
            // de una plantilla guardada. Van aquí y no en otra pantalla
            // porque elegir plantilla ES crear la página.
            'templates'   => (new \Components\Paginas\Models\TemplateModel())->forCompany($companyId),
        ]);
    }

    // --------------------------------------------------------- alta y ajustes

    /**
     * Alta: **entra directo al editor**.
     *
     * Antes había una pantalla que solo pedía el título, y era un trámite: lo
     * mismo se edita en la pestaña «Página» del inspector, sin salir de lo que
     * se está montando. La página nace con un título provisional y el editor
     * recuerda ponerle uno al guardar.
     */
    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('paginas')->with('error', lang('Pag.errNoCompany'));
        }

        if ($reason = (new PlanGuard())->blockedReason($companyId)) {
            return redirect()->to('paginas')->with('error', $reason);
        }

        return $this->store($companyId);
    }

    private function store(int $companyId)
    {
        // Sin pantalla intermedia no hay título que recoger: se pone el
        // provisional, que es el que dispara el recordatorio del editor.
        $title = trim((string) $this->request->getPost('title')) ?: lang('Pag.untitled');
        $slug  = $this->svc->uniqueSlug($companyId, (string) ($this->request->getPost('slug') ?: $title));

        $data = [
            'company_id'       => $companyId,
            'user_id'          => (int) session()->get('user_id'),
            'title'            => $title,
            'slug'             => $slug,
            'status'           => 'draft',
            'blocks'           => [],
            'settings'         => [],
            'meta_title'       => trim((string) $this->request->getPost('meta_title')),
            'meta_description' => trim((string) $this->request->getPost('meta_description')),
        ];

        if (!$this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        // Recién creada no hay nada que ver: se entra directo al editor.
        return redirect()->to('paginas/editor/' . (int) $this->model->getInsertID())
            ->with('success', lang('Pag.okCreated'));
    }

    /*
     * La pantalla de ajustes (`ajustes/{id}`) SE RETIRÓ.
     *
     * Título, dirección, SEO, imagen al compartir, acento y anchos se editan en
     * la pestaña «Página» del inspector, dentro del editor, y se guardan por
     * `savePageSettings()`. Tener las dos cosas obligaba a mantener el mismo
     * formulario por duplicado y a salir del editor para cambiar un ancho.
     */

    // ---------------------------------------------------------------- editor

    /** El editor drag & drop. Recibe el árbol y el catálogo de bloques. */
    public function editor($id = null)
    {
        $page = $this->own((int) $id);
        if (!$page) {
            return redirect()->to('paginas')->with('error', lang('Pag.errNotFound'));
        }

        return view('Components\Paginas\Views\editor', [
            'title'       => $page['title'],
            'page'        => $page,
            'tree'        => is_array($page['blocks'] ?? null) ? $page['blocks'] : [],
            'registry'    => BlockRegistry::forJs(),
            'groups'      => BlockRegistry::GROUPS,
            // Secciones prearmadas (banner, tarjetas, pie…): van en código, no
            // en BD, porque no son de ninguna empresa. Ver SectionLibrary.
            'sections'      => SectionLibrary::forJs(),
            'sectionGroups' => SectionLibrary::GROUPS,
            // Los bloques de empresa se dibujan con datos reales en el lienzo.
            // Va una copia recortada: solo lo que esos bloques pintan, nada de
            // plan, dueño ni identificadores internos.
            'company'     => $this->publicCompany($this->company((int) $page['company_id'])),
            'saved'       => (new BlockModel())->forCompany((int) $page['company_id']),
            'saveUrl'     => base_url('paginas/editor/' . (int) $page['id']),
            'uploadUrl'   => base_url('paginas/subir'),
            'blockUrl'    => base_url('paginas/bloques/guardar'),
            'previewUrl'  => base_url('paginas/previsualizar/' . (int) $page['id']),
            // La dirección pública se enseñaba en la pantalla de ajustes, que se
            // retiró: es lo que el usuario copia para compartir, así que viaja
            // aquí para pintarla bajo el campo de la dirección.
            'companySlug'  => $this->companySlug((int) $page['company_id']),
            'canSaveBlock' => $this->hasPermission('pag.configure'),
        ]);
    }

    /**
     * GET paginas/token — un token de CSRF fresco para el editor.
     *
     * El token **rota en cada POST** (`Config\Security::$regenerate`), y el
     * editor guarda el suyo en memoria: basta con que otra pestaña de la
     * aplicación haga un POST para que el del editor deje de valer y todos
     * los guardados respondan 403. Con esto el editor pide uno nuevo y
     * reintenta, en vez de obligar a recargar perdiendo lo que no se guardó.
     *
     * Es un GET: no consume ni rota nada, solo devuelve el hash vigente. No
     * debilita la protección —quien lo pide ya tiene sesión y el permiso, que
     * es exactamente lo que el token supone—.
     */
    public function token()
    {
        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /**
     * POST del editor: guarda el árbol. Responde JSON porque el editor guarda
     * sin recargar.
     *
     * El árbol **se reconstruye** contra `BlockRegistry` (`sanitizeTree()`): lo
     * que llega del navegador nunca se guarda tal cual.
     */
    public function saveBlocks($id = null)
    {
        $page = $this->own((int) $id);
        if (!$page) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => lang('Pag.errNotFound')]);
        }

        $raw  = $this->request->getJSON(true);
        $tree = $this->svc->sanitizeTree($raw['blocks'] ?? []);

        $ok = $this->model->update((int) $page['id'], ['blocks' => $tree]);

        return $this->response->setJSON([
            'ok'     => (bool) $ok,
            'blocks' => $tree,          // el editor se re-sincroniza con lo guardado
            'count'  => $this->svc->countBlocks($tree),
            'error'  => $ok ? '' : lang('Pag.errSaveFailed'),
            // La página sigue con el título provisional: se recuerda, no se
            // bloquea. Lo que se está guardando es el contenido, y obligar a
            // nombrarla antes de poder guardar sería pelearse con el trabajo.
            'needsTitle' => trim((string) $page['title']) === ''
                || $page['title'] === lang('Pag.untitled'),
            // CSRF se regenera en cada petición: el editor necesita el nuevo.
            'csrf'   => csrf_hash(),
        ]);
    }

    /**
     * POST paginas/editor/{id}/ajustes — los ajustes de la página **sin salir
     * del editor**. Responde JSON.
     *
     * Guarda exactamente lo mismo que la pantalla `ajustes/{id}` y por las
     * mismas reglas: el título y la dirección pasan por el modelo, la dirección
     * se hace única dentro de la empresa y las medidas se acotan en
     * `layoutSettings()`. Lo único que cambia es que aquí se contesta con datos
     * en vez de con una redirección.
     */
    public function savePageSettings($id = null)
    {
        $page = $this->own((int) $id);
        if (!$page) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => lang('Pag.errNotFound')]);
        }

        $r     = $this->request;
        $title = trim((string) $r->getPost('title'));
        $slug  = $this->svc->uniqueSlug(
            (int) $page['company_id'],
            (string) ($r->getPost('slug') ?: $title),
            (int) $page['id']
        );

        $data = [
            'title'            => $title,
            'slug'             => $slug,
            'meta_title'       => trim((string) $r->getPost('meta_title')),
            'meta_description' => trim((string) $r->getPost('meta_description')),
            'settings'         => $this->layoutSettings($r),
        ];

        // La imagen para compartir se sube antes por `Media::upload` y aquí
        // llega ya como referencia; vacío = se conserva la que hubiera.
        $og = trim((string) $r->getPost('og_image'));
        if ($og !== '') {
            $data['og_image'] = $og;
        }

        if (!$this->model->update((int) $page['id'], $data)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'    => false,
                'error' => implode(' ', $this->model->errors()),
                'csrf'  => csrf_hash(),
            ]);
        }

        return $this->response->setJSON([
            'ok'       => true,
            'title'    => $title,
            'slug'     => $slug,
            'settings' => $data['settings'],
            'csrf'     => csrf_hash(),
        ]);
    }

    /** Vista previa: la página renderizada por el `Renderer`, la salida buena. */
    public function preview($id = null)
    {
        $page = $this->own((int) $id);
        if (!$page) {
            return redirect()->to('paginas')->with('error', lang('Pag.errNotFound'));
        }

        $company  = $this->company((int) $page['company_id']);
        $settings = is_array($page['settings'] ?? null) ? $page['settings'] : [];
        $renderer = new Renderer($settings, [
            'editor'  => true,
            'company' => $company,
            // La URL que tendrá cuando se publique: así el QR de «esta página»
            // ya se puede escanear desde la vista previa.
            'pageUrl' => base_url('pagina/' . rawurlencode((string) ($company['slug'] ?? ''))
                . '/' . rawurlencode((string) $page['slug'])),
        ]);

        return view('Components\Paginas\Views\public\page', [
            'page'      => $page,
            'company'   => $company,
            'settings'  => $settings,
            'body'      => $renderer->render(is_array($page['blocks'] ?? null) ? $page['blocks'] : []),
            'isPreview' => true,
        ]);
    }

    // -------------------------------------------------------------- acciones

    /** POST paginas/estado/{id} — publicar o volver a borrador. */
    public function changeStatus($id = null)
    {
        $page = $this->own((int) $id);
        if (!$page) {
            return redirect()->to('paginas')->with('error', lang('Pag.errNotFound'));
        }

        $status = (string) $this->request->getPost('status');
        if (!in_array($status, PageModel::STATUSES, true)) {
            return redirect()->back()->with('error', lang('Pag.errBadStatus'));
        }

        // Publicar con el servicio apagado dejaría una URL viva que el plan no
        // cubre; volver a borrador siempre se permite.
        if ($status === 'published' && !(new PlanGuard())->serviceEnabled()) {
            return redirect()->back()->with('error', lang('Pag.errPlanDisabled'));
        }

        $data = ['status' => $status];
        if ($status === 'published' && empty($page['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        $this->model->update((int) $page['id'], $data);

        return redirect()->to('paginas')
            ->with('success', $status === 'published' ? lang('Pag.okPublished') : lang('Pag.okUnpublished'));
    }

    /** POST paginas/duplicar/{id} — copia con ids nuevos y en borrador. */
    public function duplicate($id = null)
    {
        $page = $this->own((int) $id);
        if (!$page) {
            return redirect()->to('paginas')->with('error', lang('Pag.errNotFound'));
        }

        $companyId = (int) $page['company_id'];
        if ($reason = (new PlanGuard())->blockedReason($companyId)) {
            return redirect()->to('paginas')->with('error', $reason);
        }

        $title = $page['title'] . ' ' . lang('Pag.copySuffix');
        $this->model->insert([
            'company_id'       => $companyId,
            'user_id'          => (int) session()->get('user_id'),
            'title'            => mb_substr($title, 0, 180),
            'slug'             => $this->svc->uniqueSlug($companyId, $page['slug']),
            'status'           => 'draft',
            // Ids nuevos: dos bloques con el mismo id romperían la bandeja de
            // mensajes (no se sabría de qué formulario vino cada uno).
            'blocks'           => $this->svc->reindex(is_array($page['blocks'] ?? null) ? $page['blocks'] : []),
            'settings'         => is_array($page['settings'] ?? null) ? $page['settings'] : [],
            'meta_title'       => (string) $page['meta_title'],
            'meta_description' => (string) $page['meta_description'],
            'og_image'         => $page['og_image'],
        ]);

        return redirect()->to('paginas')->with('success', lang('Pag.okDuplicated'));
    }

    public function delete($id = null)
    {
        $page = $this->own((int) $id);
        if (!$page) {
            return redirect()->to('paginas')->with('error', lang('Pag.errNotFound'));
        }
        $this->model->delete((int) $page['id']);

        return redirect()->to('paginas')->with('success', lang('Pag.okDeleted'));
    }

    /** POST paginas/demo — páginas de ejemplo para ver el componente funcionando. */
    public function seedDemo()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->to('paginas')->with('error', lang('Pag.errNoCompany'));
        }

        $guard = new PlanGuard();
        if (!$guard->serviceEnabled($companyId)) {
            return redirect()->to('paginas')->with('error', lang('Pag.errPlanDisabled'));
        }

        $demo = new DemoData();
        if (!$demo->isEmpty($companyId)) {
            return redirect()->to('paginas')->with('error', lang('Pag.errDemoNotEmpty'));
        }

        $left = $guard->pagesLeft($companyId);
        if ($left !== null && $left < DemoData::PAGES) {
            return redirect()->to('paginas')->with('error', lang('Pag.errDemoQuota'));
        }

        $res = $demo->generate($companyId, (int) session()->get('user_id'));

        return redirect()->to('paginas')->with('success', lang('Pag.okDemo', [$res['pages'], $res['messages']]));
    }

    // -------------------------------------------------------------- utilidad

    /**
     * Ajustes de presentación de la página, saneados.
     *
     * Los **dos anchos** son el equivalente al `contentSize`/`wideSize` del
     * `theme.json` de Gutenberg: cada sección elige entre ellos (o se va a todo
     * lo ancho), así que cambiarlos aquí recompone la página entera. Se acotan
     * en el servidor porque acaban en un `max-width` del CSS, y el amplio nunca
     * puede quedar por debajo del de contenido —sería un ancho «amplio» más
     * estrecho que el normal—.
     */
    private function layoutSettings($r): array
    {
        $content = (int) $r->getPost('contentWidth') ?: 1100;
        $content = max(360, min(1600, $content));

        $wide = (int) $r->getPost('wideWidth') ?: (int) round($content * 1.25);
        $wide = max($content, min(1920, $wide));

        return [
            'accent' => preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $r->getPost('accent'))
                ? (string) $r->getPost('accent') : '',
            'contentWidth' => (string) $content,
            'wideWidth'    => (string) $wide,
            'header'       => $r->getPost('header') ? '1' : '0',
            'footer'       => $r->getPost('footer') ? '1' : '0',
        ];
    }

    /** @return array|null la empresa (para la marca de la página) */
    private function company(int $id): ?array
    {
        return (new \App\Models\CompanyModel())->find($id);
    }

    /**
     * La empresa recortada a lo que los bloques pintan, ya con las imágenes
     * resueltas. Es lo único que viaja al navegador en el editor: el registro
     * completo trae plan, dueño y auditoría que no pintan nada ahí.
     */
    private function publicCompany(?array $c): array
    {
        if (!$c) {
            return [];
        }
        helper('media');

        $out = [];

        foreach (['name', 'nit', 'slug', 'description', 'address', 'city', 'website',
            'contact_email', 'contact_phone', 'whatsapp', 'brand_color',
            'facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'] as $f) {
            $out[$f] = (string) ($c[$f] ?? '');
        }
        foreach (['logo', 'banner'] as $f) {
            $out[$f] = !empty($c[$f]) ? media_url($c[$f]) : '';
        }

        return $out;
    }

    /** Slug de la empresa: es la primera parte de la URL pública. */
    private function companySlug(int $companyId): string
    {
        return (string) ($this->company($companyId)['slug'] ?? '');
    }

    /** ¿El usuario en sesión tiene el permiso? (para ocultar acciones). */
    private function hasPermission(string $permission): bool
    {
        return (new \App\Models\UserModel())->hasPermission((int) session()->get('user_id'), $permission);
    }
}
