<?php

namespace Components\LeadFlow\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\LeadFlow\Libraries\AutomationRegistry;
use Components\LeadFlow\Libraries\FormEngine;
use Components\LeadFlow\Libraries\Meta;
use Components\LeadFlow\Libraries\ScoringService;

/**
 * `php spark leadflow:contrato`
 *
 * Audita mecánicamente el componente contra `proyectos/CONTRATO_COMPONENTES.md` y
 * al final imprime la **superficie del núcleo** que necesita (§15.5).
 *
 * Lee el código SIN comentarios (`token_get_all`): una nota que dice «no usa
 * icons.css» no debe contar como si lo usara.
 */
class LeadflowContrato extends BaseCommand
{
    protected $group       = 'LeadFlow';
    protected $name        = 'leadflow:contrato';
    protected $description = 'Audita LeadFlow contra el contrato de componentes e imprime la superficie del núcleo que necesita.';

    private int $ok = 0;
    private int $ko = 0;
    private array $manual = [];
    private string $root = '';

    public function run(array $params)
    {
        $this->root = rtrim(dirname(__DIR__), '/\\');
        CLI::write('Auditoría del contrato — LeadFlow Legal', 'yellow');

        $this->manifestChecks();
        $this->namespaceChecks();
        $this->migrationChecks();
        $this->routeChecks();
        $this->menuAndIconChecks();
        $this->languageChecks();
        $this->planChecks();
        $this->securityChecks();
        $this->publicViewChecks();
        $this->catalogChecks();
        $this->surface();

        CLI::newLine();
        CLI::write(str_repeat('─', 50), 'dark_gray');
        CLI::write($this->ko === 0 ? "CONFORME: {$this->ok} comprobaciones, 0 incumplimientos" : "INCUMPLIMIENTOS: {$this->ok} correctas, {$this->ko} fallidas",
            $this->ko === 0 ? 'green' : 'red');
        if ($this->manual) {
            CLI::newLine();
            CLI::write('Queda por revisar a mano:', 'yellow');
            foreach ($this->manual as $m) {
                CLI::write('  · ' . $m, 'dark_gray');
            }
        }
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

    private function files(string $sub = ''): array
    {
        $base = $this->root . ($sub ? DIRECTORY_SEPARATOR . $sub : '');
        if (! is_dir($base)) {
            return [];
        }
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    private function clean(string $src): string
    {
        $out = '';
        foreach (token_get_all($src) as $t) {
            if (is_array($t)) {
                $out .= in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? str_repeat("\n", substr_count($t[1], "\n")) : $t[1];
            } else {
                $out .= $t;
            }
        }

        return $out;
    }

    private function manifest(): array
    {
        return require $this->root . DIRECTORY_SEPARATOR . 'Manifest.php';
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function manifestChecks(): void
    {
        $this->title('1. Manifest (§3)');
        $m = $this->manifest();
        foreach (['code', 'name', 'version', 'table_prefix'] as $k) {
            $this->check('declara ' . $k, ! empty($m[$k]));
        }
        $this->check('code de 3–8 minúsculas', (bool) preg_match('/^[a-z]{3,8}$/', $m['code'] ?? ''));
        $this->check('prefijo cmp_<code>_', ($m['table_prefix'] ?? '') === 'cmp_' . $m['code'] . '_');
        $this->check('versión SemVer', (bool) preg_match('/^\d+\.\d+\.\d+$/', $m['version'] ?? ''));
        $this->check('ámbito declarado', in_array($m['scope'] ?? 'company', ['company', 'tenant'], true));
        $this->check('rótulos traducibles (name_key y label_key)', ! empty($m['name_key'])
            && array_filter($m['menus'] ?? [], static fn ($x) => empty($x['label_key'])) === []);
    }

    private function namespaceChecks(): void
    {
        $this->title('2. Namespace y tablas (§0.2, §0.3)');
        $bad = [];
        $n   = 0;
        foreach ($this->files() as $f) {
            if (preg_match('/^namespace\s+([^;]+);/m', $this->clean((string) file_get_contents($f)), $mm)) {
                $n++;
                if (! str_starts_with(trim($mm[1]), 'Components\\LeadFlow')) {
                    $bad[] = basename($f);
                }
            }
        }
        $this->check("las {$n} clases viven bajo Components\\LeadFlow", $bad === [], implode(', ', $bad));

        $tables = [];
        $badT   = [];
        foreach ($this->files('Models') as $f) {
            if (preg_match('/\$table\s*=\s*[\'"]([^\'"]+)/', $this->clean((string) file_get_contents($f)), $mm)) {
                $tables[] = $mm[1];
                if (! str_starts_with($mm[1], 'cmp_lfl_')) {
                    $badT[] = $mm[1];
                }
            }
        }
        $this->check(count($tables) . ' modelos con prefijo cmp_lfl_', $badT === [], implode(', ', $badT));

        $core  = ['users', 'companies', 'roles', 'permissions', 'plans', 'subscriptions', 'app_settings', 'company_users'];
        $touch = [];
        foreach ($this->files('Database') as $f) {
            $src = $this->clean((string) file_get_contents($f));
            foreach ($core as $t) {
                if (preg_match('/(createTable|dropTable)\(\s*[\'"]' . $t . '[\'"]/', $src)) {
                    $touch[] = basename($f) . ' → ' . $t;
                }
            }
        }
        $this->check('ninguna migración crea ni borra una tabla del núcleo', $touch === [], implode(', ', $touch));
    }

    private function migrationChecks(): void
    {
        $this->title('3. Migraciones idempotentes y portables (§6, §8)');
        $files = $this->files('Database' . DIRECTORY_SEPARATOR . 'Migrations');
        $noGuard = $noDown = $bad = [];
        foreach ($files as $f) {
            $src = $this->clean((string) file_get_contents($f));
            if (! str_contains($src, 'tableExists(') && ! str_contains($src, 'fieldExists(')) {
                $noGuard[] = basename($f);
            }
            if (! str_contains($src, 'function down(')) {
                $noDown[] = basename($f);
            }
            foreach (["'ENUM'", "'JSON'", 'GROUP_CONCAT', 'IFNULL', '->query('] as $p) {
                if (str_contains($src, $p)) {
                    $bad[] = basename($f) . ' → ' . $p;
                }
            }
        }
        $this->check(count($files) . ' migraciones, todas idempotentes', $noGuard === [], implode(', ', $noGuard));
        $this->check('todas tienen down()', $noDown === [], implode(', ', $noDown));
        $this->check('sin tipos ni SQL de un solo motor', $bad === [], implode(', ', $bad));

        $plan = array_values(array_filter($files, static fn ($f) => str_contains($f, 'LimitsToPlans')));
        $this->check('la migración de plans vive en el componente', $plan !== []);
        if ($plan) {
            $src = $this->clean((string) file_get_contents($plan[0]));
            $this->check('  comprueba tableExists(plans), fieldExists y resetDataCache()',
                str_contains($src, "tableExists('plans')") && substr_count($src, 'fieldExists(') >= 2 && str_contains($src, 'resetDataCache()'));
            $this->check('  su down() quita solo sus columnas', str_contains($src, 'dropColumn'));
        }

        // Nada de SQL crudo en el resto del componente tampoco.
        $raw = [];
        foreach ($this->files() as $f) {
            if (str_contains($f, 'Commands')) {
                continue;
            }
            // `$db->query(` es SQL crudo; `DataTable->query(callback)` es la API del núcleo.
            if (preg_match('/(?:\$db|db\(\)|connect\(\))\s*->\s*query\(/', $this->clean((string) file_get_contents($f)))) {
                $raw[] = basename($f);
            }
        }
        $this->check('ninguna consulta en SQL crudo', $raw === [], implode(', ', $raw));
    }

    private function routeChecks(): void
    {
        $this->title('4. Rutas y permisos (§4, §9)');
        $src = $this->clean((string) file_get_contents($this->root . '/Config/Routes.php'));

        [$admin, $public] = array_pad(explode("group('leadflow-publico'", $src, 2), 2, '');
        $this->check('el grupo interno exige sesión', str_contains($admin, "group('leadflow'") && str_contains($admin, "'filter' => 'auth'"));

        preg_match_all('/\$routes->(get|post|match)\((.+?)\);/s', $admin, $mm, PREG_SET_ORDER);
        $noPerm = $getDestructive = $wrongVerb = [];
        foreach ($mm as $r) {
            if (! str_contains($r[2], 'permission:')) {
                $noPerm[] = trim(substr($r[2], 0, 60));
            }
            if ($r[1] === 'get' && preg_match('/eliminar|desconectar|mover|completar/i', $r[2])) {
                $getDestructive[] = trim(substr($r[2], 0, 60));
            }
            if (preg_match('/eliminar/i', $r[2]) && str_contains($r[2], 'permission:lfl.view')) {
                $wrongVerb[] = trim(substr($r[2], 0, 60));
            }
        }
        $this->check(count($mm) . ' rutas internas, todas con permission:', $noPerm === [], implode(' | ', $noPerm));
        $this->check('ninguna acción que cambia estado por GET', $getDestructive === [], implode(' | ', $getDestructive));
        $this->check('ningún borrado con lfl.view', $wrongVerb === [], implode(' | ', $wrongVerb));
        $this->check('el grupo público no usa auth (lo gobierna el token)', $public !== '' && ! str_contains($public, "'auth'"));

        $decl = array_keys($this->manifest()['permissions'] ?? []);
        preg_match_all('/permission:([a-z.]+)/', $admin, $pm);
        $used = array_values(array_unique($pm[1]));
        $this->check('todo permiso usado está declarado', array_diff($used, $decl) === [], implode(', ', array_diff($used, $decl)));
        $this->check('todo permiso declarado se usa', array_diff($decl, $used) === [], implode(', ', array_diff($decl, $used)));
        $this->check('verbos del núcleo presentes', array_diff(['lfl.view', 'lfl.create', 'lfl.edit', 'lfl.delete'], $decl) === []);
    }

    private function menuAndIconChecks(): void
    {
        $this->title('5. Menús e iconos (§10)');
        $css = @file_get_contents(FCPATH . 'css' . DIRECTORY_SEPARATOR . 'icons.css') ?: '';
        if ($css === '') {
            $this->manual[] = 'No se pudo leer public/css/icons.css.';

            return;
        }
        $missing = [];
        foreach ($this->manifest()['menus'] as $it) {
            if (! str_contains($css, '.' . $it['icon'] . '{')) {
                $missing[] = $it['icon'];
            }
        }
        $this->check('los iconos del menú existen', $missing === [], implode(', ', $missing));

        // Todos los `icon-*` que usan las vistas internas y las bibliotecas (un icono
        // inexistente no da error: simplemente no pinta nada).
        $used = [];
        foreach (array_merge($this->files('Views'), $this->files('Libraries')) as $f) {
            if (str_contains($f, DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            preg_match_all('/\bicon-[a-z0-9-]+/', $this->clean((string) file_get_contents($f)), $mm);
            $used = array_merge($used, $mm[0]);
        }
        $used = array_values(array_unique($used));
        $miss = array_values(array_filter($used, static fn ($i) => ! str_contains($css, '.' . $i . '{')));
        $this->check(count($used) . ' iconos usados, todos existen en icons.css', $miss === [], implode(', ', $miss));
    }

    private function languageChecks(): void
    {
        $this->title('6. Idiomas (§7.2)');
        $es = require $this->root . '/Language/es/Lfl.php';
        $en = require $this->root . '/Language/en/Lfl.php';
        $this->check(count($es) . ' claves en es', $es !== []);
        $onlyEs = array_diff(array_keys($es), array_keys($en));
        $onlyEn = array_diff(array_keys($en), array_keys($es));
        $this->check('en tiene exactamente las mismas claves', $onlyEs === [] && $onlyEn === [],
            'solo es: ' . implode(', ', array_slice($onlyEs, 0, 20)) . ' | solo en: ' . implode(', ', array_slice($onlyEn, 0, 20)));

        $used = [];
        foreach ($this->files() as $f) {
            if (str_contains($f, 'Language')) {
                continue;
            }
            // Un prefijo que se concatena ('Lfl.chan_' . $canal) no es una clave: sus
            // claves completas se comprueban abajo, desde los mapas.
            preg_match_all("/'Lfl\.([a-zA-Z0-9_]+)'(?!\s*\.)/", $this->clean((string) file_get_contents($f)), $mm);
            $used = array_merge($used, $mm[1]);
        }

        // Las claves que se componen en tiempo de ejecución, desde los mapas.
        $maps = [Meta::PRIORITIES, Meta::STATUSES, Meta::STAGE_KINDS, Meta::STAGE_CODES, Meta::SOURCE_KINDS, Meta::LOST_REASONS,
            Meta::ACTIVITY_TYPES, Meta::ACTIVITY_STATUSES, Meta::APPT_MODES, Meta::APPT_STATUSES, Meta::APPT_RESULTS, Meta::CHANNELS,
            Meta::DIRECTIONS, Meta::OUTCOMES, Meta::MEMBER_ROLES, Meta::FORM_STATUSES, Meta::CONVERSION_STATUSES,
            Meta::INTEGRATION_STATUSES, ScoringService::CRITERIA_LABELS, FormEngine::TYPE_LABELS, FormEngine::OPERATOR_LABELS,
            AutomationRegistry::TRIGGERS, AutomationRegistry::OPERATOR_LABELS, AutomationRegistry::ASSIGN_MODES, AutomationRegistry::ASSIGN_TO];
        // Las acciones y los campos de las automatizaciones declaran su clave dentro
        // de una tupla, no como valor suelto.
        foreach (AutomationRegistry::ACTIONS as $def) {
            $used[] = $def[0];
        }
        foreach (AutomationRegistry::FIELDS as $def) {
            $used[] = $def[1];
        }
        foreach ($maps as $map) {
            $used = array_merge($used, array_values($map));
        }
        foreach (ScoringService::CRITERIA_LABELS as $k) {
            $used[] = $k . 'Hint';
        }
        foreach (['manual', 'public', 'import', 'demo', 'internal'] as $c) {
            $used[] = 'chan_' . $c;
        }
        foreach (['email', 'phone', 'name_phone', 'name'] as $r) {
            $used[] = 'dupBy_' . $r;
        }
        foreach (['es', 'en'] as $l) {
            $used[] = 'lang_' . $l;
        }
        foreach (\Components\LeadFlow\Models\StageModel::COLORS as $c) {
            $used[] = 'color_' . $c;
        }
        for ($d = 1; $d <= 7; $d++) {
            $used[] = 'day' . $d;
        }
        foreach (['viewAll', 'viewMine', 'viewNew', 'viewUnassigned', 'actMine', 'actOverdue', 'actToday', 'actAllPending', 'actDone',
            'tabSummary', 'tabTimeline', 'tabInfo', 'tabPrequal', 'tabActivities', 'tabAppointments', 'tabComms', 'tabDocuments',
            'tabDocketwise', 'dwTabConnection', 'dwTabSync', 'dwTabConversions', 'dwTabErrors', 'dwContact', 'dwMatter',
            'dwSummaryNote', 'dwNote', 'dwDocument', 'rt5', 'rt30', 'rt60', 'rt240', 'rtMore', 'rtNone', 'kpiGenerated',
            'kpiQualified', 'repWithConsultation', 'kpiProposals', 'kpiWonCohort', 'apConfirm', 'apCancel',
            'modeSimulated', 'modeSimulatedHint', 'modeLive', 'modeLiveHint', 'filterNoActivity', 'filterOverdue', 'filterAlt',
            'dwUsers', 'dwMatterTypes', 'demoSummary0', 'demoSummary1', 'demoSummary2', 'sources', 'campaigns', 'services',
            'roleAdvisor', 'roleLawyer', 'service', 'source', 'campaign', 'advisor', 'lawyer'] as $k) {
            $used[] = $k;
        }
        foreach (['source', 'campaign', 'service'] as $k) {
            $used[] = $k . 'New';
            $used[] = $k . 'Edit';
        }
        $used    = array_values(array_unique($used));
        $missing = array_values(array_diff($used, array_keys($es)));
        $this->check(count($used) . ' claves usadas, todas definidas', $missing === [], implode(', ', array_slice($missing, 0, 40)));
        $this->manual[] = 'Calidad de las traducciones al inglés (el auditor solo comprueba que existan).';
    }

    private function planChecks(): void
    {
        $this->title('7. Plan (§11, los 5 entregables)');
        $m = $this->manifest();
        $this->check('plan_flag y plan_limits en el Manifest', ! empty($m['plan_flag']) && ! empty($m['plan_limits']));
        $g = $this->clean((string) @file_get_contents($this->root . '/Libraries/PlanGuard.php'));
        $this->check('PlanGuard con serviceEnabled() y leadsLeft()', str_contains($g, 'function serviceEnabled') && str_contains($g, 'function leadsLeft'));
        $this->check('effectivePlan() con method_exists y camino alternativo (§15.2)', str_contains($g, 'method_exists') && str_contains($g, 'function ownPlan'));
        $this->check('una columna ausente cuenta como permitido', str_contains($g, 'array_key_exists'));
        $ls = $this->clean((string) @file_get_contents($this->root . '/Libraries/LeadService.php'));
        $this->check('el cupo se hace valer en el SERVICIO de alta', str_contains($ls, 'blockReason'));
        $pf = $this->clean((string) @file_get_contents($this->root . '/Controllers/PublicForm.php'));
        $this->check('el formulario público comprueba el plan', str_contains($pf, 'serviceEnabled'));
        $this->check('el formulario de planes NO se toca (§15.4)', ! is_dir($this->root . '/Views/plans'));
    }

    private function securityChecks(): void
    {
        $this->title('8. Seguridad (§12, §31)');
        $noCsrf = [];
        $forms  = 0;
        foreach ($this->files('Views') as $f) {
            $src = (string) file_get_contents($f);
            $n   = preg_match_all('/method=["\']post["\']/i', $src);
            $forms += $n;
            if ($n > 0 && substr_count($src, 'csrf_field()') < $n) {
                $noCsrf[] = basename(dirname($f)) . '/' . basename($f);
            }
        }
        $this->check("{$forms} formularios POST, todos con csrf_field()", $noCsrf === [], implode(', ', $noCsrf));

        $raw = [];
        /*
         * Quedan fuera, por convención de nombre, dos casos que se emiten en crudo a
         * propósito: `$…Html` (HTML ya compuesto y escapado pieza a pieza) y `$…Hex`
         * (un color validado contra un patrón: `esc()` lo convertiría en un color
         * inválido). Todo lo demás pasa por `esc()` o por un cast explícito.
         */
        $pattern = '/<\?=\s*\$(?!this\b)(?![a-zA-Z_]*(?:Html|Hex)\b)([a-zA-Z_]+)((?:\[[^\]]+\])*)\s*\?>/';
        foreach ($this->files('Views') as $f) {
            preg_match_all($pattern, (string) file_get_contents($f), $mm, PREG_SET_ORDER);
            if ($mm) {
                $raw[] = basename(dirname($f)) . '/' . basename($f) . ': ' . implode(', ', array_unique(array_map(static fn ($x) => '$' . $x[1], $mm)));
            }
        }
        $this->check('ninguna vista imprime una variable sin esc()', $raw === [], implode(' | ', $raw));

        $balance = [];
        foreach ($this->files('Views') as $f) {
            $src = (string) file_get_contents($f);
            foreach (['style', 'script'] as $tag) {
                if (substr_count($src, '<' . $tag) !== substr_count($src, '</' . $tag . '>')) {
                    $balance[] = basename($f) . ' <' . $tag . '>';
                }
            }
        }
        $this->check('<style> y <script> equilibrados en todas las vistas', $balance === [], implode(', ', $balance));

        $im = $this->clean((string) file_get_contents($this->root . '/Models/IntegrationModel.php'));
        $pm = $this->clean((string) file_get_contents($this->root . '/Models/ProviderModel.php'));
        $this->check('tokens y secreto cifrados en reposo', str_contains($im, 'Crypto::enc') && str_contains($pm, 'Crypto::enc'));

        $docs = $this->clean((string) file_get_contents($this->root . '/Libraries/DocumentService.php'));
        $this->check('documentos fuera de public/, con hash y tipo por contenido',
            str_contains($docs, 'WRITEPATH') && str_contains($docs, "hash_file('sha256'") && str_contains($docs, 'getMimeType()'));

        $base = $this->clean((string) file_get_contents($this->root . '/Controllers/BaseLfl.php'));
        $this->check('return_to validado contra un patrón cerrado', str_contains($base, 'preg_match'));
        $this->check('los mensajes flash se escapan (el layout los pinta en crudo)', str_contains($base, "setFlashdata(\$type, esc("));

        $ad = $this->clean((string) file_get_contents($this->root . '/Libraries/Integrations/DocketwiseSimulatedAdapter.php'));
        $ah = $this->clean((string) file_get_contents($this->root . '/Libraries/Integrations/DocketwiseHttpAdapter.php'));
        $this->check('los dos adaptadores implementan la interfaz común (§37)',
            str_contains($ad, 'implements IntegrationAdapter') && str_contains($ah, 'implements IntegrationAdapter'));

        $this->manual[] = 'Prueba de POST con id ajeno en cada acción (el autotest cubre servicios y visibilidad; las rutas, a mano).';
        $this->manual[] = 'Adaptador HTTP de Docketwise SIN PROBAR contra la API real (no hay sandbox): validar al activar el modo live.';
        $this->manual[] = 'Verificación en PostgreSQL y SQL Server (aquí solo hay MySQL).';
    }

    private function publicViewChecks(): void
    {
        $this->title('9. Vistas públicas autosuficientes (§7.3)');
        $bad = [];
        foreach ($this->files('Views' . DIRECTORY_SEPARATOR . 'public') as $f) {
            $src = $this->clean((string) file_get_contents($f));
            foreach (['tailwind.css', 'icons.css', 'fonts.css', 'layouts/admin', 'rel="stylesheet"', 'class="icon ', 'cdn.'] as $p) {
                if (str_contains($src, $p)) {
                    $bad[] = basename($f) . ' → ' . $p;
                }
            }
        }
        $this->check('sin assets del núcleo, sin hojas externas y sin CDN', $bad === [], implode(', ', $bad));
        $layout = (string) file_get_contents($this->root . '/Views/public/_layout.php');
        $this->check('tienen CSS propio con modo oscuro', str_contains($layout, 'prefers-color-scheme: dark'));
        $this->check('el color de marca se valida antes de emitirse', str_contains($layout, "preg_match('/^#[0-9a-fA-F]{6}$/'"));
    }

    /**
     * Catálogo de funcionalidades del manual.
     *
     * Un manual que enlaza a una ruta que ya no existe, o que exige un permiso que
     * nadie siembra, es peor que no tener manual: manda a alguien a una pantalla que
     * no abre y le hace dudar de todo lo demás. Esto lo comprueba sin abrir nada.
     *
     * Los iconos del catálogo ya los cubre la sección 5, que escanea `Libraries/`.
     */
    private function catalogChecks(): void
    {
        $this->title('10. Catálogo de funcionalidades del manual');

        $catalog = \Components\LeadFlow\Libraries\HelpCatalog::items();
        $items   = array_merge(...array_values($catalog));
        $perms   = array_keys($this->manifest()['permissions'] ?? []);
        $routes  = (string) file_get_contents($this->root . '/Config/Routes.php');
        $prefix  = trim((string) ($this->manifest()['routes'][0] ?? ''), '/');

        // Toda clave de idioma del catálogo, en los DOS idiomas.
        $es = require $this->root . '/Language/es/Lfl.php';
        $en = require $this->root . '/Language/en/Lfl.php';
        $faltan = [];
        foreach (array_keys($catalog) as $g) {
            foreach (['fnGrp' . ucfirst($g), 'fnGrp' . ucfirst($g) . 'D'] as $k) {
                foreach (['es' => $es, 'en' => $en] as $l => $a) {
                    if (! isset($a[$k])) {
                        $faltan[] = "{$l}:{$k}";
                    }
                }
            }
        }
        foreach ($items as $it) {
            $claves = ['fn' . $it['key'], 'fn' . $it['key'] . 'D'];
            if (! empty($it['where'])) {
                $claves[] = $it['where'];
            }
            foreach ($claves as $k) {
                foreach (['es' => $es, 'en' => $en] as $l => $a) {
                    if (! isset($a[$k])) {
                        $faltan[] = "{$l}:{$k}";
                    }
                }
            }
        }
        $this->check(count($items) . ' funcionalidades, todas con nombre y detalle en es y en',
            $faltan === [], implode(', ', $faltan));

        // Cada ruta declarada tiene que existir de verdad en `Config/Routes.php`.
        $rotas = [];
        foreach ($items as $it) {
            if (empty($it['route'])) {
                continue;
            }
            // La ruta del catálogo es absoluta («leadflow/leads»); en el archivo está
            // relativa al grupo («leads»), así que se compara sin el prefijo.
            $rel = $prefix !== '' && str_starts_with((string) $it['route'], $prefix . '/')
                ? substr((string) $it['route'], strlen($prefix) + 1)
                : (string) $it['route'];
            if (! preg_match('~[\'"]' . preg_quote($rel, '~') . '[\'"]~', $routes)) {
                $rotas[] = $it['route'];
            }
        }
        $this->check('todas las pantallas que enlaza existen en las rutas', $rotas === [], implode(', ', $rotas));

        // Y cada permiso que anuncia tiene que ser uno de los que siembra el Manifest.
        $malos = [];
        foreach ($items as $it) {
            if (! empty($it['perm']) && ! in_array($it['perm'], $perms, true)) {
                $malos[] = $it['key'] . ' → ' . $it['perm'];
            }
        }
        $this->check('los permisos que anuncia son los del Manifest', $malos === [], implode(', ', $malos));

        // Una entrada con `plan` tiene que nombrar un servicio que `planStatus()` conozca,
        // o la insignia saldría siempre en «no incluido» sin que nadie lo note.
        $conocidos = array_keys(\Components\LeadFlow\Libraries\HelpCatalog::planStatus(0));
        $raros     = [];
        foreach ($items as $it) {
            if (! empty($it['plan']) && ! in_array($it['plan'], $conocidos, true)) {
                $raros[] = $it['key'] . ' → ' . $it['plan'];
            }
        }
        $this->check('los servicios de plan que cita son los que el guardián resuelve',
            $raros === [], implode(', ', $raros));
    }
    private function surface(): void
    {
        $this->title('Superficie del núcleo que necesita (§15.5)');
        $classes = $views = [];
        foreach ($this->files() as $f) {
            $src = $this->clean((string) file_get_contents($f));
            preg_match_all('/\\\\?(App\\\\(?:Models|Libraries|Controllers)\\\\[A-Za-z]+)/', $src, $mm);
            foreach ($mm[1] as $c) {
                $classes[$c] = true;
            }
            preg_match_all("/view\(\s*'((?:layouts|partials)\/[a-z_]+)'/", $src, $v1);
            preg_match_all("/\\\$this->extend\(\s*'([a-z\/]+)'/", $src, $v2);
            foreach (array_merge($v1[1], $v2[1]) as $v) {
                $views[$v] = true;
            }
        }
        ksort($classes);
        ksort($views);
        CLI::write('  Clases:   ' . implode(', ', array_keys($classes)));
        CLI::write('  Métodos:  UserModel::hasPermission(), UserModel::getUserWithRoles(), CompanyModel::isManager(),');
        CLI::write('            CompanyUserModel::getUsers(), CompanyMailer::send(), DataTable (searchable/sortable/query/paginate)');
        CLI::write('            (con respaldo si faltan: CompanyModel::effectivePlan(), CompanyModel::isUsable())');
        CLI::write('  Vistas:   ' . implode(', ', array_keys($views)));
        CLI::write('  Filtros:  auth, permission:<perm>, throttler, csrf');
        /*
         * El recuento de columnas de `plans` sale del Manifest y no se escribe a mano:
         * cada fase que añade un servicio de plan añade una columna, y un número fijo
         * aquí envejece en silencio justo en la línea que la instalación de destino
         * tiene que leer para saber qué debe ofrecer.
         */
        $planCols = 0;
        $man      = require $this->root . '/Manifest.php';
        foreach (['plan_flag', 'plan_limits', 'plan_fields'] as $k) {
            $v = $man[$k] ?? null;
            $planCols += is_array($v) ? count($v) : ($v ? 1 : 0);
        }
        CLI::write("  Tablas:   companies(id), users(id), company_users, plans(+{$planCols} columnas propias)");
        CLI::write('  Sesión:   active_company_id, user_id');
        CLI::write('  Vendor:   chillerlan/php-qrcode (QR), GD (captcha), intl (nombres de país)');
    }
}
