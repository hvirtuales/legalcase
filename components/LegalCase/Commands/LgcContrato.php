<?php

namespace Components\LegalCase\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `php spark lgc:contrato`
 *
 * Audita mecánicamente el componente contra proyectos/CONTRATO_COMPONENTES.md y al final
 * imprime la SUPERFICIE DEL NÚCLEO que necesita: lo que la instalación de destino tiene
 * que ofrecer para que la carpeta funcione al soltarla.
 *
 * Lee el código SIN comentarios (`token_get_all`): una nota que dice «no usa X» no cuenta
 * como si lo usara.
 */
class LgcContrato extends BaseCommand
{
    protected $group       = 'LegalCase';
    protected $name        = 'lgc:contrato';
    protected $description = 'Audita LegalCase contra el contrato de componentes e imprime la superficie del núcleo que usa.';

    private int $ok = 0;
    private int $ko = 0;
    private string $root = '';

    public function run(array $params)
    {
        $this->root = rtrim(dirname(__DIR__), '/\\');
        CLI::write('Auditoría del contrato — componente LegalCase', 'yellow');

        $m = require $this->root . '/Manifest.php';

        $this->manifest($m);
        $this->namespaces();
        $this->schema();
        $this->routes($m);
        $this->i18n();
        $this->publicViews();
        $this->security();
        $this->surface();

        CLI::newLine();
        CLI::write(str_repeat('─', 60), 'dark_gray');
        CLI::write($this->ko === 0 ? "CONFORME: {$this->ok} comprobaciones, 0 incumplimientos" : "INCUMPLIMIENTOS: {$this->ko} (de " . ($this->ok + $this->ko) . ')', $this->ko === 0 ? 'green' : 'red');
        CLI::newLine();
        CLI::write('Queda por revisar a mano:', 'yellow');
        foreach ([
            'Pantallas en navegador real: claro/oscuro, escritorio/390 px (CLAUDE.md, regla 2.4).',
            'Recompilar Tailwind (`composer tailwind`) si se añaden clases nuevas en vistas internas.',
            'Portabilidad en MySQL y SQL Server (el autotest se ejecutó en el motor configurado).',
            'Antivirus real (clamscan) en el servidor de producción.',
        ] as $t) {
            CLI::write('  · ' . $t, 'dark_gray');
        }

        return $this->ko === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // ------------------------------------------------------------------ utilidades

    private function check(string $what, bool $good, string $detail = ''): void
    {
        $good ? $this->ok++ : $this->ko++;
        CLI::write(($good ? '  OK    ' : '  FALLA ') . $what . ($good || $detail === '' ? '' : '  ->  ' . $detail), $good ? 'green' : 'red');
    }

    private function title(string $t): void
    {
        CLI::newLine();
        CLI::write('=== ' . $t . ' ===', 'yellow');
    }

    /** @return list<string> */
    private function files(string $sub = '', string $ext = 'php'): array
    {
        $base = $this->root . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($base)) {
            return [];
        }
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === $ext) {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    private function clean(string $path): string
    {
        $out = '';
        foreach (token_get_all((string) file_get_contents($path)) as $t) {
            if (is_array($t)) {
                $out .= in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? str_repeat("\n", substr_count($t[1], "\n")) : $t[1];
            } else {
                $out .= $t;
            }
        }

        return $out;
    }

    private function rel(string $path): string
    {
        return ltrim(str_replace($this->root, '', $path), '/\\');
    }

    // ------------------------------------------------------------------ secciones

    private function manifest(array $m): void
    {
        $this->title('1. Manifest');
        foreach (['code', 'name', 'version', 'table_prefix', 'name_key'] as $k) {
            $this->check('declara ' . $k, !empty($m[$k]));
        }
        $this->check('code de 3–8 letras minúsculas', (bool) preg_match('/^[a-z]{3,8}$/', $m['code'] ?? ''));
        $this->check('prefijo cmp_<code>_', ($m['table_prefix'] ?? '') === 'cmp_' . $m['code'] . '_');
        $this->check('versión SemVer', (bool) preg_match('/^\d+\.\d+\.\d+$/', $m['version'] ?? ''));
        $this->check('ámbito declarado', in_array($m['scope'] ?? 'company', ['company', 'tenant'], true));
        $this->check('sin dependencias de otros componentes', ($m['depends'] ?? []) === []);

        $verbs = ['view', 'create', 'edit', 'delete', 'approve', 'configure', 'export', 'import'];
        $bad   = array_filter(array_keys($m['permissions'] ?? []), fn ($p) => !preg_match('/^' . $m['code'] . '\.(' . implode('|', $verbs) . ')$/', $p));
        $this->check('permisos <code>.<verbo>', $bad === [], implode(', ', $bad));

        $css   = (string) @file_get_contents(FCPATH . 'css/icons.css');
        $icons = [$m['icon'] ?? ''];
        $noKey = [];
        foreach ($m['menus'] ?? [] as $it) {
            $icons[] = $it['icon'] ?? '';
            if (empty($it['label_key'])) {
                $noKey[] = $it['label'];
            }
            $this->check('menú «' . $it['label'] . '» con permiso declarado', isset($m['permissions'][$it['permission'] ?? '']));
        }
        foreach ($m['plan_limits'] ?? [] as $l) {
            if (empty($l['label_key'])) {
                $noKey[] = $l['column'];
            }
        }
        $this->check('rótulos con clave de idioma', $noKey === [], implode(', ', $noKey));
        $missing = array_filter(array_unique($icons), fn ($i) => $i === '' || !str_contains($css, '.' . $i . '{'));
        $this->check('iconos existentes en public/css/icons.css', $css !== '' && $missing === [], implode(', ', $missing));
    }

    private function namespaces(): void
    {
        $this->title('2. Namespace y núcleo intacto');
        $bad = [];
        foreach ($this->files() as $f) {
            $src = $this->clean($f);
            if (preg_match('/^\s*namespace\s+([^;]+);/m', $src, $mm) && !str_starts_with(trim($mm[1]), 'Components\\LegalCase')) {
                $bad[] = $this->rel($f);
            }
        }
        $this->check('todas las clases en Components\\LegalCase\\…', $bad === [], implode(', ', $bad));
    }

    private function schema(): void
    {
        $this->title('3. Esquema portable');
        $tables = [];
        $issues = [];
        foreach ($this->files('Database') as $f) {
            $src = $this->clean($f);
            preg_match_all("/createTable\('([^']+)'/", $src, $mm);
            foreach ($mm[1] as $t) {
                $tables[] = $t;
                if (!preg_match("/tableExists\('" . preg_quote($t, '/') . "'\)/", $src)) {
                    $issues[] = "$t sin tableExists()";
                }
            }
            if (preg_match("/'type'\s*=>\s*'(ENUM|JSON|SET|YEAR)'/i", $src)) {
                $issues[] = $this->rel($f) . ' usa un tipo no portable';
            }
            preg_match_all("/addForeignKey\('[^']+',\s*'([^']+)'/", $src, $fk);
            foreach ($fk[1] as $ref) {
                if (!str_starts_with($ref, 'cmp_lgc_') && !in_array($ref, ['companies', 'users', 'roles'], true)) {
                    $issues[] = "FK a $ref";
                }
            }
            preg_match_all("/(addColumn|dropColumn|modifyColumn)\('([^']+)'/", $src, $alt);
            foreach ($alt[2] as $t) {
                if ($t !== 'plans' && !str_starts_with($t, 'cmp_lgc_')) {
                    $issues[] = "altera la tabla del núcleo $t";
                }
            }
        }
        $bad = array_filter($tables, fn ($t) => !str_starts_with($t, 'cmp_lgc_'));
        $this->check(count($tables) . ' tablas, todas con prefijo cmp_lgc_', $tables !== [] && $bad === [], implode(', ', $bad));
        $this->check('migraciones idempotentes, sin ENUM/JSON, FK solo a núcleo permitido o propias', $issues === [], implode('; ', $issues));

        $raw = [];
        foreach ($this->files() as $f) {
            // SQL crudo = una cadena pasada a query(). `DataTable::query(fn …)` es un callback.
            if (preg_match('/->query\s*\(\s*[\'"]/', $this->clean($f))) {
                $raw[] = $this->rel($f);
            }
        }
        $this->check('sin SQL crudo (->query)', $raw === [], implode(', ', $raw));
    }

    private function routes(array $m): void
    {
        $this->title('4. Rutas y permisos');
        $src   = $this->clean($this->root . '/Config/Routes.php');
        $parts = preg_split("/\\\$routes->group\(/", $src);
        $admin = '';
        $pub   = '';
        foreach ($parts as $p) {
            if (str_starts_with(ltrim($p), "'legalcase-portal'")) {
                $pub = $p;
            } elseif (str_starts_with(ltrim($p), "'legalcase'")) {
                $admin = $p;
            }
        }
        $this->check('grupo interno con filtro auth', str_contains($admin, "'filter' => 'auth'"));
        preg_match_all('/\$routes->(get|post|match)\((.+?)\);/s', $admin, $rr, PREG_SET_ORDER);
        $noPerm = $wrongVerb = $unknown = $getDestructive = [];
        foreach ($rr as $r) {
            $line = preg_replace('/\s+/', ' ', $r[2]);
            if (!preg_match("/permission:([a-z]+\.[a-z]+)/", $line, $pm)) {
                $noPerm[] = $line;

                continue;
            }
            if (!isset($m['permissions'][$pm[1]])) {
                $unknown[] = $pm[1];
            }
            if (preg_match('/eliminar/', $line) && $pm[1] !== 'lgc.delete') {
                $wrongVerb[] = $line;
            }
            if ($r[1] === 'post' && $pm[1] === 'lgc.view') {
                $wrongVerb[] = $line;
            }
            if ($r[1] === 'get' && preg_match('/eliminar|publicar|aprobar|revocar|validar/', $line)) {
                $getDestructive[] = $line;
            }
        }
        $this->check(count($rr) . ' rutas internas, todas con permission:', $noPerm === [], implode(' | ', $noPerm));
        $this->check('permisos de las rutas declarados en el Manifest', $unknown === [], implode(', ', array_unique($unknown)));
        $this->check('verbo correcto (eliminar → delete; ningún POST con view)', $wrongVerb === [], implode(' | ', $wrongVerb));
        $this->check('ninguna acción destructiva o de estado por GET', $getDestructive === [], implode(' | ', $getDestructive));
        $this->check('rutas bajo los prefijos declarados', !preg_match("/group\('(?!legalcase'|legalcase-portal')/", $src));
        $this->check('el portal va en grupo aparte sin auth del núcleo', $pub !== '' && !str_contains($pub, "'auth'"));
    }

    private function i18n(): void
    {
        $this->title('5. Idiomas');
        $es = require $this->root . '/Language/es/Lgc.php';
        $en = require $this->root . '/Language/en/Lgc.php';
        $this->check('es y en con las mismas claves (' . count($es) . ')', array_keys(array_diff_key($es, $en)) === [] && array_keys(array_diff_key($en, $es)) === [],
            implode(', ', array_merge(array_keys(array_diff_key($es, $en)), array_keys(array_diff_key($en, $es)))));

        $used = [];
        $foreign = [];
        foreach ($this->files() as $f) {
            if (str_contains($f, '/Language/')) {
                continue;
            }
            $src = $this->clean($f);
            preg_match_all("/'Lgc\.([A-Za-z0-9_]+)'/", $src, $mm);
            foreach ($mm[1] as $k) {
                $used[$k] = true;
            }
            if (preg_match("/lang\('(?!Lgc\.)[A-Z][a-z]+\./", $src)) {
                $foreign[] = $this->rel($f);
            }
        }
        $missing = array_filter(array_keys($used), fn ($k) => !str_ends_with($k, '_') && !isset($es[$k]));
        $this->check(count($used) . ' claves estáticas usadas, todas definidas', $missing === [], implode(', ', $missing));
        $this->check('no usa archivos de idioma de otros componentes', $foreign === [], implode(', ', $foreign));
    }

    private function publicViews(): void
    {
        $this->title('6. Vistas públicas autosuficientes (portal y correos)');
        $bad = [];
        foreach (array_merge($this->files('Views/portal'), $this->files('Views/email')) as $f) {
            $src = $this->clean($f);
            foreach (['tailwind.css', 'icons.css', 'fonts.css', 'class="icon ', 'layouts/admin', 'https://', 'http://', 'cdn'] as $needle) {
                if (stripos($src, $needle) !== false) {
                    $bad[] = $this->rel($f) . " ($needle)";
                }
            }
        }
        $this->check('sin tailwind.css, icons.css, layout del núcleo ni recursos externos', $bad === [], implode(', ', $bad));
        $layout = $this->clean($this->root . '/Views/portal/_layout.php');
        $this->check('layout del portal con <style> en línea y modo oscuro', str_contains($layout, '<style>') && str_contains($layout, 'prefers-color-scheme: dark'));
        $this->check('color de marca validado antes de emitirse', str_contains($layout, "preg_match('/^#[0-9a-fA-F]{6}$/'"));
    }

    private function security(): void
    {
        $this->title('7. Seguridad y aislamiento');
        $issues = [];
        foreach ($this->files() as $f) {
            $src = $this->clean($f);
            if (preg_match("/table\('cmp_(?!lgc_)/", $src) || preg_match("/'cmp_(?!lgc_)[a-z]+_/", $src)) {
                $issues[] = $this->rel($f) . ' toca tablas de otro componente';
            }
            if (preg_match('/getPost\([\'"]company_id/', $src)) {
                $issues[] = $this->rel($f) . ' toma company_id del POST';
            }
        }
        $this->check('no lee tablas de otros componentes ni company_id del POST', $issues === [], implode('; ', $issues));

        $ctrl = [];
        foreach ($this->files('Controllers') as $f) {
            $src = $this->clean($f);
            if (str_contains($f, 'ClientPortal') || str_contains($f, 'BaseLgc')) {
                continue;
            }
            if (!str_contains($src, 'extends BaseLgc')) {
                $ctrl[] = $this->rel($f);
            }
        }
        $this->check('controladores internos heredan la guarda de plan/empresa (BaseLgc)', $ctrl === [], implode(', ', $ctrl));

        $portal = $this->clean($this->root . '/Controllers/ClientPortal.php');
        preg_match_all('/public function (\w+)\(/', $portal, $mm);
        $open = array_diff($mm[1], ['__construct', 'index', 'requestLink', 'confirm', 'enter', 'logout']);
        $unguarded = array_filter($open, fn ($fn) => !preg_match('/function ' . $fn . '\([^)]*\)\s*\{\s*\$account = \$this->requireAccount\(\);/', $portal));
        $this->check('cada acción del portal exige la cuenta autenticada', $unguarded === [], implode(', ', $unguarded));

        $storage = $this->clean($this->root . '/Libraries/DocumentService.php');
        $this->check('archivos fuera de public/ (writable/uploads/lgc)', str_contains($storage, "WRITEPATH . 'uploads'") && !str_contains($storage, 'FCPATH'));
    }

    private function surface(): void
    {
        $this->title('Superficie del núcleo que necesita la instalación de destino');
        $classes = $views = $filters = [];
        foreach ($this->files() as $f) {
            if (str_ends_with($f, 'LgcContrato.php')) {
                continue;
            }
            $src = $this->clean($f);
            preg_match_all('/(App(?:\\\\[A-Z][A-Za-z0-9]*)+)/', $src, $mm);
            foreach ($mm[1] as $c) {
                $classes[ltrim($c, '\\')] = true;
            }
            preg_match_all("/view\('((?:layouts|partials)\/[a-z_\/]+)'/", $src, $vv);
            preg_match_all("/extend\('((?:layouts)\/[a-z_]+)'/", $src, $ee);
            foreach (array_merge($vv[1], $ee[1]) as $v) {
                $views[$v] = true;
            }
            preg_match_all("/'filter' => '([a-z]+)/", $src, $ff);
            foreach ($ff[1] as $x) {
                $filters[$x] = true;
            }
        }
        ksort($classes);
        ksort($views);
        CLI::write('  Clases: ' . implode(', ', array_keys($classes)), 'dark_gray');
        CLI::write('  Vistas: ' . implode(', ', array_keys($views)), 'dark_gray');
        CLI::write('  Filtros: ' . implode(', ', array_keys($filters)), 'dark_gray');
        CLI::write('  Tablas: companies, users, company_users (lectura vía modelos), plans (columnas lgc_*)', 'dark_gray');
        CLI::write('  Sesión: active_company_id, user_id, language', 'dark_gray');
        CLI::write('  Servicios: throttler, request', 'dark_gray');

        $missing = array_filter(array_keys($classes), fn ($c) => !class_exists($c) && !trait_exists($c));
        $this->check('todas las clases del núcleo usadas existen en esta instalación', $missing === [], implode(', ', $missing));
    }
}
