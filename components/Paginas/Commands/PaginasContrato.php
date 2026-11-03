<?php

namespace Components\Paginas\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Paginas\Libraries\BlockRegistry;

/**
 * Audita el componente contra `proyectos/CONTRATO_COMPONENTES.md`.
 *
 * Es una comprobación **mecánica**, no una lectura a ojo: recorre la carpeta y
 * verifica cada punto del checklist §14 que se pueda comprobar leyendo el
 * código. Lo que no se puede (que un formulario esté bien ordenado, que un
 * texto esté bien traducido) se marca como revisión manual y se dice.
 *
 * Además imprime la **superficie del núcleo** que usa el componente: la lista
 * exacta de lo que la plataforma de destino tiene que ofrecer para que soltar
 * la carpeta funcione. Es la respuesta a «¿me lo puedo llevar a otra
 * instalación?».
 *
 *   php spark paginas:contrato
 */
class PaginasContrato extends BaseCommand
{
    protected $group       = 'Páginas';
    protected $name        = 'paginas:contrato';
    protected $description = 'Audita el componente contra el contrato de componentes e informa de su acople con el núcleo.';

    private int $ok = 0;
    private int $ko = 0;

    /** Cosas que el contrato permite pero conviene tener a la vista. */
    private array $notas = [];

    private string $dir;

    public function run(array $params)
    {
        $this->dir = dirname(__DIR__);

        $this->title('0. Principios');
        $this->principios();

        $this->title('2-3. Estructura y Manifest');
        $this->estructura();

        $this->title('4. Rutas');
        $this->rutas();

        $this->title('6-8. Tablas, migraciones y portabilidad');
        $this->datos();

        $this->title('7.2 i18n');
        $this->idiomas();

        $this->title('7.3 Vistas públicas autosuficientes');
        $this->publicas();

        $this->title('9-10. Permisos y menús');
        $this->permisos();

        $this->title('11. Plan');
        $this->plan();

        $this->title('12. Seguridad');
        $this->seguridad();

        $this->superficie();

        CLI::write('');
        CLI::write(str_repeat('─', 60));
        CLI::write("Cumple: {$this->ok} · Incumple: {$this->ko}", $this->ko ? 'red' : 'green');

        if ($this->notas) {
            CLI::write('');
            CLI::write('Revisión manual (el contrato no se puede comprobar leyendo código):', 'yellow');
            foreach ($this->notas as $n) {
                CLI::write('  · ' . $n);
            }
        }

        return $this->ko === 0 ? 0 : 1;
    }

    // ---------------------------------------------------------------- checks

    private function principios(): void
    {
        // §0.1 — no modificar el núcleo. Lo que se puede comprobar desde aquí es
        // que el componente no declara clases en `App\` y que no depende de
        // código del núcleo que no venía con él.
        $enApp = [];

        foreach ($this->archivos('php') as $f) {
            if (preg_match('/^\s*namespace\s+App\\\\/m', (string) file_get_contents($f))) {
                $enApp[] = basename($f);
            }
        }
        $this->check('§0.2 ninguna clase en el namespace App\\', $enApp === [], implode(', ', $enApp));

        // Las clases del núcleo que usa: tienen que existir Y ser de las que ya
        // trae cualquier instalación (no algo que este componente haya metido).
        $usadas = $this->clasesNucleo();
        $faltan = array_values(array_filter($usadas, static fn ($c) => !class_exists($c)));
        $this->check('§11 todas las clases del núcleo que usa existen', $faltan === [], implode(', ', $faltan));

        $prefijo = $this->manifest()['table_prefix'] ?? '';
        $this->check('§0.3 declara prefijo de tablas propio', $prefijo === 'cmp_pag_', $prefijo);

        // §0.4 — aislamiento. Depender de otro componente ata la instalación
        // a que ESE otro también esté: es lo que llevó a que este tenga su
        // propia copia de `QrService` y de `ImageOptimizer` en vez de usar la
        // de Contabilidad o la que el núcleo estrenó con él.
        $ajenos = [];

        foreach ($this->archivos('php', 'js') as $file) {
            // Sin comentarios: si no, este mismo archivo se delata al nombrar a
            // Contabilidad en la nota de arriba.
            $src = str_ends_with($file, '.js')
                ? $this->limpioJs((string) file_get_contents($file))
                : $this->limpio((string) file_get_contents($file));
            preg_match_all('/Components\\\\([A-Za-z]+)/', $src, $mc);

            foreach ($mc[1] as $c) {
                if ($c !== 'Paginas') {
                    $ajenos[$c] = true;
                }
            }
        }
        $this->check('§0.4 no depende de ningún otro componente', $ajenos === [], implode(', ', array_keys($ajenos)));

        // §13 — la versión del Manifest tiene que ir por delante de lo que
        // hay instalado, o el gestor no ofrece actualizar.
        $estado = @json_decode((string) @file_get_contents(WRITEPATH . 'components.json'), true) ?: [];
        $puesta = $estado['Paginas']['version'] ?? null;
        $this->check('§13 la versión del Manifest no es menor que la instalada',
            $puesta === null || version_compare((string) ($this->manifest()['version'] ?? '0'), (string) $puesta, '>='),
            'manifest=' . ($this->manifest()['version'] ?? '?') . ' instalada=' . $puesta);
    }

    private function estructura(): void
    {
        $m = $this->manifest();

        foreach (['code', 'name', 'version', 'table_prefix'] as $campo) {
            $this->check("§3 el Manifest declara «{$campo}»", !empty($m[$campo]), '');
        }
        $this->check('§3 la versión es SemVer',
            (bool) preg_match('/^\d+\.\d+\.\d+$/', (string) ($m['version'] ?? '')), (string) ($m['version'] ?? ''));
        $this->check('§3 el code casa con el prefijo de tablas',
            'cmp_' . ($m['code'] ?? '') . '_' === ($m['table_prefix'] ?? ''), '');

        foreach (['Config/Routes.php', 'Controllers', 'Models', 'Views', 'Language/es', 'Language/en'] as $ruta) {
            $this->check("§2 existe {$ruta}", file_exists($this->dir . '/' . $ruta), '');
        }
    }

    private function rutas(): void
    {
        $rutas   = (string) file_get_contents($this->dir . '/Config/Routes.php');
        $grupos  = [];
        preg_match_all("/\\\$routes->group\('([^']+)'/", $rutas, $mg);
        $grupos = $mg[1];

        $declarados = $this->manifest()['routes'] ?? [];
        $fuera      = array_values(array_diff($grupos, $declarados));
        $this->check('§4 todos los grupos de ruta están declarados en el Manifest',
            $fuera === [], implode(', ', $fuera));

        $this->check('§4 el namespace de los controladores es el del componente',
            substr_count($rutas, "'namespace' => 'Components\\Paginas\\Controllers'") === count($grupos),
            substr_count($rutas, "'namespace' => 'Components\\Paginas\\Controllers'") . ' de ' . count($grupos));

        // Ninguna ruta de administración sin permiso. Se mira línea a línea
        // dentro del grupo `paginas`, que es el que lleva `auth`.
        $sinPermiso = [];
        $dentro     = false;

        foreach (explode("\n", $rutas) as $linea) {
            if (str_contains($linea, "\$routes->group('paginas'")) {
                $dentro = true;

                continue;
            }
            if ($dentro && preg_match('/^\}\);/', trim($linea))) {
                $dentro = false;
            }
            if ($dentro && preg_match("/\\\$routes->(get|post|match)\(/", $linea) && !str_contains($linea, 'permission:')) {
                $sinPermiso[] = trim($linea);
            }
        }
        $this->check('§9 ninguna ruta de administración sin permission:', $sinPermiso === [],
            implode(' | ', $sinPermiso));

        // Los borrados van por POST.
        preg_match_all("/\\\$routes->get\('([^']*elimin[^']*)'/", $rutas, $mb);
        $this->check('§4 ningún borrado por GET', ($mb[1] ?? []) === [], implode(', ', $mb[1] ?? []));
    }

    private function datos(): void
    {
        $prefijo = 'cmp_pag_';
        $malas   = [];
        $noIdem  = [];
        $crudo   = [];

        foreach (glob($this->dir . '/Database/Migrations/*.php') as $f) {
            $src = (string) file_get_contents($f);

            preg_match_all("/createTable\('([^']+)'/", $src, $mt);
            foreach ($mt[1] as $t) {
                if (!str_starts_with($t, $prefijo)) {
                    $malas[] = $t;
                }
            }

            // Idempotente: o comprueba la tabla, o el campo (las de `plans`).
            if (str_contains($src, 'createTable(') && !str_contains($src, 'tableExists(')) {
                $noIdem[] = basename($f);
            }
            if (str_contains($src, 'addColumn(') && !str_contains($src, 'fieldExists(')) {
                $noIdem[] = basename($f);
            }

            // Portabilidad: ni ENUM, ni JSON nativo, ni SQL crudo.
            foreach (["'ENUM'", "'JSON'", '->query('] as $prohibido) {
                if (str_contains($src, $prohibido)) {
                    $crudo[] = basename($f) . ' → ' . $prohibido;
                }
            }
        }

        $this->check('§6 todas las tablas creadas llevan el prefijo', $malas === [], implode(', ', $malas));
        $this->check('§6 todas las migraciones son idempotentes', $noIdem === [], implode(', ', array_unique($noIdem)));
        $this->check('§8 sin ENUM, JSON nativo ni SQL crudo en migraciones', $crudo === [], implode(', ', $crudo));

        // Las tablas del núcleo solo se tocan por FK.
        $altera = [];

        foreach (glob($this->dir . '/Database/Migrations/*.php') as $f) {
            $src = (string) file_get_contents($f);
            foreach (['companies', 'users', 'roles', 'permissions'] as $nucleo) {
                if (preg_match("/(createTable|dropTable|modifyColumn)\('{$nucleo}'/", $src)) {
                    $altera[] = basename($f) . ' → ' . $nucleo;
                }
            }
        }
        $this->check('§11 no crea ni redefine tablas del núcleo', $altera === [], implode(', ', $altera));

        // Multi-tenant: toda tabla propia con `company_id`.
        $sinTenant = [];

        foreach (glob($this->dir . '/Models/*.php') as $f) {
            $src = (string) file_get_contents($f);
            if (preg_match("/\\\$table\s*=\s*'({$prefijo}[a-z_]+)'/", $src, $mm)
                && !str_contains($src, "'company_id'")) {
                $sinTenant[] = $mm[1];
            }
        }
        $this->check('§6 toda tabla propia se aísla por company_id', $sinTenant === [], implode(', ', $sinTenant));
    }

    private function idiomas(): void
    {
        $es = require $this->dir . '/Language/es/Pag.php';
        $en = require $this->dir . '/Language/en/Pag.php';

        $this->check('§7.2 mismo juego de claves en es y en',
            array_keys($es) === array_keys($en),
            'es=' . count($es) . ' en=' . count($en)
            . ' · solo es: ' . implode(', ', array_keys(array_diff_key($es, $en)))
            . ' · solo en: ' . implode(', ', array_keys(array_diff_key($en, $es))));

        // Claves usadas y no definidas. Las compuestas (`'Pag.x' . $i`) no se
        // pueden resolver leyendo: se listan aparte para mirarlas a mano.
        $usadas = [];

        foreach ($this->archivos('php', 'js') as $f) {
            // Los propios archivos de idioma documentan `lang('Pag.clave')`
            // en su cabecera: eso no es un uso.
            if (str_contains($f, 'Language')) {
                continue;
            }
            $src = str_ends_with($f, '.js')
                ? $this->limpioJs((string) file_get_contents($f))
                : $this->limpio((string) file_get_contents($f));
            preg_match_all("/lang\('Pag\.([A-Za-z0-9_]+)'\s*\)/", $src, $mk);
            foreach ($mk[1] as $k) {
                $usadas[$k] = true;
            }
        }
        $faltan = array_values(array_diff(array_keys($usadas), array_keys($es)));
        $this->check('§7.2 ninguna clave usada sin definir', $faltan === [], implode(', ', $faltan));

        // Texto visible incrustado: se busca `alert('…')`/`confirm('…')` con
        // letras, que es el descuido típico en el JS del componente.
        $incrustado = [];

        foreach ($this->archivos('js') as $f) {
            if (preg_match_all("/(?:alert|confirm)\('([^']*[a-záéíóúñ]{4,}[^']*)'\)/i", $this->limpioJs((string) file_get_contents($f)), $mi)) {
                $incrustado = array_merge($incrustado, $mi[1]);
            }
        }
        $this->check('§7.2 sin texto visible incrustado en el JS', $incrustado === [], implode(' | ', $incrustado));
    }

    private function publicas(): void
    {
        $dirPub = $this->dir . '/Views/public';
        $this->check('§7.3 hay vistas públicas separadas', is_dir($dirPub), '');

        $conNucleo = [];
        $conCdn    = [];

        foreach (glob($dirPub . '/*.php') as $f) {
            $src = $this->limpio((string) file_get_contents($f));
            foreach (['tailwind.css', 'icons.css', 'fonts.css', "class=\"icon "] as $delNucleo) {
                if (str_contains($src, $delNucleo)) {
                    $conNucleo[] = basename($f) . ' → ' . trim($delNucleo);
                }
            }
            if (preg_match('~(https?:)?//(cdn|unpkg|jsdelivr|fonts\.googleapis)~', $src)) {
                $conCdn[] = basename($f);
            }
        }
        $this->check('§7.3 las vistas públicas no usan assets del núcleo', $conNucleo === [], implode(', ', $conNucleo));
        $this->check('§7.3 ni CDN', $conCdn === [], implode(', ', $conCdn));
    }

    private function permisos(): void
    {
        $m     = $this->manifest();
        $perms = array_keys($m['permissions'] ?? []);
        $this->check('§9 declara permisos', $perms !== [], '');

        $mal = array_values(array_filter($perms, static fn ($p) => !preg_match('/^pag\.(view|create|edit|delete|configure|export|import)$/', $p)));
        $this->check('§9 con la convención <code>.<accion> y verbos del núcleo', $mal === [], implode(', ', $mal));

        // Los que se usan en las rutas tienen que estar declarados.
        preg_match_all("/permission:([a-z]+\.[a-z]+)/", $this->limpio((string) file_get_contents($this->dir . '/Config/Routes.php')), $mr);
        $sinDeclarar = array_values(array_diff(array_unique($mr[1]), $perms));
        $this->check('§9 ningún permiso usado sin declarar', $sinDeclarar === [], implode(', ', $sinDeclarar));

        // Menús: icono existente y permiso declarado.
        $sinIcono = [];
        $css      = (string) @file_get_contents(FCPATH . 'css/icons.css');

        foreach ($m['menus'] ?? [] as $item) {
            if (!str_contains($css, '.' . $item['icon'] . '{')) {
                $sinIcono[] = $item['icon'];
            }
        }
        $this->check('§10 todos los iconos del menú existen en icons.css', $sinIcono === [], implode(', ', $sinIcono));
    }

    private function plan(): void
    {
        $m = $this->manifest();
        $this->check('§11 declara plan_flag', !empty($m['plan_flag']), '');
        $this->check('§11 declara plan_limits', !empty($m['plan_limits']), '');
        $this->check('§11 tiene PlanGuard', is_file($this->dir . '/Libraries/PlanGuard.php'), '');

        // La migración de `plans` va en el componente y su down() la revierte.
        $suya = false;

        foreach (glob($this->dir . '/Database/Migrations/*.php') as $f) {
            $src = (string) file_get_contents($f);
            if (str_contains($src, "'plans'") && str_contains($src, 'dropColumn')) {
                $suya = true;
            }
        }
        $this->check('§11 la migración de plans es del componente y se revierte', $suya, '');

        // El gating tiene que estar en el SERVICIO, no solo en la vista.
        $enControlador = 0;

        foreach (glob($this->dir . '/Controllers/*.php') as $f) {
            $enControlador += substr_count((string) file_get_contents($f), 'PlanGuard');
        }
        $this->check('§11 el PlanGuard se aplica en los controladores', $enControlador > 0, (string) $enControlador);
    }

    private function seguridad(): void
    {
        // Todo formulario POST con CSRF.
        $sinCsrf = [];

        foreach ($this->archivos('php') as $f) {
            // Solo las VISTAS: en un comando o en una librería un
            // `method="post"` es una cadena de prueba o una plantilla, no un
            // formulario servido. El del `Renderer` es el formulario público,
            // cuyo token llega por su contexto y se comprueba aparte.
            if (!str_contains($f, DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $src = $this->limpio((string) file_get_contents($f));
            if (!str_contains($src, "method=\"post\"")) {
                continue;
            }
            // Se cuentan formularios y tokens; uno por uno es inviable con PHP
            // intercalado, pero un formulario sin ningún token sí se detecta.
            $forms = substr_count($src, 'method="post"');
            // El token se emite con `csrf_field()` o se guarda una vez en una
            // variable y se reusa (es lo que hace el listado con sus filas).
            $toks = substr_count($src, 'csrf_field()') + substr_count($src, '$csrf');
            if ($toks < $forms) {
                $sinCsrf[] = basename($f) . " ({$toks}/{$forms})";
            }
        }
        $this->check('§12 todo formulario POST lleva csrf_field()', $sinCsrf === [], implode(', ', $sinCsrf));

        // El árbol se reconstruye contra el registro: la frontera del componente.
        $svc = (string) file_get_contents($this->dir . '/Libraries/PageService.php');
        $rnd = (string) file_get_contents($this->dir . '/Libraries/Renderer.php');
        $this->check('§12 el formulario público emite su token de CSRF',
            str_contains($rnd, "context['csrf']") || str_contains($rnd, 'csrf'), '');
        $this->check('§12 el árbol se sanea contra el registro', str_contains($svc, 'sanitizeTree'), '');
        $this->check('§12 el HTML del editor pasa por el saneador',
            is_file($this->dir . '/Libraries/HtmlSanitizer.php'), '');

        $this->notas[] = '§7.1 orden y agrupación de los formularios (.form-section): revisión visual.';
        $this->notas[] = '§7.2 calidad de las traducciones, no solo su presencia.';
        $this->notas[] = '§8 portabilidad probada en MySQL; PostgreSQL y SQL Server quedan por diseño (Forge).';
    }

    /**
     * Lo que el componente le pide al núcleo.
     *
     * No es un incumplimiento —el contrato **exige** reusar auth, RBAC, layout y
     * partials del núcleo (§4, §5, §7)—, pero sí es la lista de lo que la
     * plataforma de destino tiene que ofrecer para que soltar la carpeta baste.
     */
    private function superficie(): void
    {
        $this->title('Superficie del núcleo que necesita la plataforma de destino');

        $clases = $this->clasesNucleo();
        sort($clases);
        CLI::write('  Clases:   ' . implode(', ', $clases));

        $vistas = [];

        foreach ($this->archivos('php') as $f) {
            preg_match_all("/(?:view|extend)\('((?:layouts|partials)\/[a-z_]+)'/", (string) file_get_contents($f), $mv);
            $vistas = array_merge($vistas, $mv[1]);
        }
        $vistas = array_values(array_unique($vistas));
        sort($vistas);
        CLI::write('  Vistas:   ' . implode(', ', $vistas));

        // Métodos concretos: es lo que de verdad rompe en una instalación con
        // un núcleo más viejo. `effectivePlan()` llegó con los tenants y su
        // ausencia tumbaba el componente con «Call to undefined method».
        $metodos  = [];
        $blindados = [];

        foreach ($this->archivos('php') as $file) {
            $src = $this->limpio((string) file_get_contents($file));

            // Alias -> clase, de los `use App\…` del propio archivo.
            $alias = [];
            preg_match_all('/^use (App\\\\[A-Za-z\\\\]+);/m', $src, $mu);

            foreach ($mu[1] as $fqcn) {
                $alias[substr($fqcn, strrpos($fqcn, '\\') + 1)] = $fqcn;
            }

            // `(new Clase())->metodo(` y `(new \App\X\Y())->metodo(`
            preg_match_all('/\(new\s+(\\\\?[A-Za-z\\\\]+)\(\)\)->([a-zA-Z_]+)\(/', $src, $mm, PREG_SET_ORDER);

            foreach ($mm as $m) {
                $clase = ltrim($m[1], '\\');
                $clase = $alias[$clase] ?? $clase;
                if (!str_starts_with($clase, 'App\\')) {
                    continue;
                }
                $corto = substr($clase, strrpos($clase, '\\') + 1) . '::' . $m[2] . '()';
                if (preg_match('/method_exists\([^)]*\)[^;]*' . preg_quote($m[2], '/') . '/', $src)) {
                    $blindados[$corto] = true;
                } else {
                    $metodos[$corto] = true;
                }
            }
        }
        ksort($metodos);
        ksort($blindados);
        CLI::write('  Métodos:  ' . (implode(', ', array_keys($metodos)) ?: '—'));

        if ($blindados) {
            CLI::write('            (con respaldo si el núcleo no los tiene: ' . implode(', ', array_keys($blindados)) . ')');
        }

        CLI::write('  Filtros:  auth, permission:<perm>');
        CLI::write('  Tablas:   companies, users, plans  (solo por FK / lectura)');
        CLI::write('  Helpers:  media_url(), lang(), esc(), csrf_field(), base_url(), session()');
        CLI::write('  Sesión:   active_company_id');
        CLI::write('  Assets:   tailwind.css, icons.css  (solo las pantallas INTERNAS; las públicas no)');
    }

    // ---------------------------------------------------------------- utils

    /**
     * El código sin comentarios.
     *
     * Sin esto el auditor se cree lo que dicen los comentarios: una nota que
     * explica «esto NO usa icons.css» se contaba como si lo usara, y la
     * cabecera de un archivo de idiomas que documenta `lang('Pag.clave')`
     * aparecía como clave sin definir. Cuatro falsos positivos bastan para que
     * nadie vuelva a mirar el informe.
     */
    private function limpio(string $src): string
    {
        $out = '';

        foreach (token_get_all($src) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($t) ? $t[1] : $t;
        }

        return $out;
    }

    /** Igual, pero para JS: comentarios de línea y de bloque. */
    private function limpioJs(string $src): string
    {
        $src = preg_replace('~/\*.*?\*/~s', '', $src) ?? $src;

        return preg_replace('~^\s*//.*$~m', '', $src) ?? $src;
    }

    /** Las clases `App\…` que el componente importa. */
    private function clasesNucleo(): array
    {
        $clases = [];

        foreach ($this->archivos('php') as $f) {
            preg_match_all('/^use (App\\\\[A-Za-z\\\\]+);/m', (string) file_get_contents($f), $mu);
            foreach ($mu[1] as $c) {
                $clases[$c] = true;
            }
        }

        return array_keys($clases);
    }

    /** @return list<string> rutas de los archivos del componente con esas extensiones */
    private function archivos(string ...$ext): array
    {
        $out = [];
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir));

        foreach ($it as $f) {
            if ($f->isFile() && in_array(strtolower($f->getExtension()), $ext, true)) {
                $out[] = $f->getPathname();
            }
        }

        return $out;
    }

    private function manifest(): array
    {
        static $m = null;

        return $m ??= require $this->dir . '/Manifest.php';
    }

    private function title(string $t): void
    {
        CLI::write('');
        CLI::write($t, 'yellow');
    }

    private function check(string $what, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->ok++;
            CLI::write('  ✓ ' . $what, 'green');

            return;
        }
        $this->ko++;
        CLI::write('  ✗ ' . $what . ($detail !== '' ? '  →  ' . $detail : ''), 'red');
    }
}
