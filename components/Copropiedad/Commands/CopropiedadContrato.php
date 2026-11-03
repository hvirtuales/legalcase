<?php

namespace Components\Copropiedad\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `php spark copropiedad:contrato`
 *
 * Audita mecánicamente el componente contra `proyectos/CONTRATO_COMPONENTES.md` y,
 * al final, imprime la **superficie del núcleo** que necesita: exactamente lo que la
 * instalación de destino tiene que ofrecer para que la carpeta funcione al soltarla
 * (contrato §15.5).
 *
 * **Lee el código SIN comentarios** (`token_get_all`). Sin eso, una nota que dice
 * «esta vista NO usa icons.css» contaría como si lo usara, y un auditor con falsos
 * positivos deja de leerse a la semana.
 */
class CopropiedadContrato extends BaseCommand
{
    protected $group       = 'Copropiedad';
    protected $name        = 'copropiedad:contrato';
    protected $description = 'Audita el componente contra el contrato e imprime la superficie del núcleo que necesita.';

    private int $ok = 0;
    private int $ko = 0;
    private array $manual = [];

    private string $raiz = '';

    public function run(array $params)
    {
        $this->raiz = rtrim(dirname(__DIR__), '/\\');

        CLI::write('Auditoría del contrato — componente Copropiedad', 'yellow');
        CLI::write($this->raiz, 'dark_gray');

        $this->s1Manifest();
        $this->s2Namespace();
        $this->s3Tablas();
        $this->s4Migraciones();
        $this->s5Rutas();
        $this->s6Permisos();
        $this->s7Menus();
        $this->s8Idiomas();
        $this->s9Plan();
        $this->s10Seguridad();
        $this->superficie();

        CLI::newLine();
        CLI::write(str_repeat('─', 50), 'dark_gray');

        if ($this->ko === 0) {
            CLI::write("CONFORME: {$this->ok} comprobaciones, 0 incumplimientos", 'green');
        } else {
            CLI::write("INCUMPLIMIENTOS: {$this->ok} correctas, {$this->ko} fallidas", 'red');
        }

        if ($this->manual) {
            CLI::newLine();
            CLI::write('Queda por revisar a mano (no se puede comprobar leyendo el código):', 'yellow');

            foreach ($this->manual as $m) {
                CLI::write('  · ' . $m, 'dark_gray');
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function check(string $que, bool $bien, string $detalle = ''): void
    {
        if ($bien) {
            $this->ok++;
            CLI::write('  OK    ' . $que, 'green');

            return;
        }
        $this->ko++;
        CLI::write('  FALLA ' . $que . ($detalle !== '' ? '  ->  ' . $detalle : ''), 'red');
    }

    private function titulo(string $t): void
    {
        CLI::newLine();
        CLI::write('=== ' . $t . ' ===', 'yellow');
    }

    /** @return string[] rutas de los archivos con esa extensión dentro del componente */
    private function archivos(string $ext, string $sub = ''): array
    {
        $base = $this->raiz . ($sub ? DIRECTORY_SEPARATOR . $sub : '');
        if (! is_dir($base)) {
            return [];
        }

        $out = [];
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === $ext) {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /**
     * El código sin comentarios ni docblocks.
     *
     * Es la diferencia entre un auditor que se lee y uno que no: los comentarios de
     * este componente hablan de lo que NO hace («no usa CDN», «no toca el núcleo»),
     * y buscar esas palabras en crudo daba positivos donde había una explicación.
     */
    private function limpio(string $src): string
    {
        $out = '';

        foreach (token_get_all($src) as $t) {
            if (is_array($t)) {
                if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    // Se conservan los saltos para no falsear los números de línea.
                    $out .= str_repeat("\n", substr_count($t[1], "\n"));

                    continue;
                }
                $out .= $t[1];

                continue;
            }
            $out .= $t;
        }

        return $out;
    }

    private function manifest(): array
    {
        return require $this->raiz . DIRECTORY_SEPARATOR . 'Manifest.php';
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function s1Manifest(): void
    {
        $this->titulo('1. Manifest (§3)');
        $m = $this->manifest();

        foreach (['code', 'name', 'version', 'table_prefix'] as $campo) {
            $this->check('declara ' . $campo, ! empty($m[$campo]));
        }

        $this->check('el code son 3–8 letras minúsculas', (bool) preg_match('/^[a-z]{3,8}$/', $m['code'] ?? ''));
        $this->check('el prefijo de tablas es cmp_<code>_', ($m['table_prefix'] ?? '') === 'cmp_' . ($m['code'] ?? '') . '_');
        $this->check('la versión es SemVer', (bool) preg_match('/^\d+\.\d+\.\d+$/', $m['version'] ?? ''));
        $this->check('declara sus rutas', ! empty($m['routes']) && is_array($m['routes']));
        $this->check('declara permisos', ! empty($m['permissions']) && is_array($m['permissions']));
        $this->check('declara el ámbito (§15.3)', in_array($m['scope'] ?? 'company', ['company', 'tenant'], true));
    }

    private function s2Namespace(): void
    {
        $this->titulo('2. Namespace propio (§0.2)');
        $m    = $this->manifest();
        $malo = [];
        $ns   = 0;

        foreach ($this->archivos('php') as $f) {
            $src = $this->limpio((string) file_get_contents($f));

            if (preg_match('/^namespace\s+([^;]+);/m', $src, $mm)) {
                $ns++;
                if (! str_starts_with(trim($mm[1]), 'Components\\Copropiedad')) {
                    $malo[] = basename($f) . ' → ' . trim($mm[1]);
                }
            }
        }

        $this->check("las {$ns} clases viven bajo Components\\Copropiedad", $malo === [], implode(', ', $malo));
        $this->check('la carpeta es PascalCase', basename($this->raiz) === 'Copropiedad');
        $this->check('el Manifest está en la raíz', is_file($this->raiz . DIRECTORY_SEPARATOR . 'Manifest.php'));
        unset($m);
    }

    private function s3Tablas(): void
    {
        $this->titulo('3. Tablas con prefijo propio (§0.3)');

        $malas = [];
        $mias  = [];

        foreach ($this->archivos('php', 'Models') as $f) {
            $src = $this->limpio((string) file_get_contents($f));

            if (preg_match('/\$table\s*=\s*[\'"]([^\'"]+)/', $src, $mm)) {
                $mias[] = $mm[1];
                if (! str_starts_with($mm[1], 'cmp_cop_')) {
                    $malas[] = basename($f) . ' → ' . $mm[1];
                }
            }
        }

        $this->check(count($mias) . ' modelos, todos con prefijo cmp_cop_', $malas === [], implode(', ', $malas));

        // Ninguna migración puede crear o redefinir una tabla del núcleo.
        $nucleo = ['users', 'companies', 'roles', 'permissions', 'plans', 'subscriptions', 'app_settings'];
        $tocan  = [];

        foreach ($this->archivos('php', 'Database') as $f) {
            $src = $this->limpio((string) file_get_contents($f));

            foreach ($nucleo as $t) {
                if (preg_match('/createTable\(\s*[\'"]' . $t . '[\'"]/', $src)
                    || preg_match('/dropTable\(\s*[\'"]' . $t . '[\'"]/', $src)) {
                    $tocan[] = basename($f) . ' → ' . $t;
                }
            }
        }

        $this->check('ninguna migración crea ni borra una tabla del núcleo', $tocan === [], implode(', ', $tocan));
    }

    private function s4Migraciones(): void
    {
        $this->titulo('4. Migraciones idempotentes y portables (§6, §8)');

        $ficheros = $this->archivos('php', 'Database' . DIRECTORY_SEPARATOR . 'Migrations');
        $this->check('hay migraciones', $ficheros !== []);

        $sinGuarda = [];
        $sinDown   = [];
        $noPortable = [];

        foreach ($ficheros as $f) {
            $src  = $this->limpio((string) file_get_contents($f));
            $base = basename($f);

            // Idempotencia: o comprueba la tabla, o comprueba el campo (las que
            // añaden columnas a `plans`).
            if (! preg_match('/tableExists\(/', $src) && ! preg_match('/fieldExists\(/', $src)) {
                $sinGuarda[] = $base;
            }
            if (! preg_match('/function down\(/', $src)) {
                $sinDown[] = $base;
            }

            // Portabilidad: nada exclusivo de un motor.
            foreach (['ENUM', "'JSON'", 'GROUP_CONCAT', 'IFNULL', 'AUTO_INCREMENT=', '->query('] as $prohibido) {
                if (str_contains($src, $prohibido)) {
                    $noPortable[] = $base . ' → ' . $prohibido;
                }
            }
        }

        $this->check(count($ficheros) . ' migraciones, todas idempotentes', $sinGuarda === [], implode(', ', $sinGuarda));
        $this->check('todas tienen down()', $sinDown === [], implode(', ', $sinDown));
        $this->check('ninguna usa tipos ni SQL de un solo motor', $noPortable === [], implode(', ', $noPortable));

        // Las FK e índices, por Forge.
        $forge = 0;

        foreach ($ficheros as $f) {
            $forge += substr_count($this->limpio((string) file_get_contents($f)), 'addForeignKey(');
        }
        $this->check($forge . ' claves foráneas declaradas por Forge', $forge > 0);

        // La migración de `plans` comprueba que la tabla exista antes de tocarla.
        $plan = array_values(array_filter($ficheros, static fn ($f) => str_contains($f, 'LimitsToPlans')));
        $this->check('existe la migración que añade las columnas a plans', $plan !== []);

        if ($plan) {
            $src = $this->limpio((string) file_get_contents($plan[0]));
            $this->check('  comprueba tableExists(\'plans\') antes de tocarla', str_contains($src, "tableExists('plans')"));
            $this->check('  comprueba fieldExists antes de cada addColumn', substr_count($src, 'fieldExists(') >= 2);
            $this->check('  su down() elimina solo sus columnas', str_contains($src, 'dropColumn'));
            // El tropiezo que hizo perder un índice del núcleo en silencio.
            $this->check('  llama a resetDataCache() tras cada ALTER', str_contains($src, 'resetDataCache()'));
        }
    }

    private function s5Rutas(): void
    {
        $this->titulo('5. Rutas (§4, §9)');

        $f   = $this->raiz . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Routes.php';
        $src = $this->limpio((string) file_get_contents($f));

        $this->check('todas las rutas cuelgan del grupo copropiedad',
            substr_count($src, "\$routes->group(") === 1 && str_contains($src, "group('copropiedad'"));
        $this->check('el grupo exige sesión', str_contains($src, "'filter' => 'auth'"));
        $this->check('el grupo usa el namespace propio', str_contains($src, "'namespace' => 'Components\\Copropiedad\\Controllers'"));

        // Ninguna ruta sin `permission:` — ni siquiera las que solo muestran.
        preg_match_all('/\$routes->(get|post|match)\((.+?)\);/s', $src, $mm, PREG_SET_ORDER);
        $sinPermiso = [];

        foreach ($mm as $r) {
            if (! str_contains($r[2], 'permission:')) {
                $sinPermiso[] = trim(substr($r[2], 0, 60));
            }
        }
        $this->check(count($mm) . ' rutas, todas con permission:', $sinPermiso === [], implode(' | ', $sinPermiso));

        // Los borrados van por POST.
        $getBorrar = [];

        foreach ($mm as $r) {
            $destructiva = preg_match('/eliminar|borrar|revocar|quitar|cerrar/i', $r[2]);
            if ($destructiva && $r[1] === 'get') {
                $getBorrar[] = trim(substr($r[2], 0, 60));
            }
        }
        $this->check('ninguna acción destructiva por GET', $getBorrar === [], implode(' | ', $getBorrar));

        // El verbo correcto: un POST destructivo no puede ir con `view`.
        $verboMalo = [];

        foreach ($mm as $r) {
            if (preg_match('/eliminar|borrar/i', $r[2]) && str_contains($r[2], 'permission:cop.view')) {
                $verboMalo[] = trim(substr($r[2], 0, 60));
            }
        }
        $this->check('ningún borrado protegido con cop.view', $verboMalo === [], implode(' | ', $verboMalo));
    }

    private function s6Permisos(): void
    {
        $this->titulo('6. Permisos (§9)');

        $m    = $this->manifest();
        $decl = array_keys($m['permissions'] ?? []);

        $malos = array_values(array_filter($decl, static fn ($p) => ! preg_match('/^cop\.[a-z]+$/', $p)));
        $this->check('todos con la convención <code>.<accion>', $malos === [], implode(', ', $malos));

        $verbos = array_map(static fn ($p) => substr($p, 4), $decl);
        $this->check('usa los verbos del núcleo (view/create/edit/delete)',
            array_diff(['view', 'create', 'edit', 'delete'], $verbos) === []);

        // Todo permiso usado en las rutas está declarado, y al revés.
        $src = $this->limpio((string) file_get_contents($this->raiz . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Routes.php'));
        preg_match_all('/permission:([a-z.]+)/', $src, $mm);
        $usados = array_values(array_unique($mm[1]));

        $this->check('todo permiso usado está declarado', array_diff($usados, $decl) === [], implode(', ', array_diff($usados, $decl)));
        $this->check('todo permiso declarado se usa', array_diff($decl, $usados) === [], implode(', ', array_diff($decl, $usados)));
    }

    private function s7Menus(): void
    {
        $this->titulo('7. Menús (§10)');

        $m     = $this->manifest();
        $menus = $m['menus'] ?? [];
        $this->check('declara menús', $menus !== []);

        // Un `icon-` que no existe NO da error: simplemente no pinta nada, así que
        // el hueco no se relaciona con el nombre del icono. Por eso se comprueba.
        $css = @file_get_contents(FCPATH . 'css' . DIRECTORY_SEPARATOR . 'icons.css') ?: '';
        $sinIcono = [];
        $sinPerm  = [];
        $malaRuta = [];

        foreach ($menus as $it) {
            if ($css !== '' && ! str_contains($css, '.' . ($it['icon'] ?? '') . '{')) {
                $sinIcono[] = $it['icon'] ?? '(vacío)';
            }
            if (empty($it['permission'])) {
                $sinPerm[] = $it['label'] ?? '?';
            }
            if (! str_starts_with($it['route'] ?? '', 'copropiedad')) {
                $malaRuta[] = $it['route'] ?? '?';
            }
        }

        $this->check('todos los iconos existen en el núcleo', $sinIcono === [], implode(', ', $sinIcono));
        $this->check('todos los menús llevan permiso', $sinPerm === [], implode(', ', $sinPerm));
        $this->check('todos apuntan a rutas del componente', $malaRuta === [], implode(', ', $malaRuta));

        if ($css === '') {
            $this->manual[] = 'No se pudo leer public/css/icons.css: comprueba a mano que los iconos del menú existan.';
        }
    }

    private function s8Idiomas(): void
    {
        $this->titulo('8. Idiomas (§7.2)');

        $es = require $this->raiz . '/Language/es/Cop.php';
        $en = require $this->raiz . '/Language/en/Cop.php';

        $soloEs = array_diff(array_keys($es), array_keys($en));
        $soloEn = array_diff(array_keys($en), array_keys($es));

        $this->check(count($es) . ' claves en es', $es !== []);
        $this->check('en tiene las mismas claves', $soloEs === [] && $soloEn === [],
            'solo es: ' . implode(', ', $soloEs) . ' | solo en: ' . implode(', ', $soloEn));

        // Ninguna clave usada sin definir: es lo que se ve como «Cop.algo» en
        // pantalla, y no da ningún error.
        $usadas = [];

        foreach (array_merge($this->archivos('php', 'Views'), $this->archivos('php', 'Controllers'),
            $this->archivos('php', 'Libraries'), $this->archivos('php', 'Models')) as $f) {
            preg_match_all("/lang\(\s*'Cop\.([a-zA-Z0-9_]+)'/", $this->limpio((string) file_get_contents($f)), $mm);
            $usadas = array_merge($usadas, $mm[1]);
        }
        $usadas = array_values(array_unique($usadas));
        $faltan = array_diff($usadas, array_keys($es));

        $this->check(count($usadas) . ' claves usadas, todas definidas', $faltan === [], implode(', ', $faltan));

        // Texto visible incrustado: se busca en los `lang()` que faltan, no en el
        // HTML, porque distinguir una etiqueta de una clase CSS no es fiable.
        $this->manual[] = 'Calidad de las traducciones al inglés (el auditor solo comprueba que existan).';
    }

    private function s9Plan(): void
    {
        $this->titulo('9. Integración con planes (§11, los 5 entregables)');

        $m = $this->manifest();

        $this->check('1. la migración de plans está en el componente',
            $this->archivos('php', 'Database' . DIRECTORY_SEPARATOR . 'Migrations') !== []
            && array_filter($this->archivos('php', 'Database' . DIRECTORY_SEPARATOR . 'Migrations'),
                static fn ($f) => str_contains($f, 'LimitsToPlans')) !== []);

        $this->check('2. declara plan_flag en el Manifest', ! empty($m['plan_flag']));
        $this->check('   y plan_limits para el Panel Empresa', ! empty($m['plan_limits']));

        $guard = $this->raiz . '/Libraries/PlanGuard.php';
        $this->check('3. existe el PlanGuard', is_file($guard));

        if (is_file($guard)) {
            $src = $this->limpio((string) file_get_contents($guard));
            $this->check('   expone serviceEnabled()', str_contains($src, 'function serviceEnabled'));
            $this->check('   expone el cupo restante', str_contains($src, 'function unitsLeft'));
            // §15.2: no puede exigir un método que un núcleo viejo no tiene.
            $this->check('   resuelve effectivePlan() con method_exists (§15.2)',
                str_contains($src, 'method_exists') && str_contains($src, 'effectivePlan'));
            $this->check('   y tiene camino alternativo si no está', str_contains($src, 'function ownPlan'));
        }

        // El enforcement tiene que estar en el SERVIDOR, no solo ocultando botones.
        $units = (string) @file_get_contents($this->raiz . '/Controllers/Units.php');
        $this->check('   se aplica al crear, en el controlador',
            str_contains($this->limpio($units), 'blockReason'));

        $this->check('4. el formulario de planes NO se toca (§15.4)',
            ! str_contains(implode(' ', array_map('basename', $this->archivos('php'))), 'plans'));

        $this->check('5. el down() de la migración retira las columnas',
            str_contains($this->limpio((string) file_get_contents(
                $this->raiz . '/Database/Migrations/2026-08-09-100011_AddCopropiedadLimitsToPlans.php'
            )), 'dropColumn'));
    }

    private function s10Seguridad(): void
    {
        $this->titulo('10. Seguridad (§12)');

        $sinCsrf = [];
        $forms   = 0;

        foreach ($this->archivos('php', 'Views') as $f) {
            $src = (string) file_get_contents($f);
            /*
             * Se cuentan los `method="post"`, no las etiquetas `<form …>` enteras:
             * un atributo con `<?= base_url(…) ?>` dentro lleva un `>` en medio, así
             * que cualquier patrón del tipo `<form[^>]*>` corta antes de llegar al
             * método y contaba UN formulario en todo el componente.
             */
            $n = preg_match_all('/method=["\']post["\']/i', $src);
            $forms += $n;

            // Cuenta los `csrf_field()` del archivo, en HTML o construidos en PHP.
            if ($n > 0 && substr_count($src, 'csrf_field()') < $n) {
                $sinCsrf[] = basename(dirname($f)) . '/' . basename($f)
                    . ' (' . $n . ' formularios, ' . substr_count($src, 'csrf_field()') . ' tokens)';
            }
        }

        $this->check($forms . ' formularios POST, todos con csrf_field()', $sinCsrf === [], implode(' | ', $sinCsrf));

        // Redirecciones abiertas: el `return_to` que viaja en los formularios.
        foreach (['Controllers/Relations.php', 'Controllers/Documents.php'] as $rel) {
            $src = $this->limpio((string) @file_get_contents($this->raiz . DIRECTORY_SEPARATOR . $rel));
            if ($src !== '' && str_contains($src, 'return_to')) {
                $this->check(basename($rel) . ' valida return_to contra un patrón cerrado',
                    str_contains($src, 'preg_match'));
            }
        }

        /*
         * Salida escapada: ninguna vista imprime una variable en crudo.
         *
         * El comentario va en bloque y no con `//`: un cierre de etiqueta PHP dentro
         * de un comentario de una línea **sale de modo PHP de verdad**, y escribir
         * aquí el patrón que se busca partía el archivo por la mitad.
         */
        $crudos = [];

        /*
         * `$celda` y `$out` quedan fuera: son HTML ya compuesto y escapado pieza a
         * pieza donde se arma (las columnas de las tablas). Volver a escaparlos
         * enseñaría las etiquetas en pantalla. Todo lo demás —variable suelta o
         * acceso a un array— tiene que pasar por `esc()` o por un cast explícito.
         */
        $patron = '/<\?=\s*\$(?!this|celda|out)([a-zA-Z_]+)((?:\[[^\]]+\])*)\s*\?>/';

        foreach ($this->archivos('php', 'Views') as $f) {
            $src = (string) file_get_contents($f);
            preg_match_all($patron, $src, $mm, PREG_SET_ORDER);

            if ($mm) {
                $nombres = array_unique(array_map(static fn ($x) => '$' . $x[1] . $x[2], $mm));
                $crudos[] = basename(dirname($f)) . '/' . basename($f) . ': ' . implode(', ', $nombres);
            }
        }
        $this->check('ninguna vista imprime una variable sin esc()', $crudos === [], implode(' | ', $crudos));

        $this->manual[] = 'Prueba de POST con id ajeno en cada acción (el autotest cubre el servicio; las rutas, a mano).';
        $this->manual[] = 'Verificación en PostgreSQL y SQL Server (aquí solo hay MySQL).';
    }

    /**
     * Lo que este componente necesita del núcleo.
     *
     * Es la lista que hay que contrastar con la instalación de destino **antes** de
     * copiar la carpeta: si algo de aquí no está allí, el componente no arranca.
     */
    private function superficie(): void
    {
        $this->titulo('Superficie del núcleo que necesita (§15.5)');

        $clases   = [];
        $metodos  = [];
        $blindados = [];
        $vistas   = [];
        $helpers  = [];

        foreach ($this->archivos('php') as $f) {
            $src = $this->limpio((string) file_get_contents($f));

            // Clases del núcleo: por `use App\…` y por `\App\…` en línea.
            preg_match_all('/(?:^use\s+|new\s+\\\\?)(App\\\\[A-Za-z\\\\]+)/m', $src, $mm);

            foreach ($mm[1] as $fqcn) {
                $clases[rtrim($fqcn, '\\')] = true;
            }

            // Métodos concretos: es lo que de verdad rompe con un núcleo más viejo.
            preg_match_all('/\(new\s+\\\\?([A-Za-z\\\\]+)\(\)\)->([a-zA-Z_]+)\(/', $src, $mm2, PREG_SET_ORDER);

            foreach ($mm2 as $x) {
                $clase = ltrim($x[1], '\\');
                if (! str_contains($clase, 'App\\') && ! in_array($clase, ['CompanyModel', 'UploadService', 'DataTable'], true)) {
                    continue;
                }
                $corto = (str_contains($clase, '\\') ? substr($clase, strrpos($clase, '\\') + 1) : $clase) . '::' . $x[2] . '()';

                $metodos[$corto] = true;
            }

            /*
             * Los métodos **blindados**: los que se piden con `method_exists()`.
             *
             * Se detectan por ahí y no por la forma de la llamada porque el patrón
             * de §15.2 es precisamente guardar el modelo en una variable y preguntar
             * antes de llamar — `(new X())->m()` no aparece nunca en ese caso, así
             * que buscarlo así dejaba fuera justo lo que hay que vigilar.
             */
            preg_match_all('/method_exists\(\s*\$?[a-zA-Z_>\\\\()\[\]\' ]+,\s*[\'"]([a-zA-Z_]+)[\'"]\s*\)/', $src, $mb);

            foreach ($mb[1] as $nombre) {
                $blindados[$nombre . '()'] = true;
                unset($metodos[$nombre . '()']);
            }

            // Vistas y partials del núcleo.
            preg_match_all("/view\(\s*'((?:layouts|partials)\/[a-z_]+)'/", $src, $mm3);

            foreach ($mm3[1] as $v) {
                $vistas[$v] = true;
            }
            preg_match_all("/\\\$this->extend\(\s*'([a-z\/]+)'/", $src, $mm4);

            foreach ($mm4[1] as $v) {
                $vistas[$v] = true;
            }

            foreach (['media_url(', 'base_url(', 'csrf_field(', 'lang(', 'esc('] as $h) {
                if (str_contains($src, $h)) {
                    $helpers[rtrim($h, '(')] = true;
                }
            }
        }

        ksort($clases);
        ksort($metodos);
        ksort($blindados);
        ksort($vistas);
        ksort($helpers);

        CLI::write('  Clases:   ' . implode(', ', array_keys($clases)));
        CLI::write('  Métodos:  ' . (implode(', ', array_keys($metodos)) ?: '—'));

        if ($blindados) {
            CLI::write('            (con respaldo si el núcleo no los tiene: ' . implode(', ', array_keys($blindados)) . ')');
        }

        CLI::write('  Vistas:   ' . implode(', ', array_keys($vistas)));
        CLI::write('  Helpers:  ' . implode(', ', array_keys($helpers)));
        CLI::write('  Filtros:  auth, permission:<perm>');
        CLI::write('  Tablas:   companies(id), users(id), plans(+2 columnas propias)');
        CLI::write('  Sesión:   active_company_id, user_id');
        CLI::write('  CSS:      .card .btn .badge .form-* .table .alert .form-section, iconos icon-*');
    }
}
