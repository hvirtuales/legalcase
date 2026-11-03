<?php

namespace Components\LeadFlow\Controllers;

use App\Controllers\BaseController;
use Components\LeadFlow\Libraries\Captcha;
use Components\LeadFlow\Libraries\Directory\ComplaintService;
use Components\LeadFlow\Libraries\Directory\InquiryService;
use Components\LeadFlow\Libraries\Directory\OpportunityService;
use Components\LeadFlow\Libraries\Directory\PublicDirectory;
use Components\LeadFlow\Libraries\Directory\ReviewService;
use Components\LeadFlow\Libraries\Directory\Taxonomy;
use Components\LeadFlow\Libraries\QrService;
use Components\LeadFlow\Models\AreaModel;
use Components\LeadFlow\Models\ProfileModel;

/**
 * El directorio público: portada, buscador, landing por área, perfil y la consulta
 * que se convierte en lead.
 *
 * **Sin sesión.** Lo que se puede ver lo decide `PublicDirectory` —estado del perfil
 * y plan de su empresa—, en un solo sitio, y nada más. Un perfil que no está
 * publicado devuelve 404 igual que uno que no existe: distinguirlos convertiría la
 * URL en una sonda de quién ha contratado qué.
 *
 * Los filtros viajan por GET, así que una búsqueda es un enlace que se puede
 * compartir e indexar, y la vista recibe su canónica.
 */
class Directory extends BaseController
{
    private function dir(): PublicDirectory
    {
        Taxonomy::ensure();

        return new PublicDirectory();
    }

    private function notFound(string $title)
    {
        return $this->response->setStatusCode(404)->setBody(
            view('Components\LeadFlow\Views\public\directory\notfound', ['pageTitle' => $title])
        );
    }

    /** Filtros saneados, tal como los entiende el buscador. */
    private function filters(): array
    {
        $r = $this->request;

        return [
            'q'            => mb_substr(trim((string) $r->getGet('q')), 0, 80),
            'area'         => (int) $r->getGet('area'),
            'jurisdiction' => (int) $r->getGet('jurisdiccion'),
            'language'     => mb_substr(trim((string) $r->getGet('idioma')), 0, 20),
            'mode'         => in_array($r->getGet('modalidad'), ProfileModel::MODES, true) ? (string) $r->getGet('modalidad') : '',
            'city'         => mb_substr(trim((string) $r->getGet('ciudad')), 0, 80),
        ];
    }

    /** Portada: las ocho secciones del §11 del insumo de contenidos. */
    public function home()
    {
        $dir = $this->dir();

        return view('Components\LeadFlow\Views\public\directory\home', [
            'pageTitle'   => lang('Lfl.dirBrand') . ' — ' . lang('Lfl.dirHeroTitle'),
            'description' => lang('Lfl.dirMetaHome'),
            'canonical'   => base_url('/'),
            'robots'      => 'index, follow',
            'areas'       => $dir->areasWithCounts(),
            'latest'      => $dir->latest(6),
            'totals'      => $dir->totals(),
            'cities'      => $dir->cities(),
        ]);
    }

    /** Buscador con sus resultados. */
    public function find()
    {
        $dir     = $this->dir();
        $filters = $this->filters();
        $page    = (int) $this->request->getGet('pagina');

        return view('Components\LeadFlow\Views\public\directory\find', [
            'pageTitle'   => lang('Lfl.dirFindTitle'),
            'description' => lang('Lfl.dirMetaFind'),
            'canonical'   => base_url('buscar'),
            'robots'      => 'index, follow',
            'result'      => $dir->search($filters, $page),
            'filters'     => $filters,
            'areas'       => $dir->areaOptions(),
            'jurs'        => $dir->jurisdictionOptions(),
            'cities'      => $dir->cities(),
        ]);
    }

    /**
     * Landing de un área: «abogados de inmigración».
     *
     * Es la misma búsqueda acotada, pero con su propia URL y su propio título: es lo
     * que se puede indexar y anunciar, y lo que contesta la pregunta con la que la
     * gente llega a un buscador.
     */
    public function area($slug = null)
    {
        $dir  = $this->dir();
        $area = (new AreaModel())->bySlug((string) $slug);
        if (! $area) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        return view('Components\LeadFlow\Views\public\directory\find', [
            'pageTitle'   => lang('Lfl.dirAreaTitle', [$area['name']]),
            'description' => lang('Lfl.dirMetaArea', [$area['name']]),
            'canonical'   => base_url('area/' . rawurlencode((string) $area['slug'])),
            'robots'      => 'index, follow',
            'area'        => $area,
            'result'      => $dir->search(['area' => (int) $area['id']], (int) $this->request->getGet('pagina')),
            'filters'     => ['q' => '', 'area' => (int) $area['id'], 'jurisdiction' => 0, 'language' => '', 'mode' => '', 'city' => ''],
            'areas'       => $dir->areaOptions(),
            'jurs'        => $dir->jurisdictionOptions(),
            'cities'      => $dir->cities(),
        ]);
    }

    /** El perfil público. */
    public function profile($slug = null)
    {
        $dir     = $this->dir();
        $profile = $dir->profile((string) $slug);
        if (! $profile) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        // Una visita más. Sin validación porque no la necesita y sin tocar
        // `updated_at`: la fecha de actualización es del contenido, no del contador.
        (new ProfileModel())->skipValidation(true)->protect(false)
            ->update((int) $profile['id'], ['views_count' => (int) $profile['views_count'] + 1]);

        return view('Components\LeadFlow\Views\public\directory\profile', [
            'pageTitle'   => $profile['display_name'] . ' — ' . lang('Lfl.dirBrand'),
            'description' => $profile['seo_description'] ?: mb_substr((string) ($profile['headline'] ?: ''), 0, 200),
            'canonical'   => base_url('abogado/' . rawurlencode((string) $profile['slug'])),
            'robots'      => 'index, follow',
            'profile'     => $profile,
        ]);
    }

    /** El formulario de consulta de un perfil (GET) y su envío (POST). */
    public function inquiry($slug = null)
    {
        $dir     = $this->dir();
        $profile = $dir->profile((string) $slug);
        if (! $profile) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            $res = (new InquiryService())->submit($profile, [
                'first_name' => (string) $this->request->getPost('first_name'),
                'last_name'  => (string) $this->request->getPost('last_name'),
                'email'      => (string) $this->request->getPost('email'),
                'phone'      => (string) $this->request->getPost('phone'),
                'city'       => (string) $this->request->getPost('city'),
                'area_id'    => (int) $this->request->getPost('area_id'),
                'message'    => (string) $this->request->getPost('message'),
                'consent'    => (bool) $this->request->getPost('consent'),
                'website'    => (string) $this->request->getPost('website'),
                'stamp'      => (string) $this->request->getPost('stamp'),
                'captcha'    => (string) $this->request->getPost('captcha'),
                'ip'         => (string) $this->request->getIPAddress(),
                'landing'    => base_url('abogado/' . rawurlencode((string) $profile['slug'])),
            ]);

            if (! $res['ok']) {
                return redirect()->back()->withInput()->with('dir_error', $res['message']);
            }

            return redirect()->to(base_url('abogado/' . rawurlencode((string) $profile['slug']) . '/consulta/listo'));
        }

        return view('Components\LeadFlow\Views\public\directory\inquiry', [
            'pageTitle'   => lang('Lfl.dirInquiryTitle', [$profile['display_name']]),
            'description' => '',
            'canonical'   => base_url('abogado/' . rawurlencode((string) $profile['slug']) . '/consulta'),
            'profile'     => $profile,
            'stamp'       => (new Captcha())->stamp(),
            'hasGd'       => extension_loaded('gd'),
        ]);
    }


    public function done($slug = null)
    {
        $profile = $this->dir()->profile((string) $slug);
        if (! $profile) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        return view('Components\LeadFlow\Views\public\directory\done', [
            'pageTitle' => lang('Lfl.dirDoneTitle'),
            'profile'   => $profile,
        ]);
    }

    /**
     * «Buscar orientación legal»: el formulario de quien **no sabe a quién acudir**.
     *
     * No va a ninguna firma: crea una oportunidad abierta que reclaman las firmas del
     * área. Es el primer problema del §5 del insumo, y la única vía de captación que no
     * exige que el visitante elija primero.
     */
    public function orientation()
    {
        $dir = $this->dir();

        if (strtolower($this->request->getMethod()) === 'post') {
            $res = (new OpportunityService())->submit([
                'area_id'         => (int) $this->request->getPost('area_id'),
                'jurisdiction_id' => (int) $this->request->getPost('jurisdiction_id'),
                'language'        => (string) $this->request->getPost('language'),
                'city'            => (string) $this->request->getPost('city'),
                'summary'         => (string) $this->request->getPost('summary'),
                'first_name'      => (string) $this->request->getPost('first_name'),
                'last_name'       => (string) $this->request->getPost('last_name'),
                'email'           => (string) $this->request->getPost('email'),
                'phone'           => (string) $this->request->getPost('phone'),
                'consent'         => (bool) $this->request->getPost('consent'),
                'website'         => (string) $this->request->getPost('website'),
                'stamp'           => (string) $this->request->getPost('stamp'),
                'captcha'         => (string) $this->request->getPost('captcha'),
                'ip'              => (string) $this->request->getIPAddress(),
            ]);

            if (! $res['ok']) {
                return redirect()->back()->withInput()->with('dir_error', $res['message']);
            }

            return redirect()->to(base_url('orientacion/listo'));
        }

        return view('Components\LeadFlow\Views\public\directory\orientation', [
            'pageTitle'   => lang('Lfl.oppTitle') . ' · ' . lang('Lfl.dirBrand'),
            'description' => lang('Lfl.oppMeta'),
            'canonical'   => base_url('orientacion'),
            'robots'      => 'index, follow',
            'areas'       => $dir->areaOptions(),
            'jurs'        => $dir->jurisdictionOptions(),
            'stamp'       => (new Captcha())->stamp(),
            'hasGd'       => extension_loaded('gd'),
        ]);
    }


    /** Acuse de la oportunidad: qué pasa ahora y cuántos despachos pueden escribirle. */
    public function orientationDone()
    {
        return view('Components\LeadFlow\Views\public\directory\orientation_done', [
            'pageTitle' => lang('Lfl.oppDoneTitle') . ' · ' . lang('Lfl.dirBrand'),
            'robots'    => 'noindex, follow',
            'maxClaims' => \Components\LeadFlow\Models\OpportunityModel::MAX_CLAIMS,
        ]);
    }

    /** La imagen del captcha del formulario de orientación. */
    public function orientationCaptcha()
    {
        $c = new Captcha();

        return $this->response->setJSON(['img' => $c->image($c->issue())]);
    }

    /**
     * La opinión del cliente: `/opinion/{token}`.
     *
     * Entra sin cuenta, con el token que le pasó su abogado. El 404 no distingue entre
     * «no existe», «ya opinaste» y «el plan no incluye opiniones»: distinguirlos
     * convertiría la URL en una sonda.
     */
    public function review($token = null)
    {
        $svc    = new ReviewService();
        $invite = $svc->pendingByToken((string) $token);
        if (! $invite) {
            return $this->notFound(lang('Lfl.revNotFoundTitle'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            $res = $svc->submit($invite, [
                'score_clarity'  => (int) $this->request->getPost('score_clarity'),
                'score_response' => (int) $this->request->getPost('score_response'),
                'score_care'     => (int) $this->request->getPost('score_care'),
                'comment'        => (string) $this->request->getPost('comment'),
                'author_mode'    => (string) $this->request->getPost('author_mode'),
                'consent'        => (bool) $this->request->getPost('consent'),
                'website'        => (string) $this->request->getPost('website'),
                'stamp'          => (string) $this->request->getPost('stamp'),
                'captcha'        => (string) $this->request->getPost('captcha'),
                'ip'             => (string) $this->request->getIPAddress(),
            ]);

            if (! $res['ok']) {
                return redirect()->back()->withInput()->with('dir_error', $res['message']);
            }

            return redirect()->to(base_url('opinion/' . rawurlencode((string) $token) . '/gracias'));
        }

        $profile = ! empty($invite['profile_id'])
            ? (new ProfileModel())->find((int) $invite['profile_id'])
            : null;

        return view('Components\LeadFlow\Views\public\directory\review', [
            'pageTitle' => lang('Lfl.revTitle') . ' · ' . lang('Lfl.dirBrand'),
            // Con token y datos de una persona: no se indexa nunca.
            'robots'    => 'noindex, nofollow',
            'invite'    => $invite,
            'profile'   => $profile,
            'scores'    => \Components\LeadFlow\Models\ReviewModel::SCORES,
            'stamp'     => (new Captcha())->stamp(),
            'hasGd'     => extension_loaded('gd'),
        ]);
    }

    /** Acuse: lo que pasa ahora con lo que acaba de escribir. */
    public function reviewDone($token = null)
    {
        return view('Components\LeadFlow\Views\public\directory\review_done', [
            'pageTitle' => lang('Lfl.revThanksTitle') . ' · ' . lang('Lfl.dirBrand'),
            'robots'    => 'noindex, nofollow',
        ]);
    }

    /** El captcha del formulario de opinión. */
    public function reviewCaptcha()
    {
        $c = new Captcha();

        return $this->response->setJSON(['img' => $c->image($c->issue())]);
    }

    /** El listado de firmas con presencia en el directorio. */
    public function firms()
    {
        $dir = $this->dir();

        return view('Components\LeadFlow\Views\public\directory\firms', [
            'pageTitle'   => lang('Lfl.dirFirmsTitle'),
            'description' => lang('Lfl.dirMetaFirms'),
            'canonical'   => base_url('firmas'),
            'robots'      => 'index, follow',
            'result'      => $dir->firms((int) $this->request->getGet('pagina')),
        ]);
    }

    /**
     * El micrositio de una firma: `/firma/{slug}`.
     *
     * El slug es el de `companies`, el mismo que el dueño edita en «Mi empresa», así
     * que la dirección que la firma ya reparte sirve aquí sin inventar otra.
     */
    public function firm($slug = null)
    {
        $data = $this->dir()->firm((string) $slug);
        if (! $data) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        return view('Components\LeadFlow\Views\public\directory\firm', [
            'pageTitle'   => $data['company']['name'] . ' — ' . lang('Lfl.dirBrand'),
            'description' => mb_substr(trim(strip_tags((string) ($data['company']['description'] ?? ''))), 0, 200),
            'canonical'   => base_url('firma/' . rawurlencode((string) $data['company']['slug'])),
            'robots'      => 'index, follow',
            'company'     => $data['company'],
            'profiles'    => $data['profiles'],
            'services'    => $data['services'],
            'areas'       => $data['areas'],
            'reviews'     => $data['reviews'],
            'rating'      => $data['rating'],
            'breakdown'   => $data['breakdown'],
            /*
             * El QR de ESTA página, para que la firma lo imprima y lo pegue donde
             * recibe. Se genera aquí y no en la vista: una vista pública no debería
             * tener que instanciar un servicio para pintarse, y así la vista sigue
             * funcionando —sin QR— si la librería no estuviera.
             */
            'qr'          => $this->pageQr(base_url('firma/' . rawurlencode((string) $data['company']['slug']))),
            // El formulario de consulta vive DENTRO de la ficha, como en la
            // maqueta de referencia, así que sus defensas se emiten aquí.
            'stamp'       => (new Captcha())->stamp(),
            'hasGd'       => extension_loaded('gd'),
        ]);
    }


    /**
     * El QR de una URL pública, o `null` si no se puede generar.
     *
     * El componente lleva su propia copia de `QrService` (el contrato prohíbe depender
     * de lo que uno mismo añadió al núcleo), pero aun así se envuelve en un try: un QR
     * que falla no puede tumbar la ficha de la firma, que es la página que se comparte.
     */
    private function pageQr(string $url): ?string
    {
        try {
            return (new \Components\LeadFlow\Libraries\QrService())->dataUri($url);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * El mapa del sitio del directorio: `/abogados/sitemap.xml`.
     *
     * Cuelga de `abogados/` y no de la raíz porque la raíz es del núcleo: un componente
     * no se queda con el `sitemap.xml` de la plataforma, que puede tener el suyo.
     */
    public function sitemap()
    {
        $urls = $this->dir()->sitemap();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            // **Escapado de XML, no de URL.** `esc($v, 'url')` codifica los dos puntos y
            // las barras (`http%3A%2F%2F…`) y eso no es una URL válida dentro de `<loc>`:
            // aquí solo hay que escapar `&`, `<`, `>` y las comillas.
            $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>';
            if (! empty($u['lastmod'])) {
                $xml .= '<lastmod>' . date('Y-m-d', strtotime((string) $u['lastmod'])) . '</lastmod>';
            }
            $xml .= "</url>\n";
        }
        $xml .= '</urlset>';

        return $this->response->setContentType('application/xml; charset=UTF-8')->setBody($xml);
    }

    /**
     * La tarjeta de presentación digital: `/abogado/{slug}/tarjeta`.
     *
     * Es lo que se pasa por WhatsApp cuando alguien pregunta «¿y tú a qué te dedicas?».
     * El perfil está hecho para **que alguien escriba**; la tarjeta, para
     * **presentarse**: una se lee con calma y la otra se mira tres segundos en un
     * teléfono ajeno.
     *
     * **Va por slug y no por un token**, al revés que la tarjeta de Servicios: allí el
     * token evitaba que se pudiera recorrer el maestro de vendedores, pero aquí el
     * perfil ya es público, así que un token no esconde nada y solo da una URL
     * imposible de dictar. Y la misma regla gobierna las dos páginas: si el perfil da
     * 404, la tarjeta también.
     */
    public function card($slug = null)
    {
        $profile = $this->dir()->profile((string) $slug);
        if (! $profile) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        $url = base_url('abogado/' . rawurlencode((string) $profile['slug']) . '/tarjeta');

        // La fila de la empresa, para el reverso: logo, portada, color y redes. Se LEE de
        // `companies`, que es del núcleo — el contrato prohíbe escribir ahí.
        $company = db_connect()->table('companies')->where('id', (int) $profile['company_id'])
            ->get()->getRowArray() ?: [];

        return view('Components\LeadFlow\Views\public\directory\card', [
            'pageTitle' => $profile['display_name'] . ' · ' . lang('Lfl.dirBrand'),
            'profile'   => $profile,
            'company'   => $company,
            'url'       => $url,
            // El QR apunta a la propia tarjeta: es como se pasa de un teléfono a otro.
            'qr'        => (new QrService())->dataUri($url, 5),
        ]);
    }

    /**
     * La tarjeta como contacto descargable: `/abogado/{slug}/tarjeta.vcf`.
     *
     * La `URL` del contacto es la del **perfil** y no la de la tarjeta: quien guarda el
     * contacto querrá volver a donde se le puede escribir.
     */
    public function vcf($slug = null)
    {
        $profile = $this->dir()->profile((string) $slug);
        if (! $profile) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        // Escapado de vCard 3.0: la coma, el punto y coma y el salto de línea son
        // separadores del formato, no texto.
        $esc = static fn ($v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], trim((string) $v));

        $name  = trim((string) $profile['display_name']);
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . $esc($parts[1] ?? '') . ';' . $esc($parts[0]) . ';;;',
            'FN:' . $esc($name),
        ];
        if (! empty($profile['company']['name'])) {
            $lines[] = 'ORG:' . $esc($profile['company']['name']);
        }
        // El titular hace de cargo: es lo que el profesional escribió para presentarse.
        if (! empty($profile['headline'])) {
            $lines[] = 'TITLE:' . $esc($profile['headline']);
        }
        if (! empty($profile['email'])) {
            $lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . $esc($profile['email']);
        }
        if (! empty($profile['phone'])) {
            $lines[] = 'TEL;TYPE=WORK:' . $esc($profile['phone']);
        }
        if (! empty($profile['whatsapp'])) {
            $lines[] = 'TEL;TYPE=CELL:' . $esc($profile['whatsapp']);
        }
        if (! empty($profile['city'])) {
            $lines[] = 'ADR;TYPE=WORK:;;;' . $esc($profile['city']) . ';;;';
        }
        if ($profile['areas']) {
            $lines[] = 'NOTE:' . $esc(implode(', ', array_column($profile['areas'], 'name')));
        }
        $lines[] = 'URL:' . $esc(base_url('abogado/' . rawurlencode((string) $profile['slug'])));
        $lines[] = 'END:VCARD';

        return $this->response
            ->setContentType('text/vcard; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $profile['slug'] . '.vcf"')
            ->setBody(implode("\r\n", $lines) . "\r\n");
    }

    /**
     * La imagen del captcha del formulario de consulta, en base64 y por JSON, igual
     * que en el formulario público de la firma: así se puede pedir otra sin recargar.
     */
    public function captcha($slug = null)
    {
        if (! $this->dir()->profile((string) $slug)) {
            return $this->response->setStatusCode(404)->setJSON(['img' => '']);
        }
        $c = new Captcha();

        return $this->response->setJSON(['img' => $c->image($c->issue())]);
    }

    /**
     * La consulta dirigida a la firma: `POST /firma/{slug}/consulta`.
     *
     * **Solo POST, y vuelve a la ficha.** El formulario está dentro del micrositio, así
     * que no hay una pantalla propia que mostrar: el resultado se cuenta ahí mismo con
     * un mensaje, que es donde el visitante estaba mirando. Un GET a esta dirección no
     * existe, y la ruta ni siquiera lo acepta.
     *
     * El lead nace sin abogado asignado: entra al reparto de la firma. Quien quiera
     * escribirle a alguien en concreto tiene el formulario de su perfil.
     */
    public function firmInquiry($slug = null)
    {
        $data = $this->dir()->firm((string) $slug);
        if (! $data) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        $url = base_url('firma/' . rawurlencode((string) $data['company']['slug']));
        $res = (new InquiryService())->submitToFirm($data['company'], [
            'first_name' => (string) $this->request->getPost('first_name'),
            'last_name'  => (string) $this->request->getPost('last_name'),
            'email'      => (string) $this->request->getPost('email'),
            'phone'      => (string) $this->request->getPost('phone'),
            'city'       => (string) $this->request->getPost('city'),
            'area_id'    => (int) $this->request->getPost('area_id'),
            'message'    => (string) $this->request->getPost('message'),
            'consent'    => (bool) $this->request->getPost('consent'),
            'website'    => (string) $this->request->getPost('website'),
            'stamp'      => (string) $this->request->getPost('stamp'),
            'captcha'    => (string) $this->request->getPost('captcha'),
            'ip'         => (string) $this->request->getIPAddress(),
            'landing'    => $url,
        ]);

        // `#consulta`: se vuelve al formulario, no a la portada de la ficha.
        return redirect()->to($url . '#consulta')
            ->with($res['ok'] ? 'dir_ok' : 'dir_error', $res['message']);
    }

    /**
     * La portada de la página de inicio: `GET /portada`.
     *
     * El archivo vive fuera de `public/`, así que lo entrega este controlador. Se
     * sirve con caché larga porque casi nunca cambia, y la dirección lleva un `?v=`
     * con la fecha del archivo: cambiar la portada cambia la dirección, así que la
     * caché larga no deja a nadie viendo la anterior.
     *
     * No lleva permiso ni token: es la imagen de la portada de un sitio público.
     */
    public function cover()
    {
        $path = (new \Components\LeadFlow\Libraries\Directory\CoverService())->path();
        if ($path === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $mime = (string) (new \CodeIgniter\Files\File($path))->getMimeType();
        // Lista blanca también al servir, no solo al subir: si el archivo cambiara por
        // debajo, de aquí no sale nada que no sea una de las tres imágenes aceptadas.
        if (! isset(\Components\LeadFlow\Libraries\Directory\CoverService::TYPES[$mime])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        /*
         * `setCache()` y no `setHeader()`: la respuesta de CI4 nace con
         * `Cache-Control: no-store, no-cache` puesto por `noCache()`, y `setCache()`
         * es el único camino que lo RETIRA antes de escribir el suyo. Con un
         * `setHeader` suelto la portada se volvía a descargar en cada visita.
         */
        return $this->response
            ->setHeader('Content-Type', $mime)
            // Las directivas sin valor —`public`, `immutable`— van SIN clave: CI4
            // construye la cabecera con `clave=valor` para las claves de texto y con
            // el valor a secas para las numéricas. Un `'public' => true` revienta.
            ->setCache([
                'public',
                'immutable',
                'max-age' => 31536000,
            ])
            ->setBody((string) file_get_contents($path));
    }

    /**
     * Consulta del estado de un caso, en la página de la firma:
     * `GET|POST /firma/{slug}/caso`.
     *
     * **Solo existe si la firma está conectada a USCIS en modo REAL.** En simulado la
     * página devuelve 404, y es la regla más importante de toda la fase: enseñarle a
     * un cliente de verdad un estado de ejemplo —«su caso fue aprobado»— sobre un
     * trámite migratorio real sería el peor fallo posible de este producto. El modo
     * simulado es para que la firma pruebe por dentro, no para enseñarlo fuera.
     *
     * El 404 tampoco distingue entre «la firma no existe», «no tiene directorio» y «no
     * tiene USCIS», como el resto del componente: desde fuera no se puede deducir qué
     * tiene contratada cada firma.
     *
     * `noindex`: lleva un formulario con un dato personal. No es contenido que indexar.
     */
    public function caseStatus($slug = null)
    {
        $data = $this->dir()->firm((string) $slug);
        if (! $data) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        $cid = (int) $data['company']['id'];
        $svc = new \Components\LeadFlow\Libraries\Uscis\UscisService();
        $cfg = $svc->config($cid);

        if ($cfg['mode'] !== 'live' || ! $cfg['configured']) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        $resultado = null;

        if (strtolower($this->request->getMethod()) === 'post') {
            $resultado = $this->caseLookup($svc, $data['company'], (string) $this->request->getPost('receipt'));
        }

        return view('Components\LeadFlow\Views\public\directory\case_status', [
            'pageTitle' => lang('Lfl.uscPubTitle', [$data['company']['name']]),
            'company'   => $data['company'],
            'result'    => $resultado,
            'stamp'     => (new Captcha())->stamp(),
            'hasGd'     => extension_loaded('gd'),
        ]);
    }

    /**
     * Las defensas del formulario público y la consulta.
     *
     * Van aquí y no en `UscisService` a propósito: el servicio lo usan también las
     * pantallas internas, donde no hay captcha ni trampa porque quien consulta ya
     * entró con su usuario. Lo que defiende de un visitante anónimo es de esta página.
     *
     * El freno por IP es lo que impide que alguien enumere recibos y se lleve el
     * estado de casos ajenos —y de paso, que agote el cupo de la firma—.
     */
    private function caseLookup(\Components\LeadFlow\Libraries\Uscis\UscisService $svc, array $company, string $receipt): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'message' => $m, 'case' => null, 'stale' => false, 'checked_at' => null];

        $ip = (string) $this->request->getIPAddress();

        // La IP va hasheada en la clave: el «::1» de IPv6 lleva caracteres reservados.
        if (! service('throttler')->check('lfl_case_' . md5($ip . '|' . $company['slug']), 5, MINUTE)) {
            return $fail(lang('Lfl.pubTooMany'));
        }
        if (trim((string) $this->request->getPost('website')) !== '') {
            return $fail(lang('Lfl.pubCaptchaError'));
        }

        $captcha = new Captcha();
        if (! $captcha->humanSpeed((string) $this->request->getPost('stamp'))) {
            return $fail(lang('Lfl.pubTooFast'));
        }
        if (extension_loaded('gd') && ! $captcha->check((string) $this->request->getPost('captcha'))) {
            return $fail(lang('Lfl.pubCaptchaError'));
        }

        return $svc->status((int) $company['id'], $receipt);
    }

    /** El reto del captcha de la consulta de casos. */
    public function caseCaptcha($slug = null)
    {
        if (! $this->dir()->firm((string) $slug)) {
            return $this->response->setStatusCode(404)->setJSON(['img' => '']);
        }
        $c = new Captcha();

        return $this->response->setJSON(['img' => $c->image($c->issue())]);
    }

    /**
     * El canal de denuncia: `GET|POST /reportar/{slug}`.
     *
     * El slug es el de la FIRMA. Si llega `?abogado={slug}`, la denuncia queda además
     * atada a ese perfil: desde la ficha de un abogado se denuncia a esa persona, no
     * a todo el despacho.
     *
     * **`noindex`**: es un formulario, no contenido. Y el acuse no dice nunca si la
     * denuncia entró o si se descartó por el tope: quien pasa por aquí no tiene por
     * qué poder averiguar qué hizo otra persona desde su misma red.
     */
    public function report($slug = null)
    {
        $data = $this->dir()->firm((string) $slug);
        if (! $data) {
            return $this->notFound(lang('Lfl.dirNotFoundTitle'));
        }

        // El abogado señalado, si viene y es de esta firma. Uno de otra se ignora en
        // vez de rechazarse: el objeto de la denuncia sigue siendo válido.
        $profile = null;
        $pslug   = trim((string) $this->request->getGet('abogado'));
        if ($pslug !== '') {
            $p = $this->dir()->profile($pslug);
            if ($p && (int) $p['company_id'] === (int) $data['company']['id']) {
                $profile = $p;
            }
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            $res = (new ComplaintService())->submit($data['company'], [
                'category' => (string) $this->request->getPost('category'),
                'reason'   => (string) $this->request->getPost('reason'),
                'contact'  => (string) $this->request->getPost('contact'),
                'truthful' => (bool) $this->request->getPost('truthful'),
                'website'  => (string) $this->request->getPost('website'),
                'stamp'    => (string) $this->request->getPost('stamp'),
                'captcha'  => (string) $this->request->getPost('captcha'),
                'ip'       => (string) $this->request->getIPAddress(),
            ], $profile);

            if ($res['ok']) {
                return redirect()->to(base_url('reportar/listo'));
            }

            return redirect()->back()->withInput()->with('dir_error', $res['message']);
        }

        return view('Components\LeadFlow\Views\public\directory\report', [
            'pageTitle' => lang('Lfl.repTitle'),
            'company'   => $data['company'],
            'profile'   => $profile,
            'stamp'     => (new Captcha())->stamp(),
            'hasGd'     => extension_loaded('gd'),
        ]);
    }

    /** El acuse de la denuncia. Sin datos: solo que se recibió. */
    public function reportDone()
    {
        return view('Components\LeadFlow\Views\public\directory\report_done', [
            'pageTitle' => lang('Lfl.repDoneTitle'),
        ]);
    }

    /** El reto del captcha del canal de denuncia. */
    public function reportCaptcha($slug = null)
    {
        if (! $this->dir()->firm((string) $slug)) {
            return $this->response->setStatusCode(404)->setJSON(['img' => '']);
        }
        $c = new Captcha();

        return $this->response->setJSON(['img' => $c->image($c->issue())]);
    }

    /** El reto del captcha de la ficha de la firma. */
    public function firmCaptcha($slug = null)
    {
        if (! $this->dir()->firm((string) $slug)) {
            return $this->response->setStatusCode(404)->setJSON(['img' => '']);
        }
        $c = new Captcha();

        return $this->response->setJSON(['img' => $c->image($c->issue())]);
    }
}
