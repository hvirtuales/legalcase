<?php

namespace Components\Mapas\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Mapas\Models\PointModel;

/**
 * `php spark mapas:contrato`
 *
 * Audita mecánicamente el componente contra `proyectos/CONTRATO_COMPONENTES.md`
 * y, al final, imprime la **superficie del núcleo** que necesita: exactamente lo
 * que la instalación de destino tiene que ofrecer para que la carpeta funcione
 * al soltarla (§15.5).
 *
 * **Lee el código SIN comentarios** (`token_get_all`). Sin eso, una nota que
 * dice «esta vista NO usa icons.css» contaría como si lo usara, y un auditor con
 * falsos positivos deja de leerse a la semana.
 *
 * Además de las reglas del contrato comprueba **lo que a este componente le ha
 * mordido de verdad**, que es donde un auditor gana su sitio: una clave de
 * idioma que el guion pide y el servidor no le manda, un `icon-` que no existe,
 * un `getJSON()` sin mirar antes el `Content-Type`, una respuesta JSON sin
 * `csrf_hash()`, una animación sin salida para quien pidió menos movimiento y
 * una propiedad de presentación que el editor escribe y el modelo descarta.
 */
class MapasContrato extends BaseCommand
{
    protected $group       = 'Mapas';
    protected $name        = 'mapas:contrato';
    protected $description = 'Audita el componente contra el contrato e imprime la superficie del núcleo que necesita.';

    private int $ok = 0;
    private int $ko = 0;
    private array $manual = [];

    private string $raiz = '';

    public function run(array $params)
    {
        $this->raiz = rtrim(dirname(__DIR__), '/\\');

        CLI::write('Auditoría del contrato — componente Mapas', 'yellow');
        CLI::write($this->raiz, 'dark_gray');

        $this->s1Manifest();
        $this->s2Namespace();
        $this->s3Tablas();
        $this->s4Migraciones();
        $this->s5Rutas();
        $this->s6Permisos();
        $this->s7Menus();
        $this->s8Idiomas();
        $this->s9Publicas();
        $this->s10Plan();
        $this->s11Seguridad();
        $this->s12Assets();
        $this->s13Propio();
        $this->superficie();

        CLI::newLine();
        CLI::write(str_repeat('─', 60), 'dark_gray');

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

    // ─────────────────────────────────────────────────────────── utilidades

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
        if (!is_dir($base)) {
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

    /** El código sin comentarios ni docblocks. */
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

    /**
     * Lo mismo para JavaScript, que `token_get_all` no entiende.
     *
     * Basta con quitar `/* … *\/` y `// …`: no hay que interpretar el guion,
     * solo evitar que un comentario que menciona algo cuente como si lo usara.
     */
    private function limpioJs(string $src): string
    {
        $src = preg_replace('#/\*.*?\*/#s', '', $src);

        return preg_replace('#(^|[^:])//.*$#m', '$1', $src);
    }

    private function manifest(): array
    {
        return require $this->raiz . DIRECTORY_SEPARATOR . 'Manifest.php';
    }

    private function leer(string $rel): string
    {
        return (string) @file_get_contents($this->raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    }

    // ───────────────────────────────────────────────────────── el contrato

    private function s1Manifest(): void
    {
        $this->titulo('1. Manifest (§3)');
        $m = $this->manifest();

        foreach (['code', 'name', 'version', 'table_prefix'] as $campo) {
            $this->check('declara ' . $campo, !empty($m[$campo]));
        }

        $this->check('el code son 3–8 letras minúsculas', (bool) preg_match('/^[a-z]{3,8}$/', $m['code'] ?? ''));
        $this->check('el prefijo de tablas es cmp_<code>_', ($m['table_prefix'] ?? '') === 'cmp_' . ($m['code'] ?? '') . '_');
        $this->check('la versión es SemVer', (bool) preg_match('/^\d+\.\d+\.\d+$/', $m['version'] ?? ''));
        $this->check('declara sus rutas', !empty($m['routes']) && is_array($m['routes']));
        $this->check('declara permisos', !empty($m['permissions']) && is_array($m['permissions']));
        $this->check('declara el ámbito (§15.3)', in_array($m['scope'] ?? 'company', ['company', 'tenant'], true));

        // Los rótulos se traducen por clave, no cambiando el literal: el Manifest
        // lo lee el gestor al instalar, antes de que haya locale.
        $this->check('el nombre declara su clave de idioma', !empty($m['name_key']));

        $sinClave = [];

        foreach ($m['menus'] ?? [] as $it) {
            if (empty($it['label_key'])) {
                $sinClave[] = $it['label'] ?? '?';
            }
        }

        foreach ($m['plan_limits'] ?? [] as $l) {
            if (empty($l['label_key'])) {
                $sinClave[] = $l['column'] ?? '?';
            }
        }
        $this->check('todos los rótulos declaran su clave', $sinClave === [], implode(', ', $sinClave));
    }

    private function s2Namespace(): void
    {
        $this->titulo('2. Namespace propio (§0.2)');

        $malo = [];
        $ns   = 0;

        foreach ($this->archivos('php') as $f) {
            $src = $this->limpio((string) file_get_contents($f));

            if (preg_match('/^namespace\s+([^;]+);/m', $src, $mm)) {
                $ns++;
                if (!str_starts_with(trim($mm[1]), 'Components\\Mapas')) {
                    $malo[] = basename($f) . ' → ' . trim($mm[1]);
                }
            }
        }

        $this->check("las {$ns} clases viven bajo Components\\Mapas", $malo === [], implode(', ', $malo));
        $this->check('la carpeta es PascalCase', basename($this->raiz) === 'Mapas');
        $this->check('el Manifest está en la raíz', is_file($this->raiz . DIRECTORY_SEPARATOR . 'Manifest.php'));

        // §0.4: nada de depender de una clase que el componente añadiría al núcleo.
        $propias = ['PlanImage', 'PlanGuard', 'MapService', 'JsonColumns'];
        $faltan  = array_values(array_filter($propias, fn ($c) => !is_file($this->raiz . '/Libraries/' . $c . '.php')
            && !is_file($this->raiz . '/Models/' . $c . '.php')));
        $this->check('lleva su propia copia de lo que necesita (§0.4)', $faltan === [], implode(', ', $faltan));
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
                if (!str_starts_with($mm[1], 'cmp_map_')) {
                    $malas[] = basename($f) . ' → ' . $mm[1];
                }
            }
        }

        $this->check(count($mias) . ' modelos, todos con prefijo cmp_map_', $malas === [], implode(', ', $malas));

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

        $sinGuarda  = [];
        $sinDown    = [];
        $noPortable = [];

        foreach ($ficheros as $f) {
            $src  = $this->limpio((string) file_get_contents($f));
            $base = basename($f);

            if (!preg_match('/tableExists\(/', $src) && !preg_match('/fieldExists\(/', $src)) {
                $sinGuarda[] = $base;
            }
            if (!preg_match('/function down\(/', $src)) {
                $sinDown[] = $base;
            }

            // Portabilidad: nada exclusivo de un motor. `'JSON'` entre comillas
            // porque es el TIPO de columna; la palabra suelta aparece en nombres
            // de método legítimos.
            foreach (['ENUM', "'JSON'", 'GROUP_CONCAT', 'IFNULL', 'AUTO_INCREMENT=', '->query('] as $prohibido) {
                if (str_contains($src, $prohibido)) {
                    $noPortable[] = $base . ' → ' . $prohibido;
                }
            }
        }

        $this->check(count($ficheros) . ' migraciones, todas idempotentes', $sinGuarda === [], implode(', ', $sinGuarda));
        $this->check('todas tienen down()', $sinDown === [], implode(', ', $sinDown));
        $this->check('ninguna usa tipos ni SQL de un solo motor', $noPortable === [], implode(', ', $noPortable));

        $forge = 0;

        foreach ($ficheros as $f) {
            $forge += substr_count($this->limpio((string) file_get_contents($f)), 'addForeignKey(');
        }
        $this->check($forge . ' claves foráneas declaradas por Forge', $forge > 0);

        $plan = array_values(array_filter($ficheros, static fn ($f) => str_contains($f, 'LimitsToPlans')));
        $this->check('existe la migración que añade las columnas a plans', $plan !== []);

        if ($plan) {
            $src = $this->limpio((string) file_get_contents($plan[0]));
            $this->check('  comprueba tableExists(\'plans\') antes de tocarla', str_contains($src, "tableExists('plans')"));
            $this->check('  comprueba fieldExists antes de cada addColumn', str_contains($src, 'fieldExists('));
            $this->check('  su down() elimina solo sus columnas', str_contains($src, 'dropColumn'));
            // El tropiezo que hizo perder un índice del núcleo en silencio.
            $this->check('  llama a resetDataCache() tras cada ALTER', str_contains($src, 'resetDataCache()'));
        }
    }

    private function s5Rutas(): void
    {
        $this->titulo('5. Rutas (§4, §9)');

        $src = $this->limpio($this->leer('Config/Routes.php'));

        $this->check('las rutas del panel cuelgan del grupo mapas',
            substr_count($src, '$routes->group(') === 1 && str_contains($src, "group('mapas'"));
        $this->check('el grupo exige sesión', str_contains($src, "'filter' => 'auth'"));
        $this->check('el grupo usa el namespace propio',
            str_contains($src, "'namespace' => 'Components\\Mapas\\Controllers'"));

        // El grupo va hasta su cierre; lo de después son las rutas PÚBLICAS, que
        // no llevan sesión a propósito y hay que auditar por separado.
        $ini    = strpos($src, "group('mapas'");
        $fin    = strrpos($src, '});');
        $grupo  = substr($src, $ini, $fin - $ini);
        $fuera  = substr($src, $fin);

        preg_match_all('/\$routes->(get|post|match)\((.+?)\);/s', $grupo, $mm, PREG_SET_ORDER);
        $sinPermiso = [];

        foreach ($mm as $r) {
            if (!str_contains($r[2], 'permission:')) {
                $sinPermiso[] = trim(substr($r[2], 0, 60));
            }
        }
        $this->check(count($mm) . ' rutas del panel, todas con permission:', $sinPermiso === [], implode(' | ', $sinPermiso));

        $getBorrar = [];

        foreach ($mm as $r) {
            if (preg_match('/eliminar|borrar|revocar/i', $r[2]) && $r[1] === 'get') {
                $getBorrar[] = trim(substr($r[2], 0, 60));
            }
        }
        $this->check('ninguna acción destructiva por GET', $getBorrar === [], implode(' | ', $getBorrar));

        $verboMalo = [];

        foreach ($mm as $r) {
            if (preg_match('/eliminar|borrar/i', $r[2]) && str_contains($r[2], 'permission:map.view')) {
                $verboMalo[] = trim(substr($r[2], 0, 60));
            }
        }
        $this->check('ningún borrado protegido con map.view', $verboMalo === [], implode(' | ', $verboMalo));

        // Las públicas: solo lectura y nada más. Un POST ahí fuera sería una
        // escritura sin sesión ni token.
        preg_match_all('/\$routes->(get|post|match)\((.+?)\);/s', $fuera, $pub, PREG_SET_ORDER);
        $escriben = array_values(array_filter($pub, static fn ($r) => $r[1] !== 'get'));

        $this->check(count($pub) . ' rutas públicas, todas de solo lectura', $escriben === [],
            implode(' | ', array_map(static fn ($r) => trim(substr($r[2], 0, 40)), $escriben)));

        // El orden importa: `mapa/recurso/(:segment)` tiene que ir ANTES que
        // `mapa/(:segment)`, o un token llamado «recurso» colisionaría.
        $this->check('el asset se declara antes que el token público',
            strpos($fuera, "mapa/recurso/") !== false
            && strpos($fuera, "mapa/recurso/") < strpos($fuera, "get('mapa/(:segment)'"));
    }

    private function s6Permisos(): void
    {
        $this->titulo('6. Permisos (§9)');

        $m    = $this->manifest();
        $decl = array_keys($m['permissions'] ?? []);

        $malos = array_values(array_filter($decl, static fn ($p) => !preg_match('/^map\.[a-z]+$/', $p)));
        $this->check('todos con la convención <code>.<accion>', $malos === [], implode(', ', $malos));

        $verbos = array_map(static fn ($p) => substr($p, 4), $decl);
        $this->check('usa los verbos del núcleo (view/create/edit/delete)',
            array_diff(['view', 'create', 'edit', 'delete'], $verbos) === []);

        preg_match_all('/permission:([a-z.]+)/', $this->limpio($this->leer('Config/Routes.php')), $mm);
        $usados = array_values(array_unique($mm[1]));

        $this->check('todo permiso usado está declarado', array_diff($usados, $decl) === [],
            implode(', ', array_diff($usados, $decl)));
        $this->check('todo permiso declarado se usa', array_diff($decl, $usados) === [],
            implode(', ', array_diff($decl, $usados)));
    }

    private function s7Menus(): void
    {
        $this->titulo('7. Menús e iconos (§10)');

        $m     = $this->manifest();
        $menus = $m['menus'] ?? [];
        $this->check('declara menús', $menus !== []);

        $css = @file_get_contents(FCPATH . 'css' . DIRECTORY_SEPARATOR . 'icons.css') ?: '';

        $sinIcono = [];
        $sinPerm  = [];
        $malaRuta = [];

        foreach ($menus as $it) {
            if ($css !== '' && !str_contains($css, '.' . ($it['icon'] ?? '') . '{')) {
                $sinIcono[] = $it['icon'] ?? '(vacío)';
            }
            if (empty($it['permission'])) {
                $sinPerm[] = $it['label'] ?? '?';
            }
            if (!str_starts_with($it['route'] ?? '', 'mapas')) {
                $malaRuta[] = $it['route'] ?? '?';
            }
        }

        $this->check('los iconos del menú existen en el núcleo', $sinIcono === [], implode(', ', $sinIcono));
        $this->check('todos los menús llevan permiso', $sinPerm === [], implode(', ', $sinPerm));
        $this->check('todos apuntan a rutas del componente', $malaRuta === [], implode(', ', $malaRuta));

        /*
         * Y los iconos de las VISTAS internas, que es donde más se escapan.
         * Un `icon-` inexistente **no da error**: simplemente no pinta nada, así
         * que el hueco no se relaciona nunca con el nombre del icono.
         */
        if ($css !== '') {
            $usados = [];

            foreach ($this->archivos('php', 'Views') as $f) {
                // Las vistas públicas usan SVG en línea, no el juego del núcleo.
                if (str_contains($f, DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                preg_match_all('/icon-([a-z0-9-]+)/', (string) file_get_contents($f), $mm);
                $usados = array_merge($usados, $mm[1]);
            }
            $usados = array_values(array_unique($usados));
            $faltan = array_values(array_filter($usados, static fn ($i) => !str_contains($css, '.icon-' . $i . '{')));

            $this->check(count($usados) . ' iconos usados en las vistas, todos existen', $faltan === [],
                implode(', ', array_map(static fn ($i) => 'icon-' . $i, $faltan)));
        } else {
            $this->manual[] = 'No se pudo leer public/css/icons.css: comprueba a mano que los iconos existan.';
        }
    }

    private function s8Idiomas(): void
    {
        $this->titulo('8. Idiomas (§7.2)');

        $es = require $this->raiz . '/Language/es/Map.php';
        $en = require $this->raiz . '/Language/en/Map.php';

        $soloEs = array_diff(array_keys($es), array_keys($en));
        $soloEn = array_diff(array_keys($en), array_keys($es));

        $this->check(count($es) . ' claves en es', $es !== []);
        $this->check('en tiene las mismas claves', $soloEs === [] && $soloEn === [],
            'solo es: ' . implode(', ', $soloEs) . ' | solo en: ' . implode(', ', $soloEn));

        $usadas = [];

        foreach ($this->archivos('php') as $f) {
            preg_match_all("/lang\(\s*'Map\.([a-zA-Z0-9_]+)'/", $this->limpio((string) file_get_contents($f)), $mm);
            $usadas = array_merge($usadas, $mm[1]);
        }
        // Las que la vista arma en bucle (`lang('Map.' . $k)`) van por su lista.
        foreach ($this->clavesDelEditor() as $k) {
            $usadas[] = $k;
        }
        $usadas = array_values(array_unique($usadas));
        $faltan = array_diff($usadas, array_keys($es));

        $this->check(count($usadas) . ' claves usadas, todas definidas', $faltan === [], implode(', ', $faltan));

        $this->manual[] = 'Calidad de las traducciones al inglés (el auditor solo comprueba que existan).';
    }

    /**
     * Las claves que el servidor le pasa al editor en `#mapI18n`.
     *
     * Se leen de la lista literal de la vista: es la única fuente, y lo que no
     * esté ahí llega al guion como `undefined`.
     *
     * @return string[]
     */
    private function clavesDelEditor(): array
    {
        $src = $this->leer('Views/maps/editor.php');
        if (!preg_match('/foreach\s*\(\s*\[(.*?)\]\s*as\s*\$k\)/s', $src, $m)) {
            return [];
        }
        preg_match_all("/'([a-zA-Z0-9_]+)'/", $m[1], $mm);

        return $mm[1];
    }

    private function s9Publicas(): void
    {
        $this->titulo('9. Vistas públicas autosuficientes (§7.3)');

        $publicas = $this->archivos('php', 'Views' . DIRECTORY_SEPARATOR . 'public');
        $this->check('hay vista pública', $publicas !== []);

        $delNucleo = [];
        $cdn       = [];
        $desbalance = [];

        foreach ($publicas as $f) {
            $bruto = (string) file_get_contents($f);
            $base  = basename($f);
            /*
             * SIN los comentarios: la cabecera de esta vista dice literalmente
             * «no carga tailwind.css, icons.css ni fonts.css», y buscarlo en
             * crudo daba tres incumplimientos por explicar lo que NO hace. Es
             * exactamente el falso positivo que el auditor existe para evitar.
             */
            $src = $this->limpio($bruto);

            foreach (['tailwind.css', 'icons.css', 'fonts.css', 'theme.css', "extend('layouts"] as $malo) {
                if (str_contains($src, $malo)) {
                    $delNucleo[] = $base . ' → ' . $malo;
                }
            }
            if (preg_match('#(https?:)?//(cdn|unpkg|jsdelivr|fonts\.googleapis|ajax\.googleapis)#i', $src)) {
                $cdn[] = $base;
            }

            /*
             * Etiquetas equilibradas. El contenido de un `style` es CDATA: sin su
             * cierre el navegador se traga como CSS todo lo que viene después y
             * **el body llega vacío al DOM** — una pantalla en gris que ninguna
             * comprobación con curl detecta, porque el marcado sí está en los bytes.
             */
            foreach (['style', 'script'] as $tag) {
                // Aquí sí el HTML crudo: se cuentan etiquetas, no código.
                $abre   = preg_match_all('/<' . $tag . '(\s|>)/i', $bruto);
                $cierra = preg_match_all('#</' . $tag . '>#i', $bruto);
                if ($abre !== $cierra) {
                    $desbalance[] = $base . ' → ' . $tag . ' ' . $abre . '/' . $cierra;
                }
            }
        }

        $this->check('no cargan hojas ni layouts del núcleo', $delNucleo === [], implode(', ', $delNucleo));
        $this->check('no llaman a ningún CDN', $cdn === [], implode(', ', $cdn));
        $this->check('las etiquetas <style>/<script> están equilibradas', $desbalance === [], implode(', ', $desbalance));

        // El único guion que pueden cargar es el del propio componente.
        $externos = [];

        foreach ($publicas as $f) {
            /*
             * La etiqueta ENTERA y no solo el principio del `src`: el atributo
             * lleva dentro una etiqueta PHP (`base_url(...)`), así que capturar
             * hasta la comilla devolvía `<?= base_url(` y todo parecía externo.
             */
            preg_match_all('/<script[^>]*\ssrc=[^>]*>/i', (string) file_get_contents($f), $mm);

            foreach ($mm[0] as $tag) {
                if (!str_contains($tag, 'mapa/recurso/')) {
                    $externos[] = basename($f) . ' → ' . trim(substr($tag, 0, 60));
                }
            }
        }
        $this->check('el único guion externo es el del componente', $externos === [], implode(', ', $externos));

        // Un 404 público no puede distinguir «no existe» de «sin publicar» de
        // «el plan no lo incluye»: sería una sonda de lo que tiene contratado
        // cada empresa.
        $pub = $this->limpio($this->leer('Controllers/Publico.php'));
        $this->check('el 404 público no distingue el motivo',
            substr_count($pub, 'PageNotFoundException') >= 1 && !preg_match('/(plan|publicad)[^\n]{0,40}404/i', $pub));
    }

    private function s10Plan(): void
    {
        $this->titulo('10. Integración con planes (§11)');

        $m = $this->manifest();

        $this->check('1. la migración de plans está en el componente',
            array_filter($this->archivos('php', 'Database' . DIRECTORY_SEPARATOR . 'Migrations'),
                static fn ($f) => str_contains($f, 'LimitsToPlans')) !== []);
        $this->check('2. declara plan_flag en el Manifest', !empty($m['plan_flag']));
        $this->check('   y plan_limits para el Panel Empresa', !empty($m['plan_limits']));

        $guard = $this->raiz . '/Libraries/PlanGuard.php';
        $this->check('3. existe el PlanGuard', is_file($guard));

        if (is_file($guard)) {
            $src = $this->limpio((string) file_get_contents($guard));
            $this->check('   expone serviceEnabled()', str_contains($src, 'function serviceEnabled'));
            $this->check('   expone el cupo restante', str_contains($src, 'function mapsLeft'));
            // §15.2: no puede exigir un método que un núcleo viejo no tiene.
            $this->check('   resuelve effectivePlan() con method_exists (§15.2)',
                str_contains($src, 'method_exists') && str_contains($src, 'effectivePlan'));
            /*
             * Y es *fail-open*: una columna que falta significa «esta instalación
             * no gradúa este servicio», no «está prohibido». Es la lección del
             * Mailer, que se quedó apagado para toda empresa con plan.
             */
            $this->check('   una columna ausente NO bloquea el servicio',
                str_contains($src, 'array_key_exists') || str_contains($src, '!$plan'));
        }

        // El cupo se aplica en el SERVIDOR, no ocultando el botón.
        $maps = $this->limpio($this->leer('Controllers/Maps.php'));
        $this->check('   el cupo se comprueba en el controlador', str_contains($maps, 'canCreateMap'));

        $this->check('4. el formulario de planes del núcleo NO se toca (§15.4)',
            !str_contains(implode(' ', array_map('basename', $this->archivos('php'))), 'plans'));
    }

    private function s11Seguridad(): void
    {
        $this->titulo('11. Seguridad (§12)');

        $sinCsrf = [];
        $forms   = 0;

        foreach ($this->archivos('php', 'Views') as $f) {
            $src = (string) file_get_contents($f);
            /*
             * Se cuentan los `method="post"`, no las etiquetas enteras: un
             * atributo con una etiqueta PHP dentro lleva un `>` en medio, así
             * que un patrón del tipo `<form[^>]*>` corta antes del método.
             *
             * Y el comentario va en BLOQUE: un cierre de etiqueta PHP dentro de
             * un comentario de una línea sale de modo PHP de verdad, y el resto
             * del archivo se sirve como HTML con un «Unclosed {» que apunta a la
             * función entera. Es la trampa que ya documentó Copropiedad.
             */
            $n = preg_match_all('/method=["\']post["\']/i', $src);
            $forms += $n;

            if ($n > 0 && substr_count($src, 'csrf_field()') < $n) {
                $sinCsrf[] = basename($f) . ' (' . $n . ' formularios, ' . substr_count($src, 'csrf_field()') . ' tokens)';
            }
        }
        $this->check($forms . ' formularios POST, todos con csrf_field()', $sinCsrf === [], implode(' | ', $sinCsrf));

        /*
         * Toda respuesta JSON lleva `csrf_hash()`.
         *
         * El token ROTA en cada POST. Si una respuesta no devuelve el nuevo, el
         * siguiente guardado da 403, el editor reintenta y funciona igual — pero
         * se paga un viaje de más y el fallo queda enmascarado para siempre.
         */
        $sinHash = [];

        foreach ($this->archivos('php', 'Controllers') as $f) {
            $src = $this->limpio((string) file_get_contents($f));
            // El controlador público no tiene sesión: ahí no hay token que dar.
            if (str_contains($f, 'Publico.php') || str_contains($f, 'Asset.php')) {
                continue;
            }
            $json = preg_match_all('/setJSON\(/', $src);
            $hash = substr_count($src, 'csrf_hash()');
            if ($json > 0 && $hash < $json) {
                $sinHash[] = basename($f) . ' (' . $json . ' respuestas, ' . $hash . ' tokens)';
            }
        }
        $this->check('toda respuesta JSON devuelve el token nuevo', $sinHash === [], implode(' | ', $sinHash));

        /*
         * `getJSON()` LANZA excepción si el cuerpo no es JSON —no devuelve null—,
         * así que el patrón `getJSON() ?: getPost()` da un 500 en cuanto llega un
         * formulario normal, que es como manda las cosas el editor.
         */
        $sinGuarda = [];

        foreach ($this->archivos('php', 'Controllers') as $f) {
            $src = $this->limpio((string) file_get_contents($f));
            if (str_contains($src, 'getJSON(') && !str_contains($src, 'Content-Type')) {
                $sinGuarda[] = basename($f);
            }
        }
        $this->check('ningún getJSON() sin mirar antes el Content-Type', $sinGuarda === [], implode(', ', $sinGuarda));

        /*
         * El aislamiento no lo da el permiso: cada ACCIÓN resuelve la empresa.
         *
         * Antes bastaba con que el archivo nombrara `active_company_id` en algún
         * sitio, y eso daba por bueno un controlador con una acción que se
         * olvidara de filtrar. Se mira acción por acción: o lee la sesión, o
         * llama a `companyId()` —que es lo que hace `BaseMap` por todos—.
         */
        $sinEmpresa = [];

        foreach ($this->archivos('php', 'Controllers') as $f) {
            if (str_contains($f, 'Publico.php') || str_contains($f, 'Asset.php') || str_contains($f, 'BaseMap.php')) {
                continue;
            }
            $src = $this->limpio((string) file_get_contents($f));

            // El cuerpo de cada método público, hasta el siguiente `function`.
            preg_match_all(
                '/public function (\w+)\s*\([^)]*\)[^{]*\{(.*?)(?=\n    (?:public|private|protected) function |\n\})/s',
                $src,
                $m,
                PREG_SET_ORDER
            );
            foreach ($m as $accion) {
                if ($accion[1] === '__construct') {
                    continue;
                }
                if (!str_contains($accion[2], 'companyId()') && !str_contains($accion[2], 'active_company_id')) {
                    $sinEmpresa[] = basename($f) . '::' . $accion[1] . '()';
                }
            }
        }
        $this->check('toda acción del panel resuelve la empresa activa', $sinEmpresa === [],
            implode(', ', $sinEmpresa));

        // Salida escapada en las vistas.
        $crudos = [];
        /*
         * `$svg` queda fuera: es marcado SVG compuesto en la propia vista, y
         * escaparlo enseñaría las etiquetas en pantalla. Todo lo demás pasa por
         * `esc()`, incluidos los literales del propio archivo — una excepción
         * sin marcar es la que mañana se copia a otro sitio.
         */
        $patron = '/<\?=\s*\$(?!this|svg)([a-zA-Z_]+)((?:\[[^\]]+\])*)\s*\?>/';

        foreach ($this->archivos('php', 'Views') as $f) {
            preg_match_all($patron, (string) file_get_contents($f), $mm, PREG_SET_ORDER);

            if ($mm) {
                $crudos[] = basename($f) . ': ' . implode(', ', array_unique(array_map(
                    static fn ($x) => '$' . $x[1] . $x[2],
                    $mm
                )));
            }
        }
        $this->check('ninguna vista imprime una variable sin esc()', $crudos === [], implode(' | ', $crudos));

        $this->manual[] = 'Prueba de POST con id de otra empresa en cada acción (el autotest cubre el servicio).';
        $this->manual[] = 'Comportamiento real en PostgreSQL y SQL Server (aquí solo hay MySQL).';
    }

    private function s12Assets(): void
    {
        $this->titulo('12. Assets servidos por el componente (§2)');

        /*
         * El núcleo nunca implementó la publicación a `public/components/<code>/`
         * que menciona el contrato, así que los sirve un controlador con lista
         * blanca fija. Lo que hay que vigilar es que la lista y lo que piden las
         * vistas no se separen: un archivo fuera de la lista es un 404 mudo.
         */
        $asset = $this->limpio($this->leer('Controllers/Asset.php'));
        preg_match_all("/'([a-z0-9_.-]+\.(?:js|css))'\s*=>/i", $asset, $mm);
        $lista = $mm[1];

        /*
         * Lo peligroso no es buscar POR la URL —eso es justo cómo funciona una
         * lista blanca— sino construir la RUTA con ella. Se comprueba que el
         * archivo servido salga de la entrada encontrada y que el nombre de la
         * URL no aparezca nunca dentro de la ruta.
         */
        $this->check('la lista blanca es fija y la ruta sale de ella, no de la URL',
            str_contains($asset, 'const FILES')
            && preg_match('/[$]entry\[.file.\]/', $asset)
            && !preg_match('/assets[^;]*[$]name/', $asset));
        $this->check(count($lista) . ' archivos en la lista blanca', $lista !== []);

        $pedidos = [];

        foreach ($this->archivos('php', 'Views') as $f) {
            preg_match_all("#mapa/recurso/([a-z0-9_.-]+)#i", (string) file_get_contents($f), $m2);
            $pedidos = array_merge($pedidos, $m2[1]);
        }
        $pedidos = array_values(array_unique($pedidos));
        $huerfanos = array_diff($pedidos, $lista);

        $this->check(count($pedidos) . ' archivos pedidos por las vistas, todos en la lista', $huerfanos === [],
            implode(', ', $huerfanos));

        // Y que los archivos existan de verdad en la carpeta.
        $noExisten = [];

        foreach ($lista as $nombre) {
            $sub = str_ends_with($nombre, '.css') ? 'css' : 'js';
            if (!is_file($this->raiz . '/assets/' . $sub . '/' . $nombre)) {
                $noExisten[] = $nombre;
            }
        }
        $this->check('todos los archivos de la lista existen', $noExisten === [], implode(', ', $noExisten));
    }

    /**
     * Lo propio de este componente: lo que le ha mordido de verdad.
     *
     * Un auditor que solo repite el contrato acaba siendo una lista que nadie
     * lee. Estas cinco son las que han costado una tarde cada una.
     */
    private function s13Propio(): void
    {
        $this->titulo('13. Las trampas propias de este componente');

        /*
         * 1. Toda clave que el guion pide tiene que estar en la lista que el
         *    servidor le manda. `T()` devuelve la CLAVE cuando falta, así que el
         *    síntoma es un botón que pone «save» en minúscula, no un error.
         */
        $declaradas = $this->clavesDelEditor();
        $js         = $this->limpioJs($this->leer('assets/js/editor.js'));
        preg_match_all("/\bT\(\s*'([a-zA-Z0-9_]+)'/", $js, $mm);
        // Las que se arman concatenando (`T('tool' + kind)`) no se pueden leer.
        preg_match_all("/\bT\(\s*'([a-zA-Z0-9_]+)'\s*\+/", $js, $mc);
        $pedidas = array_values(array_diff(array_unique($mm[1]), $mc[1]));
        $faltan  = array_values(array_diff($pedidas, $declaradas));

        $this->check(count($pedidas) . ' claves que pide el editor, todas se le mandan', $faltan === [],
            implode(', ', $faltan));

        /*
         * 2. Lo mismo en la página pública, que usa un objeto plano (`T.more`).
         */
        $mapPhp = $this->leer('Views/public/map.php');
        $ini    = strpos($mapPhp, 'id="mapI18n"');
        $bloque = $ini !== false ? substr($mapPhp, $ini, 900) : '';
        preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>/", $bloque, $md);
        $publicas = $md[1];

        preg_match_all('/\bT\.([a-zA-Z0-9_]+)/', $this->limpioJs($mapPhp), $mu);
        $usadas = array_values(array_unique($mu[1]));
        $faltan = array_values(array_diff($usadas, $publicas));

        $this->check(count($usadas) . ' claves que usa la página pública, todas se le mandan', $faltan === [],
            implode(', ', $faltan));

        /*
         * 3. Toda propiedad de presentación que el editor escribe tiene que estar
         *    en la lista blanca del modelo: si no, el servidor la descarta EN
         *    SILENCIO y el usuario ve que su ajuste «no se guarda».
         */
        preg_match_all("/'data-prop'\s*:\s*(?:clave|'([a-zA-Z0-9_]+)')/", $js, $mp);
        preg_match_all("/data-prop=\"([a-zA-Z0-9_]+)\"/", $js, $mp2);
        $escritas = array_values(array_filter(array_unique(array_merge($mp[1], $mp2[1]))));

        // Las que el editor construye con una variable (`color('fill', …)`) se
        // leen de las llamadas a los constructores de control.
        preg_match_all("/(?:color|rango)\(\s*'([a-zA-Z0-9_]+)'/", $js, $mv);
        $escritas = array_values(array_unique(array_merge($escritas, $mv[1])));

        $modelo = $this->limpio($this->leer('Models/PointModel.php'));
        preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*(?:'color'|'border'|'bool'|\[)/", $modelo, $ms);
        $admitidas = $ms[1];

        $sueltas = array_values(array_diff($escritas, $admitidas));
        $this->check(count($escritas) . ' propiedades que escribe el editor, todas admitidas por el modelo',
            $sueltas === [], implode(', ', $sueltas));

        /*
         * 4. Ninguna animación sin salida para quien pidió menos movimiento. Y la
         *    salida tiene que ser TAN específica como la regla que anula: una
         *    media query no sube la especificidad.
         */
        $css  = $this->leer('Views/_map_styles.php');
        $anim = preg_match_all('/animation\s*:\s*(?!none)/', $css);
        $this->check('la hoja del visor declara animaciones', $anim > 0, (string) $anim);
        $this->check('  y respeta prefers-reduced-motion', str_contains($css, 'prefers-reduced-motion'));

        if (preg_match('/@media\s*\(prefers-reduced-motion:reduce\)\s*\{(.*?)\n\}/s', $css, $mr)) {
            // Cada selector que enciende una animación debe aparecer también, con
            // sus mismas clases, dentro del bloque que la apaga.
            preg_match_all('/^\s*([^{@\n][^{\n]*)\{[^}]*animation:(?!none)/m', $css, $enciende);
            $apagados = $mr[1];
            $sinApagar = [];

            foreach (array_map('trim', $enciende[1]) as $sel) {
                foreach (explode(',', $sel) as $uno) {
                    $uno = trim($uno);
                    if ($uno !== '' && !str_contains($apagados, $uno)) {
                        $sinApagar[] = $uno;
                    }
                }
            }
            // El destello de llegada no se apaga por CSS: lo decide el guion, que
            // ni siquiera pone la clase con «menos movimiento».
            $sinApagar = array_values(array_filter($sinApagar, static fn ($s) => !str_contains($s, 'is-arrived')));

            $this->check('  cada animación tiene su apagado con la misma especificidad',
                $sinApagar === [], implode(' | ', $sinApagar));
        }

        /*
         * 5. El visor y la página pública comparten hoja y payload: si cada uno
         *    armara el suyo, el que se equivocaría sería el público.
         */
        $editor = $this->leer('Views/_editor_layout.php') . $this->leer('Views/maps/editor.php');
        $this->check('editor y página pública incrustan la MISMA hoja del visor',
            str_contains($editor, '_map_styles') && str_contains($mapPhp, '_map_styles'));
        $this->check('y consumen el mismo payload',
            str_contains($this->limpio($this->leer('Controllers/Editor.php')), 'payload(')
            && str_contains($this->limpio($this->leer('Controllers/Publico.php')), 'payload('));

        // El vocabulario de tipos vive en un solo sitio.
        $this->check('los tipos de elemento son una lista blanca del modelo',
            count(PointModel::KINDS) >= 5 && in_array('line', PointModel::KINDS, true));
    }

    /**
     * Lo que este componente necesita del núcleo.
     *
     * Es la lista que hay que contrastar con la instalación de destino **antes**
     * de copiar la carpeta: si algo de aquí no está allí, no arranca.
     */
    private function superficie(): void
    {
        $this->titulo('Superficie del núcleo que necesita (§15.5)');

        $clases  = [];
        $metodos = [];
        $vistas  = [];
        $helpers = [];

        foreach ($this->archivos('php') as $f) {
            $src = $this->limpio((string) file_get_contents($f));

            preg_match_all('/(?:^use\s+|new\s+\\\\?)(App\\\\[A-Za-z\\\\]+)/m', $src, $mm);

            foreach ($mm[1] as $fqcn) {
                $clases[rtrim($fqcn, '\\')] = true;
            }

            preg_match_all('/\(new\s+\\\\?([A-Za-z\\\\]+)\(\)\)->([a-zA-Z_]+)\(/', $src, $mm2, PREG_SET_ORDER);

            foreach ($mm2 as $x) {
                $clase = ltrim($x[1], '\\');
                if (!str_contains($clase, 'App\\') && !in_array($clase, ['CompanyModel', 'UploadService', 'DataTable'], true)) {
                    continue;
                }
                $corto = (str_contains($clase, '\\') ? substr($clase, strrpos($clase, '\\') + 1) : $clase) . '::' . $x[2] . '()';

                $metodos[$corto] = true;
            }

            preg_match_all("/view\(\s*'((?:layouts|partials)\/[a-z_]+)'/", $src, $mm3);

            foreach ($mm3[1] as $v) {
                $vistas[$v] = true;
            }
            preg_match_all("/\\\$this->extend\(\s*'([a-zA-Z\\\\\/]+)'/", $src, $mm4);

            foreach ($mm4[1] as $v) {
                if (!str_contains($v, 'Components')) {
                    $vistas[$v] = true;
                }
            }

            foreach (['media_url(', 'base_url(', 'csrf_field(', 'csrf_hash(', 'lang(', 'esc(', 'session('] as $h) {
                if (str_contains($src, $h)) {
                    $helpers[rtrim($h, '(')] = true;
                }
            }
        }

        ksort($clases);
        ksort($metodos);
        ksort($vistas);
        ksort($helpers);

        CLI::write('  Clases:   ' . (implode(', ', array_keys($clases)) ?: '—'));
        CLI::write('  Métodos:  ' . (implode(', ', array_keys($metodos)) ?: '—'));
        CLI::write('            (con respaldo si el núcleo no los tiene: effectivePlan())');
        CLI::write('  Vistas:   ' . (implode(', ', array_keys($vistas)) ?: '—'));
        CLI::write('  Helpers:  ' . implode(', ', array_keys($helpers)));
        CLI::write('  Filtros:  auth, permission:<perm>');
        CLI::write('  Tablas:   companies(id), plans(+2 columnas propias)');
        CLI::write('  Sesión:   active_company_id, language');
        CLI::write('  Assets:   tailwind.css, icons.css, fonts.css, theme.css  (SOLO el editor; la pública no)');
        CLI::write('  PHP:      ext-gd para reducir los planos (sin ella se guardan tal cual)');
    }
}
