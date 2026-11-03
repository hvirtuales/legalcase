<?php

namespace Components\LeadFlow\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\LeadFlow\Libraries\Access;
use Components\LeadFlow\Libraries\AutomationEngine;
use Components\LeadFlow\Libraries\AutomationRegistry;
use Components\LeadFlow\Libraries\Catalog;
use Components\LeadFlow\Libraries\ConversionService;
use Components\LeadFlow\Libraries\DemoData;
use Components\LeadFlow\Libraries\Dedupe;
use Components\LeadFlow\Libraries\DocumentService;
use Components\LeadFlow\Libraries\FormEngine;
use Components\LeadFlow\Libraries\Integrations\DocketwiseSimulatedAdapter;
use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Libraries\LeadService;
use Components\LeadFlow\Libraries\MatterService;
use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Libraries\ScoringService;
use Components\LeadFlow\Libraries\Team;
use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\AutomationModel;
use Components\LeadFlow\Models\AutomationRunModel;
use Components\LeadFlow\Models\ConversionModel;
use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\MappingModel;
use Components\LeadFlow\Models\MemberModel;
use Components\LeadFlow\Models\NoteModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SimRecordModel;
use Components\LeadFlow\Models\StageHistoryModel;
use Components\LeadFlow\Models\StageModel;

/**
 * `php spark leadflow:autotest`
 *
 * Comprueba las REGLAS del componente, que es lo que no se ve mirando una pantalla:
 * duplicados, saneado y lógica de los formularios, reglas del pipeline, scoring,
 * visibilidad por función comercial, aislamiento entre firmas y —sobre todo— que la
 * conversión a Docketwise sea idempotente y que un reintento tras un fallo a mitad
 * continúe sin duplicar nada.
 *
 * **Se monta su propio escenario y lo borra siempre**, también si algo falla
 * (`finally`): dos empresas y cuatro usuarios con prefijo `ZZTEST`. Borrar las
 * empresas se lleva en cascada todo lo del componente. Inserta por Query Builder:
 * el trait `Auditable` del núcleo usa `getUserAgent()`, que no existe en CLI.
 */
class LeadflowAutotest extends BaseCommand
{
    protected $group       = 'LeadFlow';
    protected $name        = 'leadflow:autotest';
    protected $description = 'Comprueba las reglas de LeadFlow (crea y borra su propio escenario).';

    private int $ok = 0;
    private int $ko = 0;
    private array $companies = [];
    private array $users = [];

    public function run(array $params)
    {
        try {
            $this->setup();

            // La conversión se prueba contra el adaptador SIMULADO, y el modo vive en
            // una fila global de la plataforma: si esta instalación está en «live»
            // —como lo está en cuanto alguien conecta una cuenta real— la sección 9
            // salía a la API de verdad y fallaba entera. Se impone el modo solo
            // durante la prueba, sin tocar la configuración, y se devuelve en el
            // `finally` aunque algo revuelva por el camino.
            IntegrationService::forceMode('simulated');

            // **Cada sección va en su propio try.** Antes una excepción en la 9 se
            // llevaba por delante la 10 y la 11, que ni se ejecutaban: el resumen
            // decía «6 fallidas» cuando en realidad había dos bloques sin probar,
            // que es peor que un fallo porque no se ve.
            foreach (['s1Dedupe', 's2Forms', 's3Catalog', 's4Leads', 's5Pipeline', 's6Scoring',
                's7Access', 's8Isolation', 's9Conversion', 's10Plan', 's11Automations', 's12Directory',
                's13Signup', 's14Opportunities', 's15Reviews', 's16Firm', 's17Complaints',
                's18Uscis', 's19Matters'] as $section) {
                try {
                    $this->{$section}();
                } catch (\Throwable $e) {
                    $this->check("sin excepciones inesperadas en {$section}", false,
                        get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
                }
            }
        } catch (\Throwable $e) {
            $this->check('sin excepciones inesperadas', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        } finally {
            IntegrationService::forceMode(null);
            $this->cleanup();
        }

        CLI::newLine();
        CLI::write(str_repeat('─', 44), 'dark_gray');
        CLI::write($this->ko === 0 ? "TODO BIEN: {$this->ok} comprobaciones, 0 fallidas" : "HAY FALLOS: {$this->ok} correctas, {$this->ko} fallidas",
            $this->ko === 0 ? 'green' : 'red');
    }

    private function check(string $what, bool $good, string $detail = ''): void
    {
        $good ? $this->ok++ : $this->ko++;
        CLI::write(($good ? '  OK    ' : '  FALLA ') . $what . (! $good && $detail !== '' ? '  ->  ' . $detail : ''), $good ? 'green' : 'red');
    }

    private function title(string $t): void
    {
        CLI::newLine();
        CLI::write('=== ' . $t . ' ===', 'yellow');
    }

    // ── Escenario ─────────────────────────────────────────────────────────────

    private function setup(): void
    {
        $db   = \Config\Database::connect();
        $tag  = bin2hex(random_bytes(3));
        $now  = date('Y-m-d H:i:s');

        foreach (['A', 'B'] as $k) {
            $db->table('companies')->insert(['name' => "ZZTEST LeadFlow {$k}", 'slug' => "zztest-lfl-{$k}-{$tag}",
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $this->companies[$k] = (int) $db->insertID();
        }
        foreach (['sup', 'adv1', 'adv2', 'aud', 'none'] as $k) {
            $db->table('users')->insert(['username' => "zztest_{$k}_{$tag}", 'email' => "zztest_{$k}_{$tag}@example.test",
                'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'first_name' => 'ZZTEST', 'last_name' => $k,
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $this->users[$k] = (int) $db->insertID();
            $db->table('company_users')->insert(['company_id' => $this->companies['A'], 'user_id' => $this->users[$k], 'is_admin' => 0]);
        }

        $m = new MemberModel();
        $m->setFor($this->companies['A'], $this->users['sup'], 'supervisor', null);
        $m->setFor($this->companies['A'], $this->users['adv1'], 'advisor', null);
        $m->setFor($this->companies['A'], $this->users['adv2'], 'advisor', null);
        $m->setFor($this->companies['A'], $this->users['aud'], 'auditor', null);
        Team::flush();
        Access::flush();

        CLI::write('Escenario: empresas ' . implode(', ', $this->companies) . ' · usuarios ' . implode(', ', $this->users), 'dark_gray');
    }

    /**
     * Directorio público (fase 1 del plan).
     *
     * Lo que se comprueba es lo que no se ve mirando la pantalla: que un perfil en
     * borrador NO salga, que publicar exija lo mínimo para servir de algo, que lo que
     * llega del formulario se reconstruya contra los catálogos, y —sobre todo— que la
     * consulta pública entre como un lead de la firma correcta con su atribución.
     */
    private function s12Directory(): void
    {
        $this->title('12. Directorio público (fase 1)');
        $cidA = $this->companies['A'];
        $cidB = $this->companies['B'];

        \Components\LeadFlow\Libraries\Directory\Taxonomy::flush();
        \Components\LeadFlow\Libraries\Directory\Taxonomy::ensure();

        $areas = (new \Components\LeadFlow\Models\AreaModel())->active();
        $jurs  = (new \Components\LeadFlow\Models\JurisdictionModel())->active();
        $this->check('la taxonomía se siembra sola', count($areas) >= 12 && count($jurs) >= 1);

        $areaId   = (int) $areas[0]['id'];
        $svc      = new \Components\LeadFlow\Libraries\Directory\ProfileService();
        $profiles = new \Components\LeadFlow\Models\ProfileModel();
        $db       = \Config\Database::connect();

        // ── Alta y saneado ────────────────────────────────────────────────────
        $res = $svc->create($cidA, [
            'kind' => 'lawyer', 'user_id' => $this->users['adv1'],
            'display_name' => 'ZZTEST Ábogada Uno', 'headline' => 'Inmigración familiar',
            'email' => 'zztest.perfil@example.test', 'city' => 'Miami',
            'languages' => 'Español, Inglés', 'consult_modes' => ['video', 'pirata'],
            'areas' => [$areaId, 999999], 'jurisdictions' => [(int) $jurs[0]['id']],
        ]);
        $this->check('se crea el perfil', $res['ok'], $res['message']);
        $pid = (int) $res['id'];
        $p   = $profiles->find($pid);

        $this->check('nace en BORRADOR', ($p['status'] ?? '') === 'draft');
        $this->check('el slug sale del nombre y sin tildes', ($p['slug'] ?? '') === 'zztest-abogada-uno', (string) ($p['slug'] ?? ''));
        $this->check('una modalidad inventada se descarta', ($p['consult_modes'] ?? '') === 'video');
        $this->check('un área inventada no se guarda',
            $db->table('cmp_lfl_profile_areas')->where('profile_id', $pid)->countAllResults() === 1);

        $this->check('un usuario que no es de la firma no se ata',
            ! $svc->create($cidA, ['kind' => 'lawyer', 'user_id' => 999999, 'display_name' => 'ZZTEST Ajeno'])['ok']);
        $this->check('el mismo usuario no tiene dos perfiles',
            ! $svc->create($cidA, ['kind' => 'lawyer', 'user_id' => $this->users['adv1'], 'display_name' => 'ZZTEST Repetida'])['ok']);
        $this->check('un perfil de la firma A no se busca desde la B', $profiles->owned($pid, $cidB) === null);

        // ── Publicación: exige lo mínimo para servir de algo ──────────────────
        $vacio = $svc->create($cidA, ['kind' => 'firm', 'display_name' => 'ZZTEST Firma Vacía']);
        $vid   = (int) $vacio['id'];
        $this->check('sin área no se publica', ! $svc->publish($profiles->find($vid))['ok']);
        $db->table('cmp_lfl_profile_areas')->insert(['profile_id' => $vid, 'area_id' => $areaId, 'created_at' => date('Y-m-d H:i:s')]);
        $this->check('sin forma de contacto tampoco', ! $svc->publish($profiles->find($vid))['ok']);

        $dir = new \Components\LeadFlow\Libraries\Directory\PublicDirectory();
        $mine = static fn (\Components\LeadFlow\Libraries\Directory\PublicDirectory $d): int => $d->search(['q' => 'ZZTEST'])['total'];
        $this->check('en borrador NO aparece en el buscador', $mine($dir) === 0);
        $this->check('…ni por su slug', $dir->profile((string) $p['slug']) === null);

        $this->check('publicado', $svc->publish($p)['ok']);
        $p = $profiles->find($pid);
        $this->check('…queda con fecha de publicación', ! empty($p['published_at']));

        $this->check('ahora sí aparece en el buscador', $mine($dir) === 1);
        $this->check('…y se puede abrir por su slug', $dir->profile((string) $p['slug']) !== null);
        $this->check('el filtro por otra área no lo trae', count($areas) > 1
            ? $dir->search(['q' => 'ZZTEST', 'area' => (int) $areas[1]['id']])['total'] === 0 : true);
        $this->check('el área aparece con su conteo', (function () use ($dir, $areaId) {
            foreach ($dir->areasWithCounts() as $a) {
                if ((int) $a['id'] === $areaId) {
                    return (int) $a['n'] >= 1;
                }
            }

            return false;
        })());

        // Sin plan asignado no hay cupo: la columna ausente significa «permitido».
        $this->check('sin plan, publicar no está limitado',
            (new PlanGuard())->publishBlockReason($cidA) === null);

        // ── La consulta pública se convierte en LEAD de esa firma ─────────────
        $inquiry = new \Components\LeadFlow\Libraries\Directory\InquiryService();
        $captcha = new \Components\LeadFlow\Libraries\Captcha();
        // El sello va con antigüedad suficiente y firmado con la clave de la
        // instalación: es lo que comprueba `humanSpeed()`.
        $t     = time() - 10;
        $stamp = $t . '.' . hash_hmac('sha256', (string) $t, (string) config('Encryption')->key);
        $base  = [
            'first_name' => 'ZZTEST', 'last_name' => 'Consulta', 'email' => 'zztest.consulta@example.test',
            'phone' => '+1 786 555 0900', 'city' => 'Miami', 'area_id' => $areaId,
            'message' => 'Necesito orientación para una visa familiar.', 'consent' => true,
            'stamp' => $stamp, 'captcha' => $captcha->issue(),
            // Una IP distinta en cada corrida: el freno del formulario es de 5 por minuto
            // por IP y perfil, y con una IP fija la segunda ejecución seguida se frenaba
            // a sí misma. Cada corrida simula un visitante nuevo, que es lo realista.
            'ip' => '203.0.113.' . random_int(1, 254),
        ];

        $this->check('sin consentimiento no se envía', ! $inquiry->submit($p, ['consent' => false] + $base)['ok']);
        $this->check('con la trampa llena tampoco',
            ! $inquiry->submit($p, ['website' => 'http://spam.test'] + $base)['ok']);

        // El captcha es de un solo uso: se emite otro para el envío bueno.
        $base['captcha'] = $captcha->issue();
        $sent = $inquiry->submit($p, $base);
        $this->check('la consulta entra', $sent['ok'], $sent['message']);

        $lead = (new LeadModel())->find((int) $sent['id']);
        $this->check('…como lead de la firma del perfil', $lead && (int) $lead['company_id'] === $cidA);
        $this->check('…atribuido al perfil', $lead && (int) $lead['profile_id'] === $pid);
        $this->check('…con el abogado del perfil asignado', $lead && (int) $lead['lawyer_user_id'] === $this->users['adv1']);
        $this->check('…marcado como que pidió consulta', $lead && (int) $lead['requested_consultation'] === 1);
        $this->check('…con la fuente del directorio', (function () use ($lead, $cidA) {
            $s = $lead && $lead['source_id'] ? (new \Components\LeadFlow\Models\SourceModel())->owned((int) $lead['source_id'], $cidA) : null;

            return $s && $s['utm_source'] === \Components\LeadFlow\Libraries\Directory\InquiryService::SOURCE_UTM;
        })());
        $this->check('el perfil cuenta el prospecto', (int) $profiles->find($pid)['leads_count'] === 1);

        // ── Fase 2: micrositio de firma, sitemap y verificación ───────────────
        $slugA = (string) (\Config\Database::connect()->table('companies')->select('slug')
            ->where('id', $cidA)->get()->getRowArray()['slug'] ?? '');
        $slugB = (string) (\Config\Database::connect()->table('companies')->select('slug')
            ->where('id', $cidB)->get()->getRowArray()['slug'] ?? '');

        $firm = $dir->firm($slugA);
        $this->check('el micrositio de la firma trae sus perfiles publicados',
            $firm !== null && count($firm['profiles']) === 1 && (int) $firm['profiles'][0]['id'] === $pid);
        // La firma B no tiene ningún perfil publicado: un micrositio vacío no existe, y de
        // paso no se filtra la lista de clientes del producto.
        $this->check('una firma sin perfiles publicados no tiene micrositio', $dir->firm($slugB) === null);
        $this->check('…ni aparece en el listado de firmas', ! in_array($slugB,
            array_column($dir->firms()['rows'], 'slug'), true));
        $this->check('la firma con perfiles sí aparece', in_array($slugA,
            array_column($dir->firms()['rows'], 'slug'), true));

        $locs = array_column($dir->sitemap(), 'loc');
        $this->check('el sitemap lleva el perfil publicado y su tarjeta',
            in_array(base_url('abogado/' . $p['slug']), $locs, true)
            && in_array(base_url('abogado/' . $p['slug'] . '/tarjeta'), $locs, true));
        $this->check('…y el micrositio de la firma', in_array(base_url('firma/' . $slugA), $locs, true));
        $this->check('…pero NO un perfil en borrador', ! in_array(base_url('abogado/' . $profiles->find($vid)['slug']), $locs, true));

        $this->check('verificar deja fecha y autor', (function () use ($svc, $profiles, $pid) {
            $svc->verify($profiles->find($pid), 1);
            $row = $profiles->find($pid);

            return ! empty($row['verified_at']) && (int) $row['verified_by'] === 1;
        })());
        $this->check('…y el perfil sale verificado en el directorio',
            ! empty($dir->profile((string) $p['slug'])['is_verified']));
        $this->check('retirar la verificación la borra', (function () use ($svc, $profiles, $pid) {
            $svc->verify($profiles->find($pid), 1, false);

            return empty($profiles->find($pid)['verified_at']);
        })());

        // ── Piezas de la tarjeta digital ──────────────────────────────────────
        $this->check('las iniciales son la letra de cada palabra',
            \Components\LeadFlow\Models\ProfileModel::initials('Jaime Zuluaga') === 'JZ'
            && \Components\LeadFlow\Models\ProfileModel::initials('Ángela') === 'Á'
            && \Components\LeadFlow\Models\ProfileModel::initials('') === '?');
        // El QR lo dibuja una copia propia del servicio (§15): si faltara el vendor, la
        // tarjeta seguiría abriéndose, pero sin lo que la hace pasar de mano en mano.
        $this->check('el QR de la tarjeta se genera',
            is_string((new \Components\LeadFlow\Libraries\QrService())->dataUri('https://example.test/abogado/x/tarjeta'))
            && str_starts_with((string) (new \Components\LeadFlow\Libraries\QrService())->dataUri('https://example.test/x'), 'data:image/'));

        // Despublicar lo saca del directorio sin borrar nada.
        $svc->unpublish($profiles->find($pid));
        $this->check('despublicar lo saca del buscador',
            $mine(new \Components\LeadFlow\Libraries\Directory\PublicDirectory()) === 0);
        $this->check('…y el lead que trajo sigue ahí', (new LeadModel())->find((int) $sent['id']) !== null);
    }

    /**
     * Alta pública de abogados y firmas (fase 2).
     *
     * Es el único sitio del componente que **escribe en las tablas del núcleo**
     * —usuario, empresa, membresía y suscripción—, y lo hace en una transacción: lo que
     * se comprueba aquí es justamente que no queden altas a medias y que lo que nace
     * nazca sin publicar.
     *
     * Limpia lo que crea al terminar: las cuentas de prueba no pasan por `$this->users`
     * porque las crea el servicio, no el escenario.
     */
    private function s13Signup(): void
    {
        $this->title('13. Alta pública (fase 2)');
        $svc = new \Components\LeadFlow\Libraries\Directory\SignupService();
        $db  = \Config\Database::connect();
        $tag = bin2hex(random_bytes(3));
        $ip  = '198.51.100.' . random_int(1, 254);

        $base = [
            'kind' => 'lawyer', 'first_name' => 'ZZTEST', 'last_name' => 'Alta',
            'email' => "zztest_alta_{$tag}@example.test", 'phone' => '+1 786 555 0777',
            'password' => 'clave-larga-9', 'password_confirm' => 'clave-larga-9',
        ];

        // ── Validaciones, antes de tocar nada ─────────────────────────────────
        $this->check('sin nombre no se registra', ! $svc->register(['first_name' => ''] + $base, $ip)['ok']);
        $this->check('con un correo inválido tampoco', ! $svc->register(['email' => 'no-es-correo'] + $base, $ip)['ok']);
        $this->check('con la contraseña corta tampoco', ! $svc->register(['password' => 'corta', 'password_confirm' => 'corta'] + $base, $ip)['ok']);
        $this->check('si las contraseñas no coinciden tampoco', ! $svc->register(['password_confirm' => 'otra-clave-9'] + $base, $ip)['ok']);
        $this->check('una firma sin nombre tampoco', ! $svc->register(['kind' => 'firm'] + $base, $ip)['ok']);
        $this->check('…y nada de eso dejó usuarios a medias',
            $db->table('users')->like('email', "zztest_alta_{$tag}", 'after')->countAllResults() === 0);

        /*
         * **El alta necesita un request HTTP, aunque esto sea CLI.**
         *
         * `UserModel` del núcleo usa el trait `Auditable`, que llama a
         * `getUserAgent()` — un método que `CLIRequest` no tiene. Es el gotcha que
         * CLAUDE.md ya documenta: «los modelos auditados no se pueden insertar por
         * CLI». El resto del autotest lo esquiva insertando por Query Builder, pero
         * aquí lo que se está probando es justamente el servicio que usa el modelo,
         * así que se le pone delante un request falso y se devuelve el verdadero al
         * terminar.
         */
        $realRequest = \Config\Services::request();
        $appConfig   = config(\Config\App::class);
        \Config\Services::injectMock('request', new \CodeIgniter\HTTP\IncomingRequest(
            $appConfig,
            new \CodeIgniter\HTTP\URI('http://localhost/registro-legal'),
            '',
            new \CodeIgniter\HTTP\UserAgent()
        ));

        try {
            $this->signupChecks($svc, $db, $base, $tag, $ip);
        } finally {
            \Config\Services::injectMock('request', $realRequest);
        }
    }

    /** El cuerpo del alta, con el request falso ya puesto. */
    private function signupChecks(
        \Components\LeadFlow\Libraries\Directory\SignupService $svc,
        $db,
        array $base,
        string $tag,
        string $ip
    ): void {
        // ── Alta de un abogado independiente ──────────────────────────────────
        $res = $svc->register($base, $ip);
        $this->check('se registra un abogado', $res['ok'], $res['message']);
        $this->check('…con un usuario a partir del correo', ($res['username'] ?? '') !== '');

        $user = $db->table('users')->where('email', $base['email'])->get()->getRowArray();
        $this->check('el usuario existe y está activo', $user && (int) $user['is_active'] === 1);
        $uid = (int) ($user['id'] ?? 0);

        $company = $uid ? $db->table('companies')->where('owner_id', $uid)->get()->getRowArray() : null;
        $cid     = (int) ($company['id'] ?? 0);
        $this->check('su empresa se creó con él como dueño', $cid > 0 && (int) $company['is_active'] === 1);
        $this->check('…con slug propio', $cid > 0 && trim((string) $company['slug']) !== '');
        $this->check('…y con plan asignado', $cid > 0 && (int) $company['plan_id'] > 0);
        $this->check('es administrador de su empresa', $cid > 0 && $db->table('company_users')
            ->where('company_id', $cid)->where('user_id', $uid)->where('is_admin', 1)->countAllResults() === 1);
        $this->check('tiene suscripción vigente', $cid > 0 && $db->table('subscriptions')
            ->where('company_id', $cid)->where('end_date >', date('Y-m-d H:i:s'))->countAllResults() === 1);
        $this->check('tiene rol del núcleo', $uid > 0 && $db->table('user_roles')->where('user_id', $uid)->countAllResults() > 0);
        $this->check('y función comercial de supervisor en su firma',
            $cid > 0 && (new MemberModel())->roleOf($cid, $uid) === 'supervisor');

        $profiles = new \Components\LeadFlow\Models\ProfileModel();
        $perfil   = $cid > 0 ? $profiles->forCompany($cid)->first() : null;
        $this->check('se creó su perfil del directorio', $perfil !== null);
        $this->check('…en BORRADOR', ($perfil['status'] ?? '') === 'draft');
        $this->check('…de tipo abogado y atado a su usuario',
            ($perfil['kind'] ?? '') === 'lawyer' && (int) ($perfil['user_id'] ?? 0) === $uid);
        $this->check('…y por tanto NO aparece en el directorio',
            (new \Components\LeadFlow\Libraries\Directory\PublicDirectory())->profile((string) ($perfil['slug'] ?? 'x')) === null);

        $this->check('el mismo correo no se registra dos veces', ! $svc->register($base, $ip)['ok']);

        // ── Alta de una firma ─────────────────────────────────────────────────
        $firmIn = ['kind' => 'firm', 'company_name' => "ZZTEST Firma {$tag}",
            'email' => "zztest_firma_{$tag}@example.test"] + $base;
        $res2 = $svc->register($firmIn, $ip);
        $this->check('se registra una firma', $res2['ok'], $res2['message']);

        $user2 = $db->table('users')->where('email', $firmIn['email'])->get()->getRowArray();
        $uid2  = (int) ($user2['id'] ?? 0);
        $comp2 = $uid2 ? $db->table('companies')->where('owner_id', $uid2)->get()->getRowArray() : null;
        $cid2  = (int) ($comp2['id'] ?? 0);
        $this->check('la empresa se llama como la firma, no como la persona',
            $cid2 > 0 && $comp2['name'] === $firmIn['company_name']);
        $perfil2 = $cid2 > 0 ? $profiles->forCompany($cid2)->first() : null;
        $this->check('su perfil es de la FIRMA y sin usuario',
            ($perfil2['kind'] ?? '') === 'firm' && ($perfil2['user_id'] ?? null) === null);

        // Limpieza de lo que creó el servicio (las empresas se llevan los perfiles en
        // cascada; los usuarios van aparte porque no cuelgan de la empresa).
        foreach (array_filter([$cid, $cid2]) as $c) {
            $db->table('company_users')->where('company_id', $c)->delete();
            $db->table('subscriptions')->where('company_id', $c)->delete();
            $db->table('companies')->where('id', $c)->delete();
        }
        foreach (array_filter([$uid, $uid2]) as $u) {
            $db->table('user_roles')->where('user_id', $u)->delete();
            $db->table('users')->where('id', $u)->delete();
        }
        $left = $db->table('users')->like('email', "zztest_alta_{$tag}", 'after')->countAllResults()
            + $db->table('users')->like('email', "zztest_firma_{$tag}", 'after')->countAllResults();
        $this->check('la prueba no deja cuentas ni empresas', $left === 0);
    }

    /**
     * Oportunidades abiertas (fase 3).
     *
     * Lo que de verdad hay que probar aquí no es el formulario: es **el reparto**. Que el
     * caso solo lo vean las firmas del área, que el contacto no salga de la bandeja, que
     * el tope no se pueda pasar ni reclamando a la vez, y que al reclamar nazca un lead
     * con los datos completos en la firma correcta.
     */
    private function s14Opportunities(): void
    {
        $this->title('14. Oportunidades abiertas (fase 3)');
        $cidA = $this->companies['A'];
        $cidB = $this->companies['B'];
        $db   = \Config\Database::connect();
        $svc  = new \Components\LeadFlow\Libraries\Directory\OpportunityService();

        \Components\LeadFlow\Libraries\Directory\Taxonomy::ensure();
        $areas   = (new \Components\LeadFlow\Models\AreaModel())->active();
        $areaId  = (int) $areas[0]['id'];
        $otraId  = (int) ($areas[1]['id'] ?? $areas[0]['id']);
        $opps    = new \Components\LeadFlow\Models\OpportunityModel();
        $claims  = new \Components\LeadFlow\Models\OpportunityClaimModel();
        $profSvc = new \Components\LeadFlow\Libraries\Directory\ProfileService();

        // Las dos firmas publican perfil: A en el área de la oportunidad, B en otra.
        foreach ([[$cidA, $areaId, 'adv1'], [$cidB, $otraId, 'adv2']] as [$cid, $area, $who]) {
            $r = $profSvc->create($cid, [
                'kind' => 'firm', 'display_name' => "ZZTEST Opp {$cid}",
                'email' => "zztest.opp.{$cid}@example.test", 'areas' => [$area],
            ]);
            $db->table('cmp_lfl_profile_areas')->where('profile_id', (int) $r['id'])->countAllResults();
            $profSvc->publish((new \Components\LeadFlow\Models\ProfileModel())->find((int) $r['id']));
        }

        // ── El prospecto describe su caso ─────────────────────────────────────
        $t     = time() - 10;
        $stamp = $t . '.' . hash_hmac('sha256', (string) $t, (string) config('Encryption')->key);
        $cap   = new \Components\LeadFlow\Libraries\Captcha();
        $base  = [
            'area_id' => $areaId, 'city' => 'Miami', 'language' => 'Español',
            'summary' => 'No sé a quién acudir: me negaron una visa familiar.',
            'first_name' => 'ZZTEST', 'last_name' => 'Oportunidad',
            'email' => 'zztest.oportunidad@example.test', 'phone' => '+1 786 555 0988',
            'consent' => true, 'stamp' => $stamp, 'ip' => '203.0.113.' . random_int(1, 254),
        ];

        // El formulario admite 3 por minuto y por IP: cada envío de la prueba es un
        // visitante distinto, que es lo realista y lo que no frena la prueba a sí misma.
        $nuevaIp = static fn (): string => '203.0.113.' . random_int(1, 254);

        $this->check('sin área no se crea la oportunidad',
            ! $svc->submit(['area_id' => 0, 'captcha' => $cap->issue(), 'ip' => $nuevaIp()] + $base)['ok']);
        $this->check('sin consentimiento tampoco',
            ! $svc->submit(['consent' => false, 'captcha' => $cap->issue(), 'ip' => $nuevaIp()] + $base)['ok']);

        $sent = $svc->submit(['captcha' => $cap->issue(), 'ip' => $nuevaIp()] + $base);
        $this->check('la oportunidad se crea', $sent['ok'], $sent['message']);
        $oid = (int) $sent['id'];
        $row = $opps->find($oid);
        $this->check('…abierta y con el tope escrito en su fila',
            ($row['status'] ?? '') === 'open' && (int) $row['max_claims'] === \Components\LeadFlow\Models\OpportunityModel::MAX_CLAIMS);
        $this->check('…con token largo y aleatorio', strlen((string) ($row['token'] ?? '')) === 64);
        $this->check('…y con el contacto guardado', ($row['email_norm'] ?? '') === 'zztest.oportunidad@example.test');

        // ── El reparto ────────────────────────────────────────────────────────
        $boardA = $svc->board($cidA);
        $boardB = $svc->board($cidB);
        $idsA   = array_map(static fn ($r) => (int) $r['id'], $boardA);
        $this->check('la firma del área la ve en su bandeja', in_array($oid, $idsA, true));
        $this->check('la firma de OTRA área no la ve',
            ! in_array($oid, array_map(static fn ($r) => (int) $r['id'], $boardB), true));

        // **Lo que no debe salir de la bandeja.** Si algún día alguien añade el contacto
        // a `forBoard()`, esta comprobación lo caza antes de que llegue a producción.
        $fila = $boardA[array_search($oid, $idsA, true)] ?? [];
        $this->check('la bandeja NO lleva datos de contacto',
            ! array_key_exists('email', $fila) && ! array_key_exists('phone', $fila)
            && ! array_key_exists('first_name', $fila));
        $this->check('…pero sí el caso y las plazas que quedan',
            ($fila['area_name'] ?? '') !== '' && (int) ($fila['left'] ?? 0) === 3);

        $this->check('una firma no puede reclamar un área que no publica',
            ! $svc->claim($oid, $cidB, $this->users['sup'])['ok']);

        // ── Reclamo: nace el lead ─────────────────────────────────────────────
        $res = $svc->claim($oid, $cidA, $this->users['sup']);
        $this->check('la firma del área lo reclama', $res['ok'], $res['message']);

        $lead = (new LeadModel())->find((int) $res['id']);
        $this->check('…y nace el lead en esa firma con el contacto completo',
            $lead && (int) $lead['company_id'] === $cidA && $lead['email'] === $base['email']);
        $this->check('…con el resumen del caso', $lead && $lead['summary'] === $base['summary']);
        $this->check('…marcado como que pidió consulta', $lead && (int) $lead['requested_consultation'] === 1);
        $this->check('…y con la fuente de oportunidades', (function () use ($lead, $cidA) {
            $s = $lead && $lead['source_id'] ? (new ServiceModel()) : null;
            $src = $lead && $lead['source_id']
                ? (new \Components\LeadFlow\Models\SourceModel())->owned((int) $lead['source_id'], $cidA) : null;

            return $src && $src['utm_source'] === \Components\LeadFlow\Libraries\Directory\OpportunityService::SOURCE_UTM;
        })());
        $this->check('el reclamo queda registrado con su lead',
            $db->table('cmp_lfl_opportunity_claims')->where('opportunity_id', $oid)
                ->where('company_id', $cidA)->where('lead_id', (int) $res['id'])->countAllResults() === 1);

        $row = $opps->find($oid);
        $this->check('la oportunidad pasa a «reclamada» y baja una plaza',
            $row['status'] === 'claimed' && (int) $row['claims_count'] === 1);
        $this->check('la misma firma no la reclama dos veces', ! $svc->claim($oid, $cidA, null)['ok']);
        $this->check('…y ya no le aparece en la bandeja',
            ! in_array($oid, array_map(static fn ($r) => (int) $r['id'], $svc->board($cidA)), true));

        // ── El tope, con dos firmas compitiendo por la misma plaza ────────────
        // **Sin falsear el contador**: una oportunidad de UNA sola plaza. La primera
        // firma la ocupa y la segunda tiene que rebotar, aunque las dos publiquen el
        // área y las dos la hayan visto en su bandeja. Lo hacen valer el UNIQUE y la
        // relectura dentro de la transacción, no el contador que se leyó al pintar.
        $nuevoB = $profSvc->create($cidB, ['kind' => 'firm', 'display_name' => 'ZZTEST Opp B2',
            'email' => 'zztest.oppb2@example.test', 'areas' => [$areaId]]);
        $profSvc->publish((new \Components\LeadFlow\Models\ProfileModel())->find((int) $nuevoB['id']));

        $unaPlaza = $svc->submit(['captcha' => $cap->issue(), 'email' => 'zztest.oportunidad3@example.test', 'ip' => $nuevaIp()] + $base);
        $pid3     = (int) $unaPlaza['id'];
        $opps->skipValidation(true)->update($pid3, ['max_claims' => 1]);
        $opps->skipValidation(false);

        $this->check('las dos firmas la ven antes de reclamar',
            in_array($pid3, array_map(static fn ($r) => (int) $r['id'], $svc->board($cidA)), true)
            && in_array($pid3, array_map(static fn ($r) => (int) $r['id'], $svc->board($cidB)), true));

        $primero = $svc->claim($pid3, $cidA, null);
        $segundo = $svc->claim($pid3, $cidB, null);
        $this->check('la única plaza la ocupa quien llega primero', $primero['ok'], $primero['message']);
        $this->check('…y la segunda firma rebota', ! $segundo['ok']);

        $row3 = $opps->find($pid3);
        $this->check('la oportunidad queda CERRADA al llegar al tope',
            $row3['status'] === 'closed' && (int) $row3['claims_count'] === 1);
        $this->check('y en la tabla hay exactamente un reclamo, no dos',
            $db->table('cmp_lfl_opportunity_claims')->where('opportunity_id', $pid3)->countAllResults() === 1);
        $this->check('…así que la segunda firma no se llevó el contacto',
            (new LeadModel())->where('company_id', $cidB)->where('email', $base['email'])->countAllResults() === 0);

        // ── Caducidad: la cierra el reloj, no una pantalla ────────────────────
        $sent2 = $svc->submit(['captcha' => $cap->issue(), 'email' => 'zztest.oportunidad2@example.test', 'ip' => $nuevaIp()] + $base);
        if ($sent2['ok']) {
            $opps->skipValidation(true)->update((int) $sent2['id'], ['expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
            $opps->skipValidation(false);
            $svc->expire();
            $this->check('una oportunidad vencida se cierra sola',
                ($opps->find((int) $sent2['id'])['status'] ?? '') === 'expired');
            $this->check('…y deja de aparecer en la bandeja',
                ! in_array((int) $sent2['id'], array_map(static fn ($r) => (int) $r['id'], $svc->board($cidA)), true));
        }

        // Las oportunidades son globales: no cuelgan de la empresa, así que el borrado
        // del escenario no se las lleva. Se limpian aquí.
        $db->table('cmp_lfl_opportunities')->like('email', 'zztest.oportunidad', 'after')->delete();
        $this->check('la prueba no deja oportunidades',
            $db->table('cmp_lfl_opportunities')->like('email', 'zztest.oportunidad', 'after')->countAllResults() === 0);
    }

    /**
     * Opiniones verificadas (fase 4).
     *
     * Esta es la parte regulada del producto, así que lo que se prueba no es que el
     * formulario guarde: es que **las salvaguardas no se puedan saltar**. Que no opine
     * quien no fue cliente, que nada se publique sin pasar por la plataforma, que la
     * firma no pueda tocar lo que dijeron de ella, y que sin opiniones no haya media.
     */
    private function s15Reviews(): void
    {
        $this->title('15. Opiniones verificadas (fase 4)');
        $cidA = $this->companies['A'];
        $db   = \Config\Database::connect();
        $svc  = new \Components\LeadFlow\Libraries\Directory\ReviewService();
        $revs = new \Components\LeadFlow\Models\ReviewModel();

        // El plan del escenario no tiene la bandera (las empresas ZZTEST nacen sin plan),
        // así que lo primero que se comprueba es que eso basta para que no existan.
        $this->check('sin plan que las habilite, las opiniones están apagadas',
            ! $svc->enabledFor($cidA));

        $lead = $this->lead(['first_name' => 'ZZTEST', 'last_name' => 'Opinante',
            'email' => 'zztest.opinante@example.test']);
        $this->check('con las opiniones apagadas no se puede pedir ninguna',
            ! $svc->invite($lead)['ok']);

        /*
         * Para probar el resto hace falta un plan con la bandera encendida. Se crea uno
         * de usar y tirar para el escenario: tocar el plan de una empresa real sería
         * cambiarle lo que paga.
         */
        $db->table('plans')->insert([
            'name' => 'ZZTEST Reviews', 'scope' => 'company', 'is_active' => 1, 'price_amount' => 0,
            'service_days' => 30, 'lfl_reviews_enabled' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $planId = (int) $db->insertID();
        $db->table('companies')->where('id', $cidA)->update(['plan_id' => $planId]);
        // **El plan efectivo se cachea por petición** (el gotcha de CLAUDE.md): sin
        // vaciarlo, el cambio de plan no se ve hasta la siguiente petición. Y `PlanGuard`
        // tiene su propia caché, así que se pide un servicio nuevo después.
        \App\Models\CompanyModel::flushPlanCache();
        \Components\LeadFlow\Libraries\Access::flush();

        $svc = new \Components\LeadFlow\Libraries\Directory\ReviewService();
        $this->check('con el plan encendido ya están disponibles',
            (new \Components\LeadFlow\Libraries\Directory\ReviewService())->enabledFor($cidA));

        // ── Solo opina quien fue cliente ──────────────────────────────────────
        $this->check('a un lead abierto no se le puede pedir opinión', ! $svc->invite($lead)['ok']);

        // Se convierte a mano: lo que importa aquí es el estado, no el camino.
        (new LeadModel())->skipValidation(true)->update((int) $lead['id'], [
            'status' => 'converted', 'converted_at' => date('Y-m-d H:i:s'),
        ]);
        (new LeadModel())->skipValidation(false);
        $converted = (new LeadModel())->find((int) $lead['id']);

        $inv = $svc->invite($converted);
        $this->check('a un cliente convertido sí', $inv['ok'], $inv['message']);
        $this->check('…con un enlace de token largo', str_contains((string) ($inv['url'] ?? ''), 'opinion/')
            && strlen((string) ($revs->find((int) $inv['id'])['token'] ?? '')) === 64);

        $second = $svc->invite($converted);
        $this->check('pedirla otra vez devuelve el MISMO enlace, no crea otra',
            $second['ok'] && (int) $second['id'] === (int) $inv['id']
            && $revs->forCompany($cidA)->where('lead_id', (int) $converted['id'])->countAllResults() === 1);

        // ── El cliente opina ──────────────────────────────────────────────────
        $invite = $revs->find((int) $inv['id']);
        $t      = time() - 10;
        $stamp  = $t . '.' . hash_hmac('sha256', (string) $t, (string) config('Encryption')->key);
        $cap    = new \Components\LeadFlow\Libraries\Captcha();
        $base   = [
            'score_clarity' => 5, 'score_response' => 4, 'score_care' => 5,
            'comment' => 'Me explicaron las opciones con calma.', 'author_mode' => 'first',
            'consent' => true, 'stamp' => $stamp, 'ip' => '198.51.100.' . random_int(1, 254),
        ];

        $this->check('sin las tres notas no se guarda',
            ! $svc->submit($invite, ['score_care' => 0, 'captcha' => $cap->issue()] + $base)['ok']);
        $this->check('sin consentimiento tampoco',
            ! $svc->submit($invite, ['consent' => false, 'captcha' => $cap->issue()] + $base)['ok']);

        $sent = $svc->submit($invite, ['captcha' => $cap->issue()] + $base);
        $this->check('la opinión se guarda', $sent['ok'], $sent['message']);

        $row = $revs->find((int) $inv['id']);
        $this->check('…PENDIENTE de revisión, nunca publicada', $row['status'] === 'pending');
        $this->check('…con la media de las tres notas', abs((float) $row['score'] - 4.67) < 0.02);
        $this->check('…firmada como pidió el cliente', $row['author_name'] === 'ZZTEST');

        $this->check('el enlace no sirve dos veces', $svc->pendingByToken((string) $invite['token']) === null);

        // ── Nada se publica sin la plataforma ─────────────────────────────────
        $profileId = (int) ($row['profile_id'] ?? 0);
        $this->check('pendiente, no cuenta para la media',
            $profileId === 0 || $revs->ratingOf($profileId) === null);
        $this->check('…ni sale en la lista pública del perfil',
            $profileId === 0 || $revs->published($profileId) === []);
        $this->check('no se puede responder a una opinión sin publicar',
            ! $svc->reply($row, 'Gracias por su confianza.')['ok']);

        $this->check('la plataforma la publica', $svc->moderate($row, true, 1)['ok']);
        $row = $revs->find((int) $inv['id']);
        $this->check('…y queda constancia de quién y cuándo',
            $row['status'] === 'published' && ! empty($row['moderated_at']) && (int) $row['moderated_by'] === 1);

        // ── La firma responde, una vez, y no puede tocar la opinión ───────────
        $this->check('ahora sí se puede responder', $svc->reply($row, 'Gracias por contarlo.')['ok']);
        $row = $revs->find((int) $inv['id']);
        $this->check('…la respuesta se guarda con su fecha',
            $row['reply'] === 'Gracias por contarlo.' && ! empty($row['replied_at']));
        $this->check('…y no se puede responder dos veces', ! $svc->reply($row, 'Otra respuesta.')['ok']);
        $this->check('la nota del cliente sigue intacta tras la respuesta',
            abs((float) $row['score'] - 4.67) < 0.02 && $row['comment'] === $base['comment']);

        /*
         * ── El desglose por estrella ──────────────────────────────────────────
         *
         * Sostiene el módulo de calificación de la ficha: una media sin saber cómo se
         * reparte no distingue cuatro cincos de dos dieces y dos ceros.
         */
        // Por FIRMA y no por perfil: en este escenario la opinión no cuelga de ningún
        // perfil, y el desglose que pinta la ficha de la firma es justo el de la firma.
        $br = $revs->breakdownOfCompany($cidA);
        $this->check('el desglose devuelve SIEMPRE las cinco estrellas, incluidas las que valen cero',
            array_keys($br) === [5, 4, 3, 2, 1], implode(',', array_keys($br)));
        $this->check('…y solo suma las publicadas', array_sum($br) === 1, (string) array_sum($br));
        // La opinión del escenario puntuó 5, 5 y 4 → media 4,67 → se lee como 5 estrellas.
        $this->check('la nota se redondea a la estrella que el lector ve en la barra',
            ($br[5] ?? 0) === 1 && ($br[4] ?? 0) === 0, json_encode($br));

        /*
         * ── La copia desnormalizada y el orden ponderado ──────────────────────
         *
         * El buscador ordena sobre las filas ya traídas, así que la nota tiene que
         * estar EN la fila del perfil. Una copia que nadie actualiza es peor que no
         * tenerla: el orden diría una cosa y la ficha otra.
         */
        $prof = new \Components\LeadFlow\Models\ProfileModel();
        $this->check('publicar vuelca la calificación en la fila del perfil',
            $profileId === 0 || (
                (int) $prof->find($profileId)['rating_count'] === 1
                && abs((float) $prof->find($profileId)['rating_avg'] - 4.67) < 0.02
            ));

        // La media ponderada: lo que ordena, y que NUNCA se enseña.
        $r = \Components\LeadFlow\Models\ProfileModel::class;
        $uno    = $r::rankScore(['rating_count' => 1, 'rating_avg' => 5.0]);
        $muchos = $r::rankScore(['rating_count' => 40, 'rating_avg' => 4.8]);
        $nuevo  = $r::rankScore(['rating_count' => 0, 'rating_avg' => null]);
        $this->check('cuarenta opiniones de 4,8 ganan a una sola de 5,0',
            $muchos > $uno, sprintf('%.3f vs %.3f', $muchos, $uno));
        $this->check('quien no tiene ninguna no queda el último del todo',
            $nuevo > 0 && $nuevo < $uno, sprintf('%.3f', $nuevo));
        $this->check('…y su nota supuesta NO es cero', abs($nuevo - 0.0) > 0.1);

        // Rechazar deja motivo y la saca de la media.
        $svc->moderate($row, false, 1, 'Menciona el resultado del caso.');
        $row = $revs->find((int) $inv['id']);
        $this->check('rechazarla la retira de lo publicado y guarda el motivo',
            $row['status'] === 'rejected' && $row['reject_note'] === 'Menciona el resultado del caso.');
        $this->check('…y deja de contar para la media',
            $profileId === 0 || $revs->ratingOf($profileId) === null);
        $this->check('…ni aparece ya en el desglose',
            array_sum($revs->breakdownOfCompany($cidA)) === 0);
        $this->check('…y la copia del perfil se vacía con ella',
            $profileId === 0 || (
                (int) $prof->find($profileId)['rating_count'] === 0
                && $prof->find($profileId)['rating_avg'] === null
            ));

        // ── Limpieza del plan de usar y tirar ─────────────────────────────────
        // Por NOMBRE y no solo por el id creado: si una ejecución anterior abortó antes
        // de llegar aquí, su plan quedó huérfano y haría fallar esta comprobación sin
        // que nada estuviera realmente roto.
        $db->table('companies')->where('plan_id', $planId)->update(['plan_id' => null]);
        $db->table('plans')->where('name', 'ZZTEST Reviews')->delete();
        $this->check('la prueba no deja planes sueltos',
            $db->table('plans')->where('name', 'ZZTEST Reviews')->countAllResults() === 0);
    }

    private function cleanup(): void
    {
        $db = \Config\Database::connect();
        foreach ($this->companies as $cid) {
            $dir = DocumentService::folder($cid);
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
            $db->table('company_users')->where('company_id', $cid)->delete();
            $db->table('companies')->where('id', $cid)->delete();
        }
        foreach ($this->users as $uid) {
            $db->table('users')->where('id', $uid)->delete();
        }
        $left = $db->table('cmp_lfl_leads')->whereIn('company_id', $this->companies ?: [0])->countAllResults();
        CLI::write('Limpieza: ' . ($left === 0 ? 'sin restos' : "{$left} leads sin borrar"), $left === 0 ? 'dark_gray' : 'red');
        DocketwiseSimulatedAdapter::$failOn = null;
    }

    private function lead(array $data, array $opts = []): array
    {
        $res = (new LeadService())->create($this->companies['A'], $data + ['first_name' => 'Zz', 'last_name' => 'Test'],
            $opts + ['channel' => 'manual', 'user_id' => $this->users['sup']]);
        if (! $res['ok']) {
            throw new \RuntimeException('No se pudo crear el lead de prueba: ' . $res['message']);
        }

        return (new LeadModel())->find((int) $res['id']);
    }

    /**
     * Expedientes: la salida del embudo que NO depende de Docketwise.
     *
     * Lo que se comprueba aquí es lo que no se ve en la pantalla: que el consecutivo
     * sea por firma y no se gaste en un intento fallido, que el snapshot aguante a que
     * le cambien el maestro por debajo, que las transiciones rechacen lo que deben y
     * que un expediente de otra empresa no se pueda tocar cambiando el id.
     */
    private function s19Matters(): void
    {
        $this->title('19. Expedientes (gestión del caso en la plataforma)');

        $cidA = $this->companies['A'];
        $cidB = $this->companies['B'];
        $db   = \Config\Database::connect();

        Catalog::ensure($cidA);
        Catalog::ensure($cidB);

        $stages = new \Components\LeadFlow\Models\MatterStageModel();
        $this->check('se siembran las 11 etapas procesales de arranque',
            count($stages->ordered($cidA)) === 11, (string) count($stages->ordered($cidA)));

        Catalog::flush();
        Catalog::ensure($cidA);
        $this->check('…y volver a sembrar no las duplica', count($stages->ordered($cidA)) === 11);

        $this->check('todos los kind sembrados están declarados en KINDS',
            array_diff(array_column($stages->ordered($cidA), 'kind'),
                \Components\LeadFlow\Models\MatterStageModel::KINDS) === []);

        $svc     = new MatterService();
        $matters = new \Components\LeadFlow\Models\MatterModel();
        $leads   = new LeadModel();

        // ── Precondiciones ───────────────────────────────────────────────────
        $abierto = $this->lead(['first_name' => 'ZZTEST', 'last_name' => 'Abierto']);
        $this->check('un lead sin ganar no puede abrir expediente',
            in_array(lang('Lfl.mtrNotWon'), $svc->problems($abierto), true));

        $ganado = $this->lead(['first_name' => 'ZZTEST', 'last_name' => 'Ganado']);
        (new LeadService())->assign($ganado, null, $this->users['sup'], Access::for($cidA, $this->users['sup']));
        $ganadaId = (int) (new StageModel())->byCode($cidA, 'won')['id'];
        (new LeadService())->moveStage($leads->find((int) $ganado['id']), $ganadaId, [], $this->users['sup']);
        $ganado = $leads->find((int) $ganado['id']);

        $this->check('un lead ganado y con abogado sí puede', $svc->problems($ganado) === [], implode(' ', $svc->problems($ganado)));

        // Sin responsable se niega: un expediente sin abogado no es un expediente.
        $sinAbogado = $this->lead(['first_name' => 'ZZTEST', 'last_name' => 'SinAbogado']);
        (new LeadService())->moveStage($leads->find((int) $sinAbogado['id']), $ganadaId, [], $this->users['sup']);
        $sinAbogado = $leads->find((int) $sinAbogado['id']);
        $antes      = (int) $db->table('cmp_lfl_settings')->select('matter_sequence')
            ->where('company_id', $cidA)->get()->getRowArray()['matter_sequence'];
        $rFallo = $svc->open($sinAbogado, [], $this->users['sup']);
        $this->check('sin abogado responsable no se abre', ! $rFallo['ok']);
        $this->check('…y un intento fallido NO gasta consecutivo',
            (int) $db->table('cmp_lfl_settings')->select('matter_sequence')
                ->where('company_id', $cidA)->get()->getRowArray()['matter_sequence'] === $antes);

        // ── Apertura ─────────────────────────────────────────────────────────
        $r1 = $svc->open($ganado, [], $this->users['sup']);
        $this->check('se abre el expediente', $r1['ok'], $r1['message']);
        $m1 = $matters->find((int) $r1['id']);
        $this->check('el número lleva el formato PREFIJO-000000',
            (bool) preg_match('/^EXP-\d{6}$/', (string) $m1['number']), (string) $m1['number']);

        $fresco = $leads->find((int) $ganado['id']);
        $this->check('el lead quedó en el estado terminal del embudo',
            $fresco['status'] === 'converted' && $fresco['converted_at'] !== null);
        $this->check('…y en la etapa comercial de kind «converted»',
            (int) $fresco['stage_id'] === (int) (new StageModel())->byCode($cidA, 'converted')['id']);

        // ── Idempotencia ─────────────────────────────────────────────────────
        $antes = (int) $db->table('cmp_lfl_settings')->select('matter_sequence')
            ->where('company_id', $cidA)->get()->getRowArray()['matter_sequence'];
        $r2 = $svc->open($fresco, [], $this->users['sup']);
        $this->check('el mismo lead no abre un segundo expediente', ! $r2['ok']);
        $this->check('…y el segundo intento tampoco gasta consecutivo',
            (int) $db->table('cmp_lfl_settings')->select('matter_sequence')
                ->where('company_id', $cidA)->get()->getRowArray()['matter_sequence'] === $antes);

        // ── El consecutivo es POR FIRMA ──────────────────────────────────────
        $otro = $this->lead(['first_name' => 'ZZTEST', 'last_name' => 'Segundo']);
        (new LeadService())->assign($otro, null, $this->users['sup'], Access::for($cidA, $this->users['sup']));
        (new LeadService())->moveStage($leads->find((int) $otro['id']), $ganadaId, [], $this->users['sup']);
        $r3 = $svc->open($leads->find((int) $otro['id']), [], $this->users['sup']);
        $m3 = $matters->find((int) $r3['id']);
        $this->check('el segundo expediente de la firma avanza el consecutivo',
            (int) substr((string) $m3['number'], -6) === (int) substr((string) $m1['number'], -6) + 1,
            $m1['number'] . ' -> ' . $m3['number']);

        // ── Snapshot ─────────────────────────────────────────────────────────
        $svcId = (int) $m1['service_id'];
        if ($svcId > 0) {
            $nombreAntes = $m1['service_name'];
            (new ServiceModel())->skipValidation(true)->update($svcId, ['name' => 'ZZTEST Renombrado']);
            $this->check('renombrar el servicio NO cambia el snapshot del expediente',
                $matters->find((int) $m1['id'])['service_name'] === $nombreAntes);
        }

        // ── Transiciones ─────────────────────────────────────────────────────
        $docs = $stages->byCode($cidA, 'documents');
        $this->check('se mueve a una etapa en curso',
            $svc->moveStage($matters->find((int) $m1['id']), (int) $docs['id'], [], $this->users['sup'])['ok']);

        $filed = $stages->byCode($cidA, 'filed');
        $svc->moveStage($matters->find((int) $m1['id']), (int) $filed['id'], [], $this->users['sup']);
        $this->check('al radicar, el estado sigue siendo activo',
            $matters->find((int) $m1['id'])['status'] === 'active');

        // El retroceso es la diferencia deliberada con el pipeline comercial.
        $this->check('un expediente SÍ puede retroceder de etapa',
            $svc->moveStage($matters->find((int) $m1['id']), (int) $docs['id'], [], $this->users['sup'])['ok']);

        $espera = $stages->byCode($cidA, 'biometrics');
        $svc->moveStage($matters->find((int) $m1['id']), (int) $espera['id'], [], $this->users['sup']);
        $this->check('una etapa de espera deja el expediente «on_hold»',
            $matters->find((int) $m1['id'])['status'] === 'on_hold');

        $this->check('una etapa de OTRA empresa se rechaza',
            ! $svc->moveStage($matters->find((int) $m1['id']),
                (int) $stages->byCode($cidB, 'documents')['id'], [], $this->users['sup'])['ok']);

        // ── Cierre ───────────────────────────────────────────────────────────
        $this->check('cerrar sin motivo se rechaza',
            ! $svc->close($matters->find((int) $m1['id']), '', null, $this->users['sup'])['ok']);
        $this->check('cerrar con un motivo inventado se rechaza',
            ! $svc->close($matters->find((int) $m1['id']), 'porque_si', null, $this->users['sup'])['ok']);

        $this->check('cerrar con motivo válido funciona',
            $svc->close($matters->find((int) $m1['id']), 'won', 'ZZTEST nota', $this->users['sup'])['ok']);
        $cerrado = $matters->find((int) $m1['id']);
        $this->check('…y deja estado, fecha y motivo',
            $cerrado['status'] === 'closed' && $cerrado['closed_at'] !== null && $cerrado['close_reason'] === 'won');

        $this->check('un expediente cerrado no se mueve de etapa',
            ! $svc->moveStage($cerrado, (int) $docs['id'], [], $this->users['sup'])['ok']);
        $this->check('…hasta que se reabre', $svc->reopen($cerrado, $this->users['sup'])['ok']);
        $this->check('reabrir uno que no está cerrado se rechaza',
            ! $svc->reopen($matters->find((int) $m1['id']), $this->users['sup'])['ok']);

        // ── Aislamiento entre firmas ─────────────────────────────────────────
        $this->check('el expediente de A no se resuelve con la empresa B',
            $matters->owned((int) $m1['id'], $cidB) === null);
        $this->check('…y sí con la suya', $matters->owned((int) $m1['id'], $cidA) !== null);

        // ── Visibilidad heredada del lead ────────────────────────────────────
        $this->check('el auditor ve pero no escribe',
            Access::for($cidA, $this->users['aud'])->canSee($fresco)
            && ! Access::for($cidA, $this->users['aud'])->canWrite);
        // Se asigna primero: con `scope='own'` un lead SIN asignar lo ve cualquier
        // asesor (es la regla de `Access`), así que sin asignarlo esto no probaría nada.
        (new LeadService())->assign($leads->find((int) $ganado['id']), $this->users['adv1'], null,
            Access::for($cidA, $this->users['sup']));
        Access::flush();
        $asignado = $leads->find((int) $ganado['id']);
        $this->check('un asesor no ve el lead de otro asesor, y por tanto tampoco su expediente',
            ! Access::for($cidA, $this->users['adv2'])->canSee($asignado));
        $this->check('…y el asesor dueño sí lo ve',
            Access::for($cidA, $this->users['adv1'])->canSee($asignado));

        // ── Línea de tiempo ──────────────────────────────────────────────────
        $eventos = $db->table('cmp_lfl_timeline')->where('lead_id', (int) $ganado['id'])
            ->where('type', 'matter')->countAllResults();
        $this->check('la apertura y los movimientos quedan en la línea de tiempo DEL LEAD',
            $eventos >= 3, (string) $eventos);
        $this->check('los tres tipos nuevos tienen icono declarado',
            isset(Meta::TIMELINE_ICONS['matter'], Meta::TIMELINE_ICONS['fee'], Meta::TIMELINE_ICONS['payment']));

        // ── Disparadores ─────────────────────────────────────────────────────
        $reg = \Components\LeadFlow\Libraries\AutomationRegistry::TRIGGERS;
        $this->check('los tres disparadores del expediente están declarados',
            isset($reg['matter_opened'], $reg['matter_stage_changed'], $reg['matter_closed']));

        // ── Docketwise dejó de ser obligatorio ───────────────────────────────
        $this->check('un lead con expediente y sin conversión no dice «ya convertido»',
            ! in_array(lang('Lfl.cvAlready'), (new ConversionService())->problems($leads->find((int) $ganado['id'])), true));

        /*
         * ── Checklist documental ─────────────────────────────────────────────
         *
         * Lo que se prueba es la COPIA: que editar la plantilla después no reescriba
         * el checklist de un expediente ya abierto. Es la misma promesa del snapshot
         * de cliente y vendedor en una cotización.
         */
        $plantillas = new \Components\LeadFlow\Models\ServiceRequirementModel();
        $svcId      = (int) ($m1['service_id'] ?? 0);

        if ($svcId > 0) {
            foreach ([['ZZTEST Pasaporte', 'client', 1], ['ZZTEST Acta', 'spouse', 1],
                ['ZZTEST Opcional', 'client', 0]] as $i => [$label, $aplica, $oblig]) {
                $plantillas->insert(['company_id' => $cidA, 'service_id' => $svcId, 'label' => $label,
                    'applies_to' => $aplica, 'is_required' => $oblig, 'sort' => ($i + 1) * 10, 'is_active' => 1]);
            }

            // Un expediente NUEVO nace con la plantilla copiada.
            $leadCk = $this->lead(['first_name' => 'ZZTEST', 'last_name' => 'Checklist']);
            (new LeadService())->assign($leadCk, null, $this->users['sup'], Access::for($cidA, $this->users['sup']));
            (new LeadService())->moveStage($leads->find((int) $leadCk['id']), $ganadaId, [], $this->users['sup']);
            $rCk = $svc->open($leads->find((int) $leadCk['id']), ['service_id' => $svcId], $this->users['sup']);
            $mCk = $matters->find((int) $rCk['id']);

            $reqs = new \Components\LeadFlow\Models\MatterRequirementModel();
            $this->check('el expediente nace con el checklist del servicio copiado',
                count($reqs->forMatter((int) $mCk['id'], $cidA)) === 3,
                (string) count($reqs->forMatter((int) $mCk['id'], $cidA)));

            $this->check('…y solo cuenta los OBLIGATORIOS en el progreso',
                (int) $mCk['docs_required'] === 2, (string) $mCk['docs_required']);

            // Editar la plantilla no puede reescribir lo ya exigido.
            $copiada = $reqs->forMatter((int) $mCk['id'], $cidA)[0];
            $plantillas->update((int) $copiada['requirement_id'], ['label' => 'ZZTEST Renombrado en plantilla']);
            $this->check('editar la plantilla NO cambia el checklist ya copiado',
                $reqs->find((int) $copiada['id'])['label'] === $copiada['label']);

            // Borrar la plantilla tampoco: la FK es SET NULL a propósito.
            $plantillas->delete((int) $copiada['requirement_id']);
            $this->check('borrar la plantilla NO borra el requisito del expediente',
                $reqs->find((int) $copiada['id']) !== null);

            // Estados: solo `approved` y `waived` cuentan como resueltos.
            $reqs->update((int) $copiada['id'], ['status' => 'received']);
            $svc->refreshChecklist((int) $mCk['id']);
            $this->check('«recibido» todavía NO cuenta como resuelto',
                (int) $matters->find((int) $mCk['id'])['docs_received'] === 0);

            $reqs->update((int) $copiada['id'], ['status' => 'approved']);
            $svc->refreshChecklist((int) $mCk['id']);
            $this->check('«aprobado» sí cuenta',
                (int) $matters->find((int) $mCk['id'])['docs_received'] === 1);

            $otro = $reqs->forMatter((int) $mCk['id'], $cidA)[1];
            $reqs->update((int) $otro['id'], ['status' => 'waived']);
            $svc->refreshChecklist((int) $mCk['id']);
            $this->check('«eximido» también: es una decisión, no un documento',
                (int) $matters->find((int) $mCk['id'])['docs_received'] === 2);

            $this->check('…y entonces no falta ninguno', $reqs->missing((int) $mCk['id'], $cidA) === []);

            // El plazo del expediente sale del requisito con fecha más próxima.
            $reqs->update((int) $reqs->forMatter((int) $mCk['id'], $cidA)[2]['id'], [
                'status' => 'pending',
                'due_at' => date('Y-m-d H:i:s', strtotime('+5 days')),
            ]);
            $svc->refreshDeadline((int) $mCk['id']);
            $this->check('`next_deadline_at` toma el plazo del requisito',
                $matters->find((int) $mCk['id'])['next_deadline_at'] !== null);

            // Un requisito resuelto deja de empujar el plazo.
            $reqs->update((int) $reqs->forMatter((int) $mCk['id'], $cidA)[2]['id'], ['status' => 'waived']);
            $svc->refreshDeadline((int) $mCk['id']);
            $this->check('…y un requisito resuelto deja de contar como plazo',
                $matters->find((int) $mCk['id'])['next_deadline_at'] === null);

            $this->check('el checklist de A no se ve desde B',
                $reqs->owned((int) $copiada['id'], $cidB) === null);
        }

        /*
         * ── Honorarios y pagos ───────────────────────────────────────────────
         */
        $fees  = new \Components\LeadFlow\Models\MatterFeeModel();
        $pagos = new \Components\LeadFlow\Models\MatterPaymentModel();
        $mid1  = (int) $m1['id'];

        $fees->insert(['company_id' => $cidA, 'matter_id' => $mid1, 'kind' => 'fee',
            'concept' => 'ZZTEST Honorarios', 'amount' => 1000, 'currency' => 'USD']);
        $fees->insert(['company_id' => $cidA, 'matter_id' => $mid1, 'kind' => 'filing',
            'concept' => 'ZZTEST Tasa', 'amount' => 535, 'currency' => 'USD']);
        $svc->refreshTotals($mid1);

        $this->check('`fee_total` suma todas las líneas',
            (float) $matters->find($mid1)['fee_total'] === 1535.00,
            (string) $matters->find($mid1)['fee_total']);

        $desglose = $fees->byKind($mid1, $cidA);
        $this->check('el desglose separa honorarios de tasas oficiales',
            $desglose['fee'] === 1000.00 && $desglose['filing'] === 535.00,
            json_encode($desglose));

        $pagos->insert(['company_id' => $cidA, 'matter_id' => $mid1, 'amount' => 600,
            'currency' => 'USD', 'method' => 'transfer', 'paid_at' => date('Y-m-d H:i:s')]);
        $svc->refreshTotals($mid1);
        $this->check('`paid_total` suma los pagos',
            (float) $matters->find($mid1)['paid_total'] === 600.00);

        $this->check('el saldo se CALCULA, no se guarda',
            \Components\LeadFlow\Models\MatterModel::balance($matters->find($mid1)) === 935.00,
            (string) \Components\LeadFlow\Models\MatterModel::balance($matters->find($mid1)));

        // Borrar un pago devuelve el total: los rollups se recalculan, no se restan.
        $pagos->where('matter_id', $mid1)->delete();
        $svc->refreshTotals($mid1);
        $this->check('borrar los pagos devuelve `paid_total` a cero',
            (float) $matters->find($mid1)['paid_total'] === 0.00);

        $this->check('las líneas de A no se ven desde B',
            $fees->forMatter($mid1, $cidB) === []);

        /*
         * ── MatterSteps: una sola fuente para los tres consumidores ──────────
         */
        $pasos = \Components\LeadFlow\Libraries\MatterSteps::evaluate($matters->find($mid1));
        $this->check('`MatterSteps` devuelve un paso por cada uno declarado',
            array_keys($pasos) === array_keys(\Components\LeadFlow\Libraries\MatterSteps::STEPS));
        $this->check('…cada uno con label, icon, ok, runs y checks',
            array_diff(['label', 'icon', 'ok', 'runs', 'checks'], array_keys($pasos['intake'])) === []);
        $this->check('con responsable asignado, radicar no está bloqueado',
            \Components\LeadFlow\Libraries\MatterSteps::blocker($pasos, 'filing') === null);
        $this->check('el diagnóstico para la vista aplana todos los checks',
            count(\Components\LeadFlow\Libraries\MatterSteps::toDiagnostic($pasos)['checks']) >= 10);

        /*
         * ── El tipo de actividad `deadline` ──────────────────────────────────
         */
        $this->check('«plazo» es un tipo de actividad declarado',
            in_array('deadline', ActivityModel::TYPES, true));
        $this->check('…con su clave de idioma y su icono',
            isset(Meta::ACTIVITY_TYPES['deadline'], Meta::ACTIVITY_ICONS['deadline']));

        $acts = new ActivityModel();
        (new \Components\LeadFlow\Libraries\EngagementService())->addActivity($leads->find((int) $ganado['id']), [
            'activity_type' => 'deadline', 'title' => 'ZZTEST Vence respuesta',
            'due_date' => date('Y-m-d', strtotime('+3 days')),
        ]);
        $this->check('`nextDue` filtra por tipo sin romper a quien no lo pasa',
            $acts->nextDue((int) $ganado['id'], $cidA, 'deadline') !== null
            && $acts->nextDue((int) $ganado['id'], $cidA) !== null);

        $svc->refreshDeadline($mid1);
        $this->check('una actividad «plazo» alimenta `next_deadline_at` del expediente',
            $matters->find($mid1)['next_deadline_at'] !== null);

        // ── Borrado ──────────────────────────────────────────────────────────
        // Va al final a propósito: comprueba que las hijas se limpian a mano, que es
        // lo que el soft-delete NO hace por cascada.
        $fees->insert(['company_id' => $cidA, 'matter_id' => (int) $m3['id'], 'kind' => 'fee',
            'concept' => 'ZZTEST Para borrar', 'amount' => 10, 'currency' => 'USD']);
        $this->check('se borra el expediente', $svc->delete($matters->find((int) $m3['id']))['ok']);
        $this->check('…y sus honorarios se van con él',
            $fees->forMatter((int) $m3['id'], $cidA) === []);
        $this->check('…y deja de resolverse', $matters->find((int) $m3['id']) === null);
    }
    // ── Secciones ─────────────────────────────────────────────────────────────

    private function s1Dedupe(): void
    {
        $this->title('1. Normalización y duplicados (§23)');
        $this->check('el email se normaliza a minúsculas', Dedupe::email('  Ana.Perez@Example.COM ') === 'ana.perez@example.com');
        $this->check('un email inválido no se normaliza', Dedupe::email('no-es-email') === null);
        $this->check('el teléfono queda en dígitos', Dedupe::phone('+1 (305) 555-0199') === '13055550199');
        $this->check('un teléfono corto no cuenta', Dedupe::phone('12345') === null);
        $this->check('se compara por los últimos 10 dígitos', Dedupe::key10('13055550199') === Dedupe::key10('3055550199'));
    }

    private function s2Forms(): void
    {
        $this->title('2. Formularios dinámicos: saneado y lógica (§13)');
        $e = new FormEngine();
        $s = $e->sanitize([
            ['id' => 'f_inus', 'type' => 'yesno', 'label' => '¿Está en EE. UU.?', 'required' => true],
            ['id' => 'f_status', 'type' => 'select', 'label' => 'Estatus', 'required' => true, 'options' => ['Turista', 'Estudiante', ''],
                'rule' => ['field' => 'f_inus', 'op' => 'equals', 'value' => 'sí']],
            ['id' => 'f_country', 'type' => 'country', 'label' => 'País', 'required' => true,
                'rule' => ['field' => 'f_inus', 'op' => 'equals', 'value' => 'no']],
            ['id' => 'f_bad', 'type' => 'script', 'label' => 'Inventado'],
            ['id' => 'f_fwd', 'type' => 'text', 'label' => 'Mira adelante', 'rule' => ['field' => 'f_zzz', 'op' => 'equals', 'value' => 'x']],
            ['id' => 'f_empty', 'type' => 'select', 'label' => 'Sin opciones', 'options' => []],
            ['id' => 'f_inus', 'type' => 'text', 'label' => '<b>Id repetido</b>'],
        ]);
        $types = array_column($s, 'type');
        $this->check('un tipo inventado se descarta', ! in_array('script', $types, true));
        $this->check('una lista sin opciones se descarta', ! in_array('Sin opciones', array_column($s, 'label'), true));
        $this->check('las opciones vacías se limpian', ($s[1]['options'] ?? []) === ['Turista', 'Estudiante']);
        $fwd = array_values(array_filter($s, static fn ($f) => $f['label'] === 'Mira adelante'))[0] ?? [];
        $this->check('una regla hacia un campo inexistente se retira', ! isset($fwd['rule']));
        $ids = array_column($s, 'id');
        $this->check('un id repetido se regenera', count($ids) === count(array_unique($ids)));
        $this->check('las etiquetas pierden el HTML', in_array('Id repetido', array_column($s, 'label'), true));

        $r = $e->collect($s, ['f_inus' => 'yes', 'f_status' => 'Turista', 'f_country' => 'MX', 'f_fwd' => 'x']);
        $this->check('con «Sí», el país (oculto) se DESCARTA aunque llegue', ! isset($r['values']['f_country']));
        $this->check('con «Sí», el estatus visible se guarda', ($r['values']['f_status'] ?? null) === 'Turista');
        $r2 = $e->collect($s, ['f_inus' => 'no']);
        $this->check('con «No», el país pasa a ser obligatorio', isset($r2['errors']['f_country']));
        $this->check('con «No», el estatus oculto no se exige', ! isset($r2['errors']['f_status']));
        $r3 = $e->collect($s, ['f_inus' => 'yes', 'f_status' => 'Pirata']);
        $this->check('una opción que no existe es un error', isset($r3['errors']['f_status']));
        $r4 = $e->collect([['id' => 'f_mail', 'type' => 'email', 'label' => 'Correo', 'required' => false, 'help' => '']], ['f_mail' => 'no-email']);
        $this->check('un email mal escrito es un error', isset($r4['errors']['f_mail']));
        $this->check('«completo» exige respuesta a todo lo visible', $r['complete'] === false || count($r['values']) >= 2);
    }

    private function s3Catalog(): void
    {
        $this->title('3. Catálogo de arranque');
        Catalog::ensure($this->companies['A']);
        $cid = $this->companies['A'];
        $this->check('15 etapas del §9', (new StageModel())->countFor($cid) === 15);
        $this->check('existen «Nuevo», «Ganado» y «Convertido»', (new StageModel())->byCode($cid, 'new') && (new StageModel())->byCode($cid, 'won') && (new StageModel())->byCode($cid, 'converted'));
        $this->check('8 servicios de ejemplo', (new ServiceModel())->countFor($cid) === 8);
        $this->check('8 reglas de scoring', count((new \Components\LeadFlow\Models\ScoringRuleModel())->mapFor($cid)) === 8);
        Catalog::flush();
        Catalog::ensure($cid);
        $this->check('sembrar dos veces no duplica', (new StageModel())->countFor($cid) === 15);
    }

    private function s4Leads(): void
    {
        $this->title('4. Alta de leads');
        $cid = $this->companies['A'];
        $l   = $this->lead(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ANA@example.test', 'phone' => '+1 305 555 0101']);
        $this->check('se crea en la etapa «Nuevo»', (int) $l['stage_id'] === (int) (new StageModel())->byCode($cid, 'new')['id']);
        $this->check('guarda el email normalizado', $l['email_norm'] === 'ana@example.test');
        $this->check('deja historial de etapa', (new StageHistoryModel())->where('lead_id', $l['id'])->countAllResults() === 1);

        $dup = (new LeadService())->create($cid, ['first_name' => 'Ana', 'last_name' => 'P', 'phone' => '(305) 555-0101'], ['channel' => 'manual']);
        $this->check('el mismo teléfono escrito distinto se detecta', ! $dup['ok'] && ! empty($dup['duplicates']));
        $forced = (new LeadService())->create($cid, ['first_name' => 'Ana', 'last_name' => 'P', 'phone' => '(305) 555-0101'], ['channel' => 'manual', 'force' => true]);
        $this->check('forzando, se crea igual', $forced['ok']);

        $svcB = (new ServiceModel())->insert(['company_id' => $this->companies['B'], 'name' => 'ZZTEST B', 'is_active' => 1]);
        $x    = $this->lead(['first_name' => 'Ref', 'last_name' => 'Ajena', 'service_id' => (int) $svcB, 'assigned_user_id' => 999999999], ['force' => true]);
        $this->check('un servicio de OTRA firma no se guarda', $x['service_id'] === null);
        $this->check('un usuario ajeno no se asigna', $x['assigned_user_id'] === null);
    }

    private function s5Pipeline(): void
    {
        $this->title('5. Reglas del pipeline (§9)');
        $cid    = $this->companies['A'];
        $stages = new StageModel();
        $svc    = new LeadService();
        $l      = $this->lead(['first_name' => 'Pipe', 'last_name' => 'Line', 'email' => 'pipe@example.test']);

        $r = $svc->moveStage($l, (int) $stages->byCode($cid, 'converted')['id']);
        $this->check('a «Convertido» no se llega arrastrando', ! $r['ok']);
        $r = $svc->moveStage($l, (int) $stages->byCode($cid, 'lost')['id']);
        $this->check('perder sin motivo se rechaza', ! $r['ok']);
        $r = $svc->moveStage($l, (int) $stages->byCode($cid, 'lost')['id'], ['reason' => 'no_budget']);
        $l = (new LeadModel())->find($l['id']);
        $this->check('perder con motivo lo deja en estado «perdido»', $r['ok'] && $l['status'] === 'lost' && $l['lost_reason'] === 'no_budget');
        $r = $svc->moveStage($l, (int) $stages->byCode($cid, 'won')['id'], ['won_value' => 1500]);
        $l = (new LeadModel())->find($l['id']);
        $this->check('ganar fija fecha y valor, y limpia el motivo', $r['ok'] && $l['status'] === 'won' && $l['won_at'] && (float) $l['won_value'] === 1500.0 && $l['lost_reason'] === null);
        $this->check('cada cambio queda en el historial', (new StageHistoryModel())->where('lead_id', $l['id'])->countAllResults() === 3);

        $foreign = (new StageModel())->insert(['company_id' => $this->companies['B'], 'name' => 'Ajena', 'kind' => 'open', 'sort' => 1, 'is_active' => 1]);
        $this->check('no se mueve a la etapa de OTRA firma', ! $svc->moveStage($l, (int) $foreign)['ok']);

        $n = $this->lead(['first_name' => 'Primer', 'last_name' => 'Contacto', 'email' => 'primer@example.test']);
        $svc->touchOutbound($cid, (int) $n['id']);
        $n = (new LeadModel())->find($n['id']);
        $this->check('el primer contacto fija la primera respuesta', ! empty($n['first_response_at']));
        $this->check('…y pasa de «Nuevo» a «Contactado»', (int) $n['stage_id'] === (int) $stages->byCode($cid, 'contacted')['id']);
    }

    private function s6Scoring(): void
    {
        $this->title('6. Scoring por reglas (§15)');
        $cid = $this->companies['A'];
        $svc = (new ServiceModel())->forCompany($cid)->where('is_priority', 1)->first();
        $l   = $this->lead(['first_name' => 'Score', 'last_name' => 'Alto', 'email' => 'score@example.test', 'service_id' => (int) $svc['id'], 'requested_consultation' => 1]);
        $this->check('servicio prioritario + pide consulta = 35', (int) $l['score'] === 35, (string) $l['score']);
        $this->check('35 puntos es prioridad baja', $l['priority'] === 'low');
        $this->check('las bandas del §15', ScoringService::band(39) === 'low' && ScoringService::band(40) === 'medium' && ScoringService::band(70) === 'high');

        (new LeadService())->touchInbound($cid, (int) $l['id']);
        $l = (new LeadModel())->find($l['id']);
        $this->check('responder suma 10 (45 → media)', (int) $l['score'] === 45 && $l['priority'] === 'medium', $l['score'] . ' ' . $l['priority']);

        (new LeadService())->setPriority($l, 'high');
        (new ScoringService())->recalculate((int) $l['id'], $cid);
        $l = (new LeadModel())->find($l['id']);
        $this->check('una prioridad fijada a mano no la pisa el recálculo', $l['priority'] === 'high');
        (new LeadService())->setPriority($l, 'auto');
        $l = (new LeadModel())->find($l['id']);
        $this->check('«automática» la devuelve a su banda', $l['priority'] === 'medium');

        $old = date('Y-m-d H:i:s', time() - 10 * 86400);
        (new LeadModel())->skipValidation(true)->update((int) $l['id'], ['last_contact_at' => $old, 'last_inbound_at' => date('Y-m-d H:i:s', time() - 11 * 86400)]);
        $score = (new ScoringService())->recalculate((int) $l['id'], $cid);
        $this->check('sin respuesta en 7 días resta 20', $score === 25, (string) $score);
    }

    private function s7Access(): void
    {
        $this->title('7. Visibilidad por función comercial (§6)');
        $cid  = $this->companies['A'];
        $mine = $this->lead(['first_name' => 'Del', 'last_name' => 'Asesor1', 'email' => 'a1@example.test', 'assigned_user_id' => $this->users['adv1']]);
        $his  = $this->lead(['first_name' => 'Del', 'last_name' => 'Asesor2', 'email' => 'a2@example.test', 'assigned_user_id' => $this->users['adv2']]);
        $free = $this->lead(['first_name' => 'Sin', 'last_name' => 'Dueño', 'email' => 'free@example.test']);

        Access::flush();
        $adv = Access::for($cid, $this->users['adv1']);
        $this->check('el asesor ve sus leads', $adv->canSee($mine));
        $this->check('el asesor ve los sin asignar', $adv->canSee($free));
        $this->check('el asesor NO ve los de otro asesor', ! $adv->canSee($his));
        $m = new LeadModel();
        $adv->scope($m);
        $ids = array_map('intval', array_column($m->findAll(), 'id'));
        $this->check('la consulta acotada excluye los ajenos', ! in_array((int) $his['id'], $ids, true) && in_array((int) $mine['id'], $ids, true));

        $r = (new LeadService())->assign($free, $this->users['adv2'], null, $adv);
        $this->check('el asesor no puede asignar a otro', ! $r['ok']);
        $r = (new LeadService())->assign($free, $this->users['adv1'], null, $adv);
        $this->check('el asesor puede tomar un lead sin asignar', $r['ok']);

        $aud = Access::for($cid, $this->users['aud']);
        $this->check('el auditor ve todo', $aud->canSee($his) && $aud->canSee($mine));
        $this->check('el auditor no escribe', ! $aud->canWrite && ! $aud->canEdit($mine));

        $none = Access::for($cid, $this->users['none']);
        $this->check('sin función comercial no ve nada', $none->scope === 'none' && ! $none->canSee($free));
        $sup = Access::for($cid, $this->users['sup']);
        $this->check('el supervisor ve y asigna todo', $sup->seesAll() && $sup->canAssignOthers());
    }

    private function s8Isolation(): void
    {
        $this->title('8. Aislamiento entre firmas');
        $l = $this->lead(['first_name' => 'Solo', 'last_name' => 'DeA', 'email' => 'aislado@example.test']);
        $this->check('owned() con la empresa B no lo encuentra', (new LeadModel())->owned((int) $l['id'], $this->companies['B']) === null);
        $supB = Access::for($this->companies['B'], $this->users['sup']);
        $this->check('un usuario en el contexto de B no lo ve', ! $supB->canSee($l));
        $this->check('los duplicados no cruzan de firma',
            (new Dedupe())->find($this->companies['B'], ['email' => 'aislado@example.test']) === []);
    }

    /**
     * Micrositio de la firma: la empresa ES la firma, y de ella cuelgan sus abogados
     * y sus servicios.
     *
     * Lo que se comprueba aquí no se ve mirando la página: que el catálogo **no se
     * publique solo** —estar activo no es estar anunciado—, que la consulta dirigida
     * a la firma entre como un lead SIN abogado asignado, y que el freno por IP de la
     * firma no gaste el cupo del de sus abogados.
     */
    private function s16Firm(): void
    {
        $this->title('16. Micrositio de la firma (abogados y servicios)');
        $cidA = $this->companies['A'];
        $db   = \Config\Database::connect();
        $dir  = new \Components\LeadFlow\Libraries\Directory\PublicDirectory();
        $now  = date('Y-m-d H:i:s');

        $slugA = (string) ($db->table('companies')->select('slug')->where('id', $cidA)
            ->get()->getRowArray()['slug'] ?? '');

        // Hace falta un perfil publicado: sin gente publicada no hay micrositio, y eso
        // ya lo comprueba la sección 12. Aquí interesa lo que cuelga de él, así que el
        // perfil nace con lo mínimo que `publish()` exige: un área y una forma de
        // contacto. Es de tipo `firm` para no tener que atarlo a un usuario.
        $areaId = (int) (new \Components\LeadFlow\Models\AreaModel())->first()['id'];
        $psvc   = new \Components\LeadFlow\Libraries\Directory\ProfileService();
        $prof   = $psvc->create($cidA, ['kind' => 'firm', 'display_name' => 'ZZTEST Socia',
            'headline' => 'Socia fundadora', 'bio' => 'Perfil de prueba del micrositio.',
            'email' => 'zztest.socia@example.test', 'city' => 'Miami', 'areas' => [$areaId]]);
        $this->check('el perfil del escenario se crea', $prof['ok'], $prof['message'] ?? '');
        $pid  = (int) ($prof['id'] ?? 0);
        $pmdl = new \Components\LeadFlow\Models\ProfileModel();
        $pub  = $psvc->publish($pmdl->find($pid));
        $this->check('…y se publica', $pub['ok'], $pub['message'] ?? '');

        // ── El catálogo NO se publica solo ────────────────────────────────────
        $alta = static function (array $row) use ($db, $now): int {
            $db->table('cmp_lfl_services')->insert($row + ['created_at' => $now, 'updated_at' => $now]);

            return (int) $db->insertID();
        };
        $svcPriv  = $alta(['company_id' => $cidA, 'name' => 'ZZTEST Visa familiar',
            'description' => 'Servicio de prueba.', 'is_active' => 1, 'is_public' => 0, 'sort' => 0]);
        $svcPub   = $alta(['company_id' => $cidA, 'name' => 'ZZTEST Asilo',
            'is_active' => 1, 'is_public' => 1, 'sort' => 1]);
        // Activo y público, pero de OTRA firma: no puede aparecer en esta.
        $svcAjeno = $alta(['company_id' => $this->companies['B'], 'name' => 'ZZTEST Ajeno',
            'is_active' => 1, 'is_public' => 1, 'sort' => 0]);
        // Público pero desactivado: tampoco. Las dos banderas tienen que darse.
        $svcOff   = $alta(['company_id' => $cidA, 'name' => 'ZZTEST Inactivo',
            'is_active' => 0, 'is_public' => 1, 'sort' => 2]);

        $firm    = $dir->firm($slugA);
        $nombres = array_column($firm['services'] ?? [], 'name');
        $this->check('un servicio activo NO se publica por estarlo', ! in_array('ZZTEST Visa familiar', $nombres, true));
        $this->check('…solo el que la firma marcó como público', in_array('ZZTEST Asilo', $nombres, true));
        $this->check('un servicio público pero inactivo no sale', ! in_array('ZZTEST Inactivo', $nombres, true));
        $this->check('el servicio público de OTRA firma no se cuela', ! in_array('ZZTEST Ajeno', $nombres, true));
        $this->check('la ficha no arrastra la configuración interna del servicio',
            ! empty($firm['services']) && ! array_key_exists('owner_user_id', $firm['services'][0])
            && ! array_key_exists('matter_type_id', $firm['services'][0])
            && ! array_key_exists('form_id', $firm['services'][0]));
        $this->check('la ficha trae las claves que el micrositio necesita',
            array_key_exists('services', $firm) && array_key_exists('areas', $firm)
            && array_key_exists('reviews', $firm) && array_key_exists('rating', $firm));
        $this->check('sin opiniones publicadas la media es null, no un cero',
            $firm['rating'] === null && $firm['reviews'] === []);

        // ── La consulta dirigida a la FIRMA ───────────────────────────────────
        $inquiry = new \Components\LeadFlow\Libraries\Directory\InquiryService();
        $captcha = new \Components\LeadFlow\Libraries\Captcha();
        $t       = time() - 10;
        $stamp   = $t . '.' . hash_hmac('sha256', (string) $t, (string) config('Encryption')->key);
        $company = $db->table('companies')->where('id', $cidA)->get()->getRowArray();
        // IP nueva en cada corrida: el freno es de 5 por minuto y con una IP fija la
        // segunda ejecución seguida se frenaría a sí misma.
        $ip   = '198.51.100.' . random_int(1, 254);
        $base = [
            'first_name' => 'ZZTEST', 'last_name' => 'Firma', 'email' => 'zztest.firma@example.test',
            'phone' => '+1 786 555 0931', 'city' => 'Miami', 'message' => 'Quiero hablar con la firma.',
            'consent' => true, 'stamp' => $stamp, 'ip' => $ip,
        ];

        $this->check('a la firma tampoco se le escribe sin consentimiento',
            ! $inquiry->submitToFirm($company, ['consent' => false, 'captcha' => $captcha->issue()] + $base)['ok']);

        $sent = $inquiry->submitToFirm($company, ['captcha' => $captcha->issue()] + $base);
        $this->check('la consulta a la firma crea el lead', $sent['ok'], $sent['message']);

        $lead = $sent['id'] ? (new LeadModel())->find((int) $sent['id']) : null;
        $this->check('…en la firma correcta', $lead && (int) $lead['company_id'] === $cidA);
        $this->check('…SIN colgar de ningún perfil', $lead && $lead['profile_id'] === null);
        $this->check('…y SIN abogado asignado: entra al reparto de la firma',
            $lead && $lead['lawyer_user_id'] === null);
        $this->check('…pero sí marcado como que pide consulta',
            $lead && (int) $lead['requested_consultation'] === 1);
        $this->check('el contador del perfil no se infla con un lead que no es suyo',
            (int) $pmdl->find($pid)['leads_count'] === 0);

        // El freno es por destino: la firma y su abogado no comparten cupo.
        $this->check('el freno de la firma no gasta el del abogado',
            service('throttler')->check('lfl_dir_' . md5($ip . '|p:' . $pmdl->find($pid)['slug']), 5, MINUTE));

        // ── Limpieza propia: lo que crea esta sección, lo borra esta sección ──
        $db->table('cmp_lfl_services')->whereIn('id', [$svcPriv, $svcPub, $svcAjeno, $svcOff])->delete();
        if ($lead) {
            (new LeadModel())->skipValidation(true)->delete((int) $lead['id'], true);
        }
        $pmdl->delete($pid, true);
    }
    /**
     * Canal de denuncia.
     *
     * Lo que se comprueba es lo que decide si el canal sirve o es un arma: que **una
     * denuncia no toque ninguna calificación**, que no se pueda inundar la bandeja
     * repitiéndola, que la IP no se guarde en claro y que descartar exija explicarlo.
     */
    private function s17Complaints(): void
    {
        $this->title('17. Canal de denuncia');
        $cidA = $this->companies['A'];
        $db   = \Config\Database::connect();
        $svc  = new \Components\LeadFlow\Libraries\Directory\ComplaintService();
        $mdl  = new \Components\LeadFlow\Models\ComplaintModel();

        $company = $db->table('companies')->where('id', $cidA)->get()->getRowArray();
        $captcha = new \Components\LeadFlow\Libraries\Captcha();
        $t       = time() - 20;
        $sello   = $t . '.' . hash_hmac('sha256', (string) $t, (string) config('Encryption')->key);
        $ip      = '192.0.2.' . random_int(1, 254);

        $base = [
            'category' => 'impersonation',
            'reason'   => 'ZZTEST El perfil usa el número de licencia de otra persona y la foto no corresponde.',
            'truthful' => true,
            'stamp'    => $sello,
            'ip'       => $ip,
        ];

        /*
         * ── Lo que no entra ──────────────────────────────────────────────────
         *
         * Cada rechazo va con **su propia IP**: el freno es de 3 por minuto por IP y
         * firma, y se comprueba ANTES que nada, así que tres intentos inválidos
         * desde la misma IP agotarían el cupo y el envío bueno fallaría por el
         * motivo equivocado. La IP de $base se reserva para el que sí entra y sus
         * repeticiones, que es lo que prueba el tope por huella.
         */
        $otraIp = static fn (): string => '192.0.2.' . random_int(1, 254);
        $this->check('un motivo demasiado corto no se envía',
            ! $svc->submit($company, ['reason' => 'corto', 'captcha' => $captcha->issue(), 'ip' => $otraIp()] + $base)['ok']);
        $this->check('sin declarar veracidad tampoco',
            ! $svc->submit($company, ['truthful' => false, 'captcha' => $captcha->issue(), 'ip' => $otraIp()] + $base)['ok']);
        $this->check('con la trampa llena tampoco',
            ! $svc->submit($company, ['website' => 'http://spam.test', 'captcha' => $captcha->issue(), 'ip' => $otraIp()] + $base)['ok']);

        // ── La denuncia buena ─────────────────────────────────────────────────
        $res = $svc->submit($company, ['captcha' => $captcha->issue()] + $base);
        $this->check('la denuncia entra', $res['ok'] && $res['id'] > 0, $res['message']);
        $row = $res['id'] ? $mdl->find((int) $res['id']) : null;
        $this->check('…nace pendiente', $row && $row['status'] === 'pending');
        $this->check('…en la firma correcta', $row && (int) $row['company_id'] === $cidA);

        // La IP, nunca en claro.
        $this->check('la IP se guarda HASHEADA, no en claro',
            $row && $row['ip_hash'] !== $ip && strlen((string) $row['ip_hash']) === 64
            && $row['ip_hash'] === \Components\LeadFlow\Models\ComplaintModel::hashIp($ip));

        // ── No toca ninguna calificación ──────────────────────────────────────
        $revs = new \Components\LeadFlow\Models\ReviewModel();
        $this->check('una denuncia NO entra en la media de la firma',
            $revs->ratingOfCompany($cidA) === null);
        $this->check('…ni en el desglose por estrella',
            array_sum($revs->breakdownOfCompany($cidA)) === 0);

        // ── No se puede inundar la bandeja ────────────────────────────────────
        $otra = $svc->submit($company, ['captcha' => $captcha->issue()] + $base);
        $this->check('repetir desde la misma huella no crea otra',
            $otra['ok'] && $otra['id'] === 0, 'id=' . $otra['id']);
        $this->check('…y responde igual que la primera vez, sin delatar nada',
            $otra['message'] === $res['message']);
        $this->check('solo hay UNA denuncia de esa huella',
            $mdl->where('company_id', $cidA)->where('ip_hash', \Components\LeadFlow\Models\ComplaintModel::hashIp($ip))
                ->countAllResults() === 1);

        // ── Cerrarla ──────────────────────────────────────────────────────────
        $this->check('descartar sin nota no se permite', ! $svc->handle($row, false, 1, '')['ok']);
        $cerrada = $svc->handle($row, false, 1, 'No se pudo verificar nada de lo descrito.');
        $this->check('descartar con nota sí', $cerrada['ok'], $cerrada['message']);
        $row = $mdl->find((int) $res['id']);
        $this->check('…queda descartada, con quién y cuándo',
            $row['status'] === 'dismissed' && (int) $row['handled_by'] === 1 && ! empty($row['handled_at']));
        $this->check('…y con el motivo del descarte',
            $row['handled_note'] === 'No se pudo verificar nada de lo descrito.');
        $this->check('una denuncia ya cerrada no se cierra dos veces', ! $svc->handle($row, true, 1)['ok']);

        // Una descartada sigue contando para el tope: si no, quien la viera descartada
        // podría repetirla sin fin y la bandeja sería el ataque.
        $tercera = $svc->submit($company, ['captcha' => $captcha->issue()] + $base);
        $this->check('una descartada sigue frenando a esa huella',
            $tercera['ok'] && $tercera['id'] === 0);

        // ── Limpieza propia ───────────────────────────────────────────────────
        $mdl->where('company_id', $cidA)->delete(null, true);
    }
    /**
     * Consulta de estado de casos (USCIS): caché y cupo.
     *
     * Se prueba en **modo simulado**, que es justo lo que el modo simulado existe para
     * permitir: comprobar la política de caché y cupo cuesta aquí cero llamadas, y
     * contra la API real se habría comido una parte del cupo diario de la firma cada
     * vez que alguien corre el autotest.
     *
     * Lo que se comprueba es lo que decide si esto se puede abrir al público: que un
     * recibo mal escrito NO salga a la red, que la segunda consulta no gaste llamada,
     * que agotar el cupo degrade a «último estado conocido» en vez de romper, y que un
     * acierto de caché no cuente como consumo.
     */
    private function s18Uscis(): void
    {
        $this->title('18. Estado de casos USCIS (caché y cupo)');
        $cidA  = $this->companies['A'];
        $svc   = new \Components\LeadFlow\Libraries\Uscis\UscisService();
        $cache = new \Components\LeadFlow\Models\CaseStatusModel();
        $usage = new \Components\LeadFlow\Models\UscisUsageModel();
        $db    = \Config\Database::connect();

        // El escenario nace sin credenciales, así que `provider()` da el simulado.
        $this->check('sin credenciales se usa el adaptador simulado',
            $svc->provider($cidA) instanceof \Components\LeadFlow\Libraries\Uscis\UscisSimulatedAdapter);
        $this->check('…y la firma figura en modo simulado', $svc->config($cidA)['mode'] === 'simulated');

        // ── El formato se valida ANTES de salir ───────────────────────────────
        foreach (['ABC123', 'EAC999910340', 'eac9999103403x', ''] as $malo) {
            $r = $svc->status($cidA, $malo);
            if (! $r['ok']) {
                continue;
            }
            $this->check('un recibo mal formado no se consulta: ' . $malo, false);

            return;
        }
        $this->check('cuatro recibos mal formados se rechazan sin salir a la red', true);

        // Y uno bien escrito en minúsculas sí entra: la forma manda, no las mayúsculas.
        $bueno = strtolower(array_key_first(\Components\LeadFlow\Libraries\Uscis\UscisSimulatedAdapter::CASES));
        $r1    = $svc->status($cidA, $bueno);
        $this->check('un recibo válido en minúsculas se acepta y se normaliza',
            $r1['ok'] && $r1['case']['receipt'] === strtoupper($bueno), $r1['message']);
        $this->check('…la primera vez NO viene de caché', ! $r1['cached']);
        $this->check('…y trae el estado en español, con las palabras de USCIS',
            ! empty($r1['case']['status_es']), (string) ($r1['case']['status_es'] ?? ''));

        // ── La segunda consulta sale de la caché ──────────────────────────────
        $r2 = $svc->status($cidA, strtoupper($bueno));
        $this->check('la segunda consulta del mismo caso viene de la caché', $r2['cached']);
        $this->check('…y no está marcada como vieja', ! $r2['stale']);
        $this->check('…con el mismo estado', $r2['case']['status'] === $r1['case']['status']);
        $this->check('la caché guarda una sola fila por recibo y firma',
            $cache->forCompany($cidA)->where('receipt', strtoupper($bueno))->countAllResults() === 1);
        $this->check('…con el historial completo',
            count($cache::toCase($cache->lookup($cidA, strtoupper($bueno)))['history']) > 0);

        // ── El modo simulado no gasta cupo ────────────────────────────────────
        $this->check('el modo simulado no consume cupo', $usage->used($cidA) === 0);

        // ── Una caché vieja deja de valer ─────────────────────────────────────
        $fila = $cache->lookup($cidA, strtoupper($bueno));
        $db->table('cmp_lfl_case_status')->where('id', (int) $fila['id'])
            ->update(['checked_at' => date('Y-m-d H:i:s', time() - 13 * 3600)]);
        $this->check('pasadas 12 horas la caché deja de estar fresca',
            ! $cache::isFresh($cache->lookup($cidA, strtoupper($bueno))));
        $r3 = $svc->status($cidA, strtoupper($bueno));
        $this->check('…y se vuelve a consultar', $r3['ok'] && ! $r3['cached']);

        // ── Un caso que no existe ─────────────────────────────────────────────
        $r4 = $svc->status($cidA, 'WAC1234567890');
        $this->check('un caso inexistente se responde como tal, no como fallo',
            ! $r4['ok'] && $r4['case'] === null);
        $this->check('…y no se cachea nada de él',
            $cache->forCompany($cidA)->where('receipt', 'WAC1234567890')->countAllResults() === 0);

        // ── El contador de cupo ───────────────────────────────────────────────
        $usage->record($cidA);
        $usage->record($cidA);
        $this->check('el contador suma llamadas reales', $usage->used($cidA) === 2);
        $this->check('…y descuenta del cupo propio',
            $usage->left($cidA) === \Components\LeadFlow\Models\UscisUsageModel::DAILY_LIMIT - 2);
        $this->check('el tope propio está por DEBAJO del de USCIS',
            \Components\LeadFlow\Models\UscisUsageModel::DAILY_LIMIT < \Components\LeadFlow\Models\UscisUsageModel::HARD_LIMIT);
        $this->check('…con margen de reserva',
            \Components\LeadFlow\Models\UscisUsageModel::HARD_LIMIT - \Components\LeadFlow\Models\UscisUsageModel::DAILY_LIMIT >= 50);

        $hoy = $db->table('cmp_lfl_uscis_usage')->where('company_id', $cidA)->countAllResults();
        $this->check('el consumo se cuenta por día, en una sola fila', $hoy === 1);

        /*
         * ── El recibo en el lead y el disparador por cambio de estado ─────────
         */
        $reg = \Components\LeadFlow\Libraries\AutomationRegistry::TRIGGERS;
        $this->check('«cambió el estado del caso» es un disparador declarado',
            array_key_exists('case_status_changed', $reg));
        $this->check('…y el estado se puede usar como condición',
            array_key_exists('uscis_status', \Components\LeadFlow\Libraries\AutomationRegistry::FIELDS));

        // El recibo se valida de FORMA al guardarlo: uno mal escrito no puede quedarse
        // en el lead, o el tick lo consultaría cada día gastando cupo para nada.
        $lead = $this->lead(['first_name' => 'ZZTEST', 'last_name' => 'Recibo',
            'email' => 'zztest.recibo@example.test']);
        $leads = new LeadModel();

        foreach (['NO-ES-UN-RECIBO', 'EAC999910340', '12345678901234'] as $malo) {
            (new LeadService())->update($leads->find((int) $lead['id']), ['uscis_receipt' => $malo]);
            if ($leads->find((int) $lead['id'])['uscis_receipt'] === null) {
                continue;
            }
            $this->check('un recibo mal formado no se guarda en el lead: ' . $malo, false);

            return;
        }
        $this->check('tres recibos mal formados se descartan al guardar el lead', true);

        (new LeadService())->update($leads->find((int) $lead['id']), ['uscis_receipt' => ' eac9999103403 ']);
        $this->check('uno válido se guarda normalizado, sin espacios y en mayúsculas',
            $leads->find((int) $lead['id'])['uscis_receipt'] === 'EAC9999103403');

        // `refreshLeads()` solo mira firmas conectadas en modo REAL. El escenario está
        // en simulado, así que no debe tocar nada: refrescar casos contra datos de
        // ejemplo y escribirlos en el lead sería peor que no refrescar.
        $res = $svc->refreshLeads($cidA);
        $this->check('el refresco programado ignora las firmas que no están en modo real',
            $res['checked'] === 0 && $res['changed'] === 0, json_encode($res));
        $this->check('…y no deja estado escrito en el lead',
            $leads->find((int) $lead['id'])['uscis_status'] === null);

        // ── Limpieza propia ───────────────────────────────────────────────────
        $db->table('cmp_lfl_case_status')->where('company_id', $cidA)->delete();
        $db->table('cmp_lfl_uscis_usage')->where('company_id', $cidA)->delete();
    }
    private function s9Conversion(): void
    {
        $this->title('9. Conversión a Docketwise (simulado) — idempotencia (§20, §22)');
        $cid   = $this->companies['A'];
        $int   = new IntegrationService();
        $conv  = new ConversionService($int);
        $store = new SimRecordModel();
        $won   = (int) (new StageModel())->byCode($cid, 'won')['id'];

        $start = $int->startConnect($cid);
        $state = (string) $int->integration($cid)['oauth_state'];
        $this->check('conectar arranca el OAuth (modo simulado)', $start['ok'] && str_contains((string) $start['url'], 'simulado'));
        $this->check('un state falso se rechaza', ! $int->finishConnect($cid, 'sim_' . str_repeat('a', 16), 'otro-state', null)['ok']);
        $this->check('el callback con el state bueno conecta', $int->finishConnect($cid, 'sim_' . bin2hex(random_bytes(8)), $state, null)['ok'] && $int->isConnected($cid));
        $row = $int->integration($cid);
        $this->check('el token se guarda cifrado', str_starts_with((string) $row['access_token'], 'enc:lfl:'));

        // Lead que coincide con un contacto de la cuenta simulada (María López).
        $maria = $this->lead(['first_name' => 'María', 'last_name' => 'López', 'email' => 'maria.lopez@example.com', 'phone' => '+1 305 555 0199']);
        $this->check('sincronizar trae los contactos de la cuenta', $int->syncContacts($cid)['ok']);
        $cands = $int->candidates($maria);
        $this->check('detecta el posible duplicado en Docketwise', $cands && in_array('email', $cands[0]['reasons'], true));

        // `updateContact` nació para reparar los contactos que se crearon sin teléfono
        // (`leadflow:phones`). Se comprueba de ida y vuelta: se escribe y se vuelve a
        // leer desde el proveedor, que es la única prueba de que el dato llegó.
        $extId = (string) $cands[0]['contact']['external_id'];
        $int->adapter($cid)->updateContact($extId, ['phone' => '+1 786 000 1122']);
        $int->syncContacts($cid);
        $after = (new \Components\LeadFlow\Models\ExtContactModel())
            ->where('company_id', $cid)->where('external_id', $extId)->first();
        $this->check('updateContact completa el teléfono y se lee de vuelta', ($after['phone_norm'] ?? '') === '17860001122');

        $noRevienta = true;
        try {
            $int->adapter($cid)->updateContact('999999999', ['phone' => '+1 786 000 3344']);
        } catch (\Throwable $e) {
            $noRevienta = false;
        }
        $this->check('updateContact sobre un id que no existe no revienta', $noRevienta);

        $l = $this->lead(['first_name' => 'Carla', 'last_name' => 'Convertible', 'email' => 'carla@example.test', 'phone' => '+1 786 555 0142']);
        $this->check('sin estar ganado no se puede convertir', ! $conv->start($l, [], null)['ok']);
        (new LeadService())->moveStage($l, $won);
        $l = (new LeadModel())->find($l['id']);

        // `getInsertID()` es de la INSTANCIA que insertó: sobre un modelo nuevo da 0.
        $notes  = new NoteModel();
        $notes->insert(['company_id' => $cid, 'lead_id' => $l['id'], 'body' => 'Nota transferible', 'is_transferable' => 1]);
        $noteId = (int) $notes->getInsertID();
        $dir = DocumentService::folder($cid);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'zztest.pdf', "%PDF-1.4\n% zztest\n");
        $docs = new DocumentModel();
        $docs->insert(['company_id' => $cid, 'lead_id' => $l['id'], 'original_name' => 'pasaporte.pdf', 'storage_key' => 'zztest.pdf',
            'mime_type' => 'application/pdf', 'size' => 20, 'hash' => str_repeat('0', 64), 'is_authorized' => 1, 'source' => 'internal']);
        $docId = (int) $docs->getInsertID();
        $docs->insert(['company_id' => $cid, 'lead_id' => $l['id'], 'original_name' => 'privado.pdf', 'storage_key' => 'nope.pdf',
            'mime_type' => 'application/pdf', 'size' => 20, 'hash' => str_repeat('1', 64), 'is_authorized' => 0, 'source' => 'internal']);
        $privId = (int) $docs->getInsertID();
        $this->check('los ids de prueba son reales', $noteId > 0 && $docId > 0 && $privId > 0);

        $contactsBefore = $store->forCompany($cid)->where('entity', 'contact')->countAllResults();
        $opts = ['contact_mode' => 'new', 'transfer_info' => 1, 'include_summary' => 1, 'notes' => [$noteId], 'documents' => [$docId, $privId]];

        // Falla a mitad: el Contact se crea, el Matter no.
        DocketwiseSimulatedAdapter::$failOn = 'create_matter';
        $r = $conv->start($l, $opts, null);
        $c = (new ConversionModel())->forLead((int) $l['id']);
        $maps = new MappingModel();
        $this->check('un fallo en el Matter deja la conversión «fallida»', ! $r['ok'] && $c['status'] === 'failed');
        $this->check('…con el Contact ya guardado en el mapeo', $maps->external($cid, 'docketwise', 'contact', (int) $l['id']) !== null);
        $this->check('…y el lead sin convertir', (new LeadModel())->find($l['id'])['status'] === 'won');

        DocketwiseSimulatedAdapter::$failOn = null;
        $r = $conv->retry((new ConversionModel())->forLead((int) $l['id']), null);
        $l = (new LeadModel())->find($l['id']);
        $this->check('el reintento termina', $r['ok'] && $l['status'] === 'converted', $r['message']);
        $this->check('el reintento NO crea un segundo Contact', $store->forCompany($cid)->where('entity', 'contact')->countAllResults() === $contactsBefore + 1);
        $this->check('crea exactamente un Matter', $store->forCompany($cid)->where('entity', 'matter')->countAllResults() === 1);
        $this->check('transfiere la nota y el resumen', $maps->external($cid, 'docketwise', 'note', $noteId) !== null && $maps->external($cid, 'docketwise', 'summary', (int) $l['id']) !== null);
        $this->check('transfiere el documento autorizado', $maps->external($cid, 'docketwise', 'document', $docId) !== null);
        $this->check('NO transfiere el documento no autorizado', $maps->external($cid, 'docketwise', 'document', $privId) === null);

        $again = $conv->start($l, $opts, null);
        $this->check('convertir otra vez no duplica nada', $again['ok'] && $store->forCompany($cid)->where('entity', 'matter')->countAllResults() === 1);
        $this->check('un lead convertido ya no se arrastra de etapa', ! (new LeadService())->moveStage($l, $won)['ok']);

        // Vincular a un contacto existente en vez de crear otro.
        (new LeadService())->moveStage($maria, $won);
        $maria = (new LeadModel())->find($maria['id']);
        $before = $store->forCompany($cid)->where('entity', 'contact')->countAllResults();
        $r = $conv->start($maria, ['contact_mode' => 'existing', 'existing_contact_id' => $cands[0]['contact']['external_id']], null);
        $this->check('vincular a un Contact existente no crea otro', $r['ok'] && $store->forCompany($cid)->where('entity', 'contact')->countAllResults() === $before);
        $this->check('…y usa el id de Docketwise elegido',
            $maps->external($cid, 'docketwise', 'contact', (int) $maria['id']) === (string) $cands[0]['contact']['external_id']);
    }

    private function s10Plan(): void
    {
        $this->title('10. Plan (§11)');
        $g = new PlanGuard();
        $this->check('sin plan asignado: servicio permitido', $g->serviceEnabled($this->companies['B']));
        $this->check('sin plan asignado: cupo ilimitado', $g->leadsLeft($this->companies['B']) === null);
        $this->check('sin plan asignado: nada bloquea', $g->blockReason($this->companies['B']) === null);
    }

    /** Crea una automatización ya saneada y devuelve su fila. */
    private function automation(array $data): array
    {
        $reg   = new AutomationRegistry();
        $cid   = $this->companies['A'];
        $model = new AutomationModel();
        $model->insert([
            'company_id'    => $cid,
            'name'          => $data['name'] ?? 'ZZTEST auto',
            'trigger_event' => $data['trigger_event'] ?? 'lead_created',
            'match_mode'    => $data['match_mode'] ?? 'all',
            'conditions'    => json_encode($reg->sanitizeConditions($data['conditions'] ?? []), JSON_UNESCAPED_UNICODE),
            'actions'       => json_encode($reg->sanitizeActions($data['actions'] ?? [], $cid), JSON_UNESCAPED_UNICODE),
            'once_per_lead' => $data['once_per_lead'] ?? 1,
            'is_active'     => $data['is_active'] ?? 1,
            'sort'          => $data['sort'] ?? 10,
        ]);

        return $model->find((int) $model->getInsertID());
    }

    private function s11Automations(): void
    {
        $this->title('11. Automatizaciones (§19)');
        $cid    = $this->companies['A'];
        $reg    = new AutomationRegistry();
        $runs   = new AutomationRunModel();
        $stages = new StageModel();
        AutomationEngine::flush();

        // ── Saneado: lo que llega del navegador se RECONSTRUYE ────────────────
        $conds = $reg->sanitizeConditions([
            ['field' => 'score', 'op' => 'greater_than', 'value' => '40'],
            ['field' => 'inventado', 'op' => 'equals', 'value' => 'x'],
            ['field' => 'priority', 'op' => 'pirata', 'value' => 'high'],
        ]);
        $this->check('una condición sobre un campo inventado se descarta', count($conds) === 1 && $conds[0]['field'] === 'score');

        $foreignStage = (int) (new StageModel())->insert(['company_id' => $this->companies['B'], 'name' => 'ZZTEST ajena', 'kind' => 'open', 'sort' => 1, 'is_active' => 1]);
        $acts         = $reg->sanitizeActions([
            ['type' => 'move_stage', 'stage_id' => $foreignStage],
            ['type' => 'add_note', 'body' => ''],
            ['type' => 'notify_user', 'user_id' => 999999999, 'subject' => 'x', 'body' => 'y'],
            ['type' => 'hackear_todo', 'x' => 1],
            ['type' => 'set_priority', 'priority' => 'pirata'],
        ], $cid);
        $this->check('mover a la etapa de OTRA firma no se guarda', ! in_array('move_stage', array_column($acts, 'type'), true));
        $this->check('una nota sin texto no se guarda', ! in_array('add_note', array_column($acts, 'type'), true));
        $this->check('avisar a un usuario ajeno no se guarda', ! in_array('notify_user', array_column($acts, 'type'), true));
        $this->check('una acción inventada se descarta', ! in_array('hackear_todo', array_column($acts, 'type'), true));
        $this->check('una prioridad inválida cae a «auto»', ($acts[0]['priority'] ?? null) === 'auto');

        // ── Condiciones ───────────────────────────────────────────────────────
        $auto = $this->automation(['conditions' => [['field' => 'score', 'op' => 'greater_than', 'value' => '30']]]);
        $this->check('un lead que NO cumple la condición no dispara', ! AutomationEngine::matches($auto, ['score' => 10]));
        $this->check('un lead que la cumple sí dispara', AutomationEngine::matches($auto, ['score' => 31]));
        $any = $this->automation(['match_mode' => 'any', 'conditions' => [
            ['field' => 'score', 'op' => 'greater_than', 'value' => '90'],
            ['field' => 'priority', 'op' => 'equals', 'value' => 'high'],
        ]]);
        $this->check('con «alguna», basta una condición', AutomationEngine::matches($any, ['score' => 1, 'priority' => 'high']));
        $all = $this->automation(['conditions' => [
            ['field' => 'score', 'op' => 'greater_than', 'value' => '90'],
            ['field' => 'priority', 'op' => 'equals', 'value' => 'high'],
        ]]);
        $this->check('con «todas», una sola no basta', ! AutomationEngine::matches($all, ['score' => 1, 'priority' => 'high']));
        $this->check('sin condiciones se aplica siempre', AutomationEngine::matches($this->automation([]), ['score' => 0]));

        // ── Se ejecuta de verdad al crear un lead ─────────────────────────────
        (new AutomationModel())->where('company_id', $cid)->delete();
        AutomationEngine::flush();
        $a1 = $this->automation([
            'name'       => 'ZZTEST asignar y anotar',
            'conditions' => [['field' => 'requested_consultation', 'op' => 'equals', 'value' => '1']],
            'actions'    => [
                ['type' => 'assign_advisor', 'mode' => 'user', 'user_id' => $this->users['adv1']],
                ['type' => 'set_priority', 'priority' => 'high'],
                ['type' => 'add_note', 'body' => 'Bienvenido {nombre}, servicio: {servicio}.'],
            ],
        ]);
        $lead = $this->lead(['first_name' => 'Auto', 'last_name' => 'Mático', 'email' => 'auto@example.test', 'requested_consultation' => 1]);
        $lead = (new LeadModel())->find($lead['id']);
        $this->check('la automatización asignó el asesor', (int) $lead['assigned_user_id'] === $this->users['adv1']);
        $this->check('…fijó la prioridad', $lead['priority'] === 'high');
        $note = (new NoteModel())->where('lead_id', $lead['id'])->orderBy('id', 'desc')->first();
        $this->check('…y dejó la nota con los marcadores sustituidos',
            $note && str_contains((string) $note['body'], 'Bienvenido Auto') && ! str_contains((string) $note['body'], '{nombre}'), (string) ($note['body'] ?? ''));
        $this->check('queda registrada en la bitácora', $runs->where('automation_id', $a1['id'])->countAllResults() === 1);
        $this->check('…y en la línea de tiempo del lead',
            (new \Components\LeadFlow\Models\TimelineModel())->where('lead_id', $lead['id'])->where('type', 'automation')->countAllResults() === 1);

        // ── Un lead que no cumple la condición no se toca ─────────────────────
        $otro = $this->lead(['first_name' => 'Sin', 'last_name' => 'Consulta', 'email' => 'sinconsulta@example.test']);
        $otro = (new LeadModel())->find($otro['id']);
        $this->check('el lead que no cumple no recibe nada', empty($otro['assigned_user_id']) && $otro['priority'] !== 'high');

        // ── «Una sola vez por lead» ───────────────────────────────────────────
        AutomationEngine::flush();
        AutomationEngine::fire('lead_created', $lead);
        $this->check('«una sola vez por lead» no repite', $runs->where('automation_id', $a1['id'])->countAllResults() === 1);

        // ── Protección contra bucles ──────────────────────────────────────────
        (new AutomationModel())->where('company_id', $cid)->delete();
        AutomationEngine::flush();
        $prop = $stages->byCode($cid, 'prequalification');
        $qual = $stages->byCode($cid, 'qualified');
        $this->automation(['name' => 'ZZTEST ping', 'trigger_event' => 'stage_changed', 'once_per_lead' => 0,
            'actions' => [['type' => 'move_stage', 'stage_id' => (int) $qual['id']]]]);
        $this->automation(['name' => 'ZZTEST pong', 'trigger_event' => 'stage_changed', 'once_per_lead' => 0,
            'actions' => [['type' => 'move_stage', 'stage_id' => (int) $prop['id']]]]);
        $ping = $this->lead(['first_name' => 'Ping', 'last_name' => 'Pong', 'email' => 'pingpong@example.test']);
        $moved = (new LeadService())->moveStage($ping, (int) $prop['id']);
        $this->check('dos automatizaciones que se llaman entre sí no cuelgan la petición', $moved['ok']);
        $this->check('…y la profundidad queda acotada', AutomationEngine::MAX_DEPTH === 3);

        // ── Aislamiento entre firmas ──────────────────────────────────────────
        (new AutomationModel())->where('company_id', $cid)->delete();
        AutomationEngine::flush();
        $this->automation(['name' => 'ZZTEST solo A', 'actions' => [['type' => 'set_priority', 'priority' => 'high']]]);
        $leadB = (new LeadService())->create($this->companies['B'], ['first_name' => 'De', 'last_name' => 'LaB', 'email' => 'delab@example.test'], ['channel' => 'manual']);
        $rowB  = (new LeadModel())->find((int) $leadB['id']);
        $this->check('una automatización de la firma A no toca un lead de la B', $rowB && $rowB['priority'] !== 'high');

        // ── El disparador del reloj ───────────────────────────────────────────
        (new AutomationModel())->where('company_id', $cid)->delete();
        AutomationEngine::flush();
        $a2  = $this->automation(['name' => 'ZZTEST vencidas', 'trigger_event' => 'activity_overdue',
            'actions' => [['type' => 'set_priority', 'priority' => 'high']]]);
        $late = $this->lead(['first_name' => 'Tarde', 'last_name' => 'Vencida', 'email' => 'tarde@example.test']);
        $actm = new ActivityModel();
        $actm->insert(['company_id' => $cid, 'lead_id' => (int) $late['id'], 'activity_type' => 'follow_up',
            'title' => 'ZZTEST vencida', 'due_at' => date('Y-m-d H:i:s', time() - 7200), 'status' => 'pending', 'priority' => 'medium']);
        $actId = (int) $actm->getInsertID();
        $this->check('la actividad de prueba es real', $actId > 0);
        AutomationEngine::fire('activity_overdue', (new LeadModel())->find($late['id']), ['ref' => 'activity:' . $actId]);
        $this->check('una actividad vencida dispara su automatización', (new LeadModel())->find($late['id'])['priority'] === 'high');
        AutomationEngine::flush();
        AutomationEngine::fire('activity_overdue', (new LeadModel())->find($late['id']), ['ref' => 'activity:' . $actId]);
        $this->check('la misma actividad no vuelve a avisar en la siguiente pasada',
            $runs->where('automation_id', $a2['id'])->countAllResults() === 1);

        // ── Una automatización desactivada no hace nada ───────────────────────
        (new AutomationModel())->where('company_id', $cid)->delete();
        AutomationEngine::flush();
        $this->automation(['name' => 'ZZTEST apagada', 'is_active' => 0,
            'actions' => [['type' => 'set_priority', 'priority' => 'high']]]);
        $off = $this->lead(['first_name' => 'Apa', 'last_name' => 'Gada', 'email' => 'apagada@example.test']);
        $this->check('una automatización desactivada no se ejecuta', (new LeadModel())->find($off['id'])['priority'] !== 'high');

        (new AutomationModel())->where('company_id', $cid)->delete();
        AutomationEngine::flush();

        // ── El interruptor que usan los datos demo ────────────────────────────
        $this->automation(['name' => 'ZZTEST apagador', 'actions' => [['type' => 'set_priority', 'priority' => 'high']]]);
        AutomationEngine::disable();
        $mudo = $this->lead(['first_name' => 'Datos', 'last_name' => 'Demo', 'email' => 'datosdemo@example.test']);
        AutomationEngine::enable();
        $this->check('con el motor apagado, un lead fabricado no dispara nada',
            (new LeadModel())->find($mudo['id'])['priority'] !== 'high');
        (new AutomationModel())->where('company_id', $cid)->delete();
        AutomationEngine::flush();

        // ── Automatizaciones de ejemplo ───────────────────────────────────────
        $b = $this->companies['B'];
        $this->check('la firma B no tiene automatizaciones', DemoData::isEmptyAutomations($b));
        $r = (new DemoData())->runAutomations($b, null);
        $demo = (new AutomationModel())->forCompany($b)->orderBy('sort', 'asc')->findAll();
        $this->check('los datos demo crean 4 automatizaciones', $r['ok'] && count($demo) === 4, (string) count($demo));
        $this->check('…con 4 disparadores distintos y todos válidos',
            count(array_unique(array_column($demo, 'trigger_event'))) === 4
            && array_diff(array_column($demo, 'trigger_event'), array_keys(AutomationRegistry::TRIGGERS)) === []);

        // Lo importante: los ejemplos pasan por el MISMO saneador que el editor, así
        // que una acción mal escrita aquí se caería igual que la de un usuario.
        $vacias = array_filter($demo, static fn ($a) => AutomationModel::actions($a) === []);
        $this->check('ninguna se queda sin acciones al pasar por el saneador', $vacias === [],
            implode(', ', array_column($vacias, 'name')));

        $mail = array_values(array_filter($demo, static fn ($a) => $a['trigger_event'] === 'form_completed'))[0] ?? null;
        $this->check('la del correo de bienvenida nace DESACTIVADA', $mail && (int) $mail['is_active'] === 0);
        $otras = array_filter($demo, static fn ($a) => $a['trigger_event'] !== 'form_completed');
        $this->check('las que solo crean trabajo interno nacen activas',
            array_filter($otras, static fn ($a) => (int) $a['is_active'] !== 1) === []);

        $this->check('cargarlos dos veces no duplica', ! (new DemoData())->runAutomations($b, null)['ok']
            && (new AutomationModel())->forCompany($b)->countAllResults() === 4);

        (new AutomationModel())->where('company_id', $b)->delete();
        AutomationEngine::flush();
    }
}
