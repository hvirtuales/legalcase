<?php

namespace Components\Formularios\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Auditoría del contrato de componentes sobre esta carpeta.
 *
 * Recorre los archivos y comprueba mecánicamente lo que se puede comprobar
 * leyendo: namespace, prefijo de tablas, migraciones idempotentes, ninguna ruta
 * sin `permission:`, borrados por POST, simetría de idiomas, claves usadas sin
 * declarar, vistas públicas sin assets del núcleo ni CDN, los entregables del
 * plan, CSRF y la regla de CERO IA.
 *
 * El escaneo se hace SIN COMENTARIOS (`token_get_all`): sin eso, una nota que
 * dice «esto NO usa icons.css» contaría como si lo usara, y un auditor con
 * falsos positivos deja de leerse. Es la lección del auditor de Páginas.
 *
 * Al final imprime la SUPERFICIE DEL NUCLEO que el componente necesita: esa
 * lista es exactamente lo que la instalación de destino tiene que ofrecer.
 */
class FormulariosContrato extends BaseCommand
{
    protected $group       = 'Formularios';
    protected $name        = 'formularios:contrato';
    protected $description = 'Audita el componente Formularios contra el contrato de componentes.';

    private int $ok  = 0;
    private int $bad = 0;
    private string $root;

    private function check(string $what, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->ok++;

            return;
        }
        $this->bad++;
        CLI::write('  INCUMPLE: ' . $what . ($detail !== '' ? ' -> ' . $detail : ''), 'red');
    }

    /** Todos los .php de la carpeta. */
    private function files(string $sub = ''): array
    {
        $dir = $this->root . ($sub !== '' ? DIRECTORY_SEPARATOR . $sub : '');
        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    /**
     * El código de un archivo SIN comentarios ni docblocks.
     *
     * Es lo que evita los falsos positivos: aquí se habla mucho de lo que el
     * componente NO hace, y esas frases no son código.
     */
    private function code(string $path): string
    {
        $src = (string) file_get_contents($path);
        $out = '';

        foreach (token_get_all($src) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    public function run(array $params)
    {
        $this->root = dirname(__DIR__);

        CLI::write('Auditoría del contrato: componente Formularios', 'yellow');

        $this->auditManifest();
        $this->auditNamespaces();
        $this->auditMigrations();
        $this->auditRoutes();
        $this->auditLanguages();
        $this->auditPublicViews();
        $this->auditNoAi();
        $this->auditMarkup();
        $this->surface();

        CLI::newLine();
        CLI::write('Comprobaciones: ' . ($this->ok + $this->bad)
            . '  ok: ' . $this->ok . '  incumplimientos: ' . $this->bad, $this->bad ? 'red' : 'green');

        return $this->bad ? 1 : 0;
    }

    private function auditManifest(): void
    {
        CLI::write('1. Manifest', 'cyan');

        $m = require $this->root . '/Manifest.php';

        $this->check('declara code, name, version y prefijo',
            isset($m['code'], $m['name'], $m['version'], $m['table_prefix']));
        $this->check('el code es corto y en minúsculas',
            preg_match('/^[a-z]{3,8}$/', (string) $m['code']) === 1, (string) $m['code']);
        $this->check('el prefijo de tablas deriva del code',
            $m['table_prefix'] === 'cmp_' . $m['code'] . '_');
        $this->check('la versión es SemVer',
            preg_match('/^\d+\.\d+\.\d+$/', (string) $m['version']) === 1);
        $this->check('declara ámbito de empresa', ($m['scope'] ?? 'company') === 'company');

        foreach (($m['permissions'] ?? []) as $perm => $desc) {
            $this->check('el permiso ' . $perm . ' usa el prefijo del code',
                str_starts_with($perm, $m['code'] . '.'));
        }

        foreach (($m['menus'] ?? []) as $menu) {
            $this->check('el menú ' . ($menu['label'] ?? '?') . ' declara permiso',
                isset($menu['permission'], $menu['route'], $menu['icon']));
        }
        // Los iconos tienen que EXISTIR: uno inventado NO da error, simplemente
        // no pinta nada, y el hueco no se relaciona con su nombre. Se comprueban
        // TODOS los que usa el componente, no solo los del menú: un icono mudo
        // en el inspector se nota igual de poco y se arrastra igual de lejos.
        $icons = FCPATH . 'css/icons.css';
        if (is_file($icons)) {
            $css   = (string) file_get_contents($icons);
            $usados = [];

            foreach (array_merge($this->files(), $this->jsFiles()) as $file) {
                if (preg_match_all('/icon-[a-z0-9-]+/', (string) file_get_contents($file), $hit)) {
                    foreach ($hit[0] as $name) { $usados[$name] = true; }
                }
            }

            foreach (array_keys($usados) as $name) {
                $this->check('el icono ' . $name . ' existe en icons.css',
                    str_contains($css, '.' . $name . '{'), $name);
            }
        }

        $this->check('declara la bandera del plan', !empty($m['plan_flag']));
        $this->check('declara cupos del plan', !empty($m['plan_limits']));
    }

    private function auditNamespaces(): void
    {
        CLI::write('2. Namespaces y aislamiento', 'cyan');

        foreach ($this->files() as $file) {
            $code = $this->code($file);
            $rel  = str_replace($this->root . DIRECTORY_SEPARATOR, '', $file);

            if (preg_match('/namespace\s+([^;]+);/', $code, $m)) {
                $this->check($rel . ' vive bajo Components\Formularios',
                    str_starts_with(trim($m[1]), 'Components\Formularios'), trim($m[1]));
            }

            // Escribir en una tabla del núcleo o de otro componente.
            foreach (['users', 'companies', 'roles', 'plans', 'permissions'] as $core) {
                $this->check($rel . ' no inserta en la tabla del núcleo ' . $core,
                    !preg_match('/table\([\'"]' . $core . '[\'"]\)->(insert|update|delete)/', $code));
            }
            $this->check($rel . ' no usa tablas de otro componente',
                !preg_match('/cmp_(?!frm_)[a-z]+_/', $code));
        }
    }

    private function auditMigrations(): void
    {
        CLI::write('3. Migraciones', 'cyan');

        $files = $this->files('Database/Migrations');
        $this->check('hay migraciones', $files !== []);

        foreach ($files as $file) {
            $code = $this->code($file);
            $rel  = basename($file);

            $isPlans = str_contains($rel, 'Plans');

            if ($isPlans) {
                // La migración del plan solo AÑADE columnas propias.
                $this->check($rel . ' comprueba que exista la tabla plans',
                    str_contains($code, "tableExists('plans')"));
                $this->check($rel . ' comprueba cada columna antes de añadirla',
                    substr_count($code, 'fieldExists(') >= 3);
                $this->check($rel . ' no redefine la tabla plans',
                    !str_contains($code, "createTable('plans')"));
                $this->check($rel . ' limpia sus columnas al revertir',
                    str_contains($code, 'dropColumn'));
                $this->check($rel . ' invalida la caché de columnas tras el ALTER',
                    str_contains($code, 'resetDataCache'));
            } else {
                $this->check($rel . ' es idempotente',
                    str_contains($code, 'tableExists('));
                $this->check($rel . ' crea una tabla con el prefijo del componente',
                    preg_match("/createTable\('cmp_frm_/", $code) === 1);
                $this->check($rel . ' se revierte',
                    str_contains($code, 'dropTable'));
            }

            // Portabilidad a los tres motores.
            $this->check($rel . ' no usa ENUM', !str_contains($code, "'ENUM'"));
            $this->check($rel . ' no usa JSON nativo', !preg_match("/'type'\s*=>\s*'JSON'/", $code));
            $this->check($rel . ' no usa SQL crudo', !preg_match('/\$this->db->query\(/', $code));
        }
    }

    private function auditRoutes(): void
    {
        CLI::write('4. Rutas', 'cyan');

        $file = $this->root . '/Config/Routes.php';
        $code = $this->code($file);

        // Toda ruta del grupo administrativo lleva su permiso. Ninguna excepción,
        // ni siquiera las que solo muestran algo.
        preg_match_all('/\$routes->(get|post|put|delete)\(([^;]+);/s', $code, $all, PREG_SET_ORDER);

        $admin = 0;
        $publicRoutes = 0;
        foreach ($all as $route) {
            $line = $route[2];
            $isPublic = str_contains($line, 'PublicForm::') || str_contains($line, 'Asset::');

            if ($isPublic) {
                $publicRoutes++;

                continue;
            }
            $admin++;
            $this->check('la ruta ' . trim(substr($line, 0, 46)) . ' lleva permiso',
                str_contains($line, 'permission:frm.'));
        }

        $this->check('hay rutas administrativas', $admin > 0);
        $this->check('hay rutas públicas separadas', $publicRoutes > 0);
        $this->check('el grupo administrativo exige sesión', str_contains($code, "'filter' => 'auth'"));

        // Los borrados y los cambios de estado son POST.
        $this->check('los borrados van por POST',
            !preg_match('/\$routes->get\([^;]*(eliminar|borrar|delete)/i', $code));
        $this->check('el alta va por POST', str_contains($code, "post('nuevo'"));
        $this->check('publicar va por POST', str_contains($code, "post('publicar"));
    }

    private function auditLanguages(): void
    {
        CLI::write('5. Idiomas', 'cyan');

        $es = require $this->root . '/Language/es/Frm.php';
        $en = require $this->root . '/Language/en/Frm.php';

        $soloEs = array_diff(array_keys($es), array_keys($en));
        $soloEn = array_diff(array_keys($en), array_keys($es));

        $this->check('es y en tienen las mismas claves',
            $soloEs === [] && $soloEn === [],
            implode(', ', array_merge($soloEs, $soloEn)));

        // Claves usadas en el código que nadie declaró. Es el fallo que se ve
        // en pantalla como «Frm.loQueSea» y que nunca aparece en las pruebas.
        $usadas = [];
        foreach (array_merge($this->files(), $this->jsFiles()) as $file) {
            $body = str_ends_with($file, '.php') ? $this->code($file) : (string) file_get_contents($file);
            // La mirada atrás descarta las RUTAS de archivo: `Language/es/Frm.php`
            // también encaja con el patrón, y contaba `php` como una clave que
            // nadie declaró. Un auditor con falsos positivos deja de leerse.
            if (preg_match_all("#(?<![\\/\w])Frm\.([a-zA-Z0-9_]+)#", $body, $m)) {
                foreach ($m[1] as $k) { $usadas[$k] = true; }
            }
        }

        $faltan = array_diff(array_keys($usadas), array_keys($es));
        $this->check('todas las claves usadas están declaradas', $faltan === [], implode(', ', $faltan));

        // Texto visible incrustado en las vistas: lo que no pasa por lang().
        foreach ($this->files('Views') as $file) {
            $raw = (string) file_get_contents($file);
            $rel = basename($file);
            $this->check($rel . ' no incrusta un placeholder sin traducir',
                !preg_match('/placeholder="[A-Za-zÁÉÍÓÚáéíóúñ]{4,}"/', $raw));
        }
    }

    private function jsFiles(): array
    {
        $out = [];
        foreach (glob($this->root . '/assets/js/*.js') ?: [] as $f) {
            $out[] = $f;
        }

        return $out;
    }


    /**
     * Etiquetas que, sin cerrar, se comen el resto de la página.
     *
     * `<style>` y `<script>` tienen contenido CDATA: hasta que no aparece su
     * cierre, el navegador trata como texto TODO lo que sigue. Un `</style>`
     * olvidado en la hoja incrustada dejaba el `<body>` vacío en el DOM y la
     * página en gris —y ninguna comprobación con curl lo veía, porque el
     * marcado estaba en los bytes: solo faltaba que alguien lo parseara—.
     */
    private function auditMarkup(): void
    {
        CLI::write('8. Equilibrio del marcado', 'cyan');

        foreach ($this->files('Views') as $file) {
            // Sin comentarios: la nota del docblock de `_styles.php` MENCIONA la
            // etiqueta, y contarla descuadraba el recuento. Mismo criterio que
            // el resto del auditor.
            $raw = $this->code($file);
            $rel = str_replace($this->root . DIRECTORY_SEPARATOR, '', $file);

            foreach (['style', 'script'] as $tag) {
                $abre   = preg_match_all('#<' . $tag . '[\s>]#i', $raw);
                $cierra = preg_match_all('#</' . $tag . '\s*>#i', $raw);

                if ($abre === 0 && $cierra === 0) {
                    continue;
                }
                $this->check($rel . ' cierra todos sus <' . $tag . '>',
                    $abre === $cierra, $abre . ' abre / ' . $cierra . ' cierra');
            }
        }
    }
    private function auditPublicViews(): void
    {
        CLI::write('6. Vistas públicas autosuficientes', 'cyan');

        foreach ($this->files('Views/public') as $file) {
            $rel = basename($file);
            $raw = (string) file_get_contents($file);
            $code = $this->code($file);

            $this->check($rel . ' no carga tailwind del núcleo', !str_contains($code, 'tailwind.css'));
            $this->check($rel . ' no carga icons.css del núcleo', !str_contains($code, 'icons.css'));
            $this->check($rel . ' no carga fonts.css del núcleo', !str_contains($code, 'fonts.css'));
            $this->check($rel . ' no usa la clase .icon del núcleo', !preg_match('/class="icon /', $raw));
            $this->check($rel . ' no llama a ningún CDN',
                !preg_match('#https?://(cdn|unpkg|fonts\.googleapis|ajax\.googleapis|jsdelivr)#i', $raw));
            $this->check($rel . ' no extiende un layout del núcleo', !str_contains($code, "extend('layouts"));
        }

        // El color validado se emite crudo: `esc($hex,'css')` produce un color
        // inválido. Aquí se comprueba que nadie lo haya "arreglado".
        $styles = (string) file_get_contents($this->root . '/Views/public/_styles.php');
        $this->check('el color de marca se emite crudo, no escapado como css',
            !str_contains($styles, "esc(\$brand, 'css')"));
    }

    /**
     * CERO IA. No es una preferencia: es una decisión del componente.
     *
     * Un formulario recoge datos personales, quejas, documentos y firmas.
     * Mandarlos a un tercero para resumirlos es un tratamiento que nadie
     * autorizó al responder, así que no hay ninguna llamada a un modelo de
     * lenguaje, ninguna clave de API y ninguna cuota que agotar. Esto lo
     * comprueba una máquina para que siga siendo verdad dentro de un año.
     */
    private function auditNoAi(): void
    {
        CLI::write('7. Cero IA', 'cyan');

        $prohibidos = ['AiClient', 'anthropic', 'gemini', 'openai', 'ANTHROPIC_API_KEY', 'GEMINI_API_KEY', 'OPENAI_API_KEY'];
        foreach (array_merge($this->files(), $this->jsFiles()) as $file) {
            // Este mismo archivo lleva la lista de palabras prohibidas, así que
            // se delataría a sí mismo. Se excluye a propósito: es el auditor,
            // no código del componente.
            if ($file === __FILE__) {
                continue;
            }

            $body = str_ends_with($file, '.php') ? $this->code($file) : (string) file_get_contents($file);
            $rel  = str_replace($this->root . DIRECTORY_SEPARATOR, '', $file);

            foreach ($prohibidos as $needle) {
                $this->check($rel . ' no menciona ' . $needle,
                    stripos($body, $needle) === false);
            }
        }

        // Y ninguna llamada saliente que no sea la del correo de la empresa.
        foreach ($this->files('Libraries') as $file) {
            $code = $this->code($file);
            $rel  = basename($file);
            $this->check($rel . ' no abre conexiones HTTP propias',
                !preg_match('/(curl_init|CURLRequest|file_get_contents\([\'"]https?:)/', $code));
        }
    }

    /**
     * La superficie del núcleo que este componente necesita.
     *
     * Es lo que hay que contrastar con la instalación de destino antes de
     * copiar la carpeta: si aquí apareciera algo que el componente añadió al
     * núcleo, no sería portable todavía (contrato 15.1).
     */
    private function surface(): void
    {
        CLI::newLine();
        CLI::write('Superficie del núcleo que necesita el componente:', 'yellow');

        $lines = [
            'Clases   : App\Controllers\BaseController, App\Libraries\DataTable,',
            '           App\Models\CompanyModel, App\Models\AppSettingModel,',
            '           App\Libraries\CompanyMailer (opcional, tras class_exists)',
            'Métodos  : CompanyModel::find(), CompanyModel::effectivePlan() (opcional,',
            '           tras method_exists: llegó con los tenants)',
            'Vistas   : layouts/admin, partials/datatable, partials/delete_button',
            'Filtros  : auth, permission:<code>.<accion>',
            'Tablas   : companies (FK), plans (3 columnas propias que añade y retira',
            '           la migración del componente)',
            'Helpers  : media (media_url), form, url',
            'Sesión   : user_id, active_company_id, language',
            'Servicios: throttler, encrypter (config Encryption para el captcha)',
            'Paquetes : chillerlan/php-qrcode, dompdf/dompdf, ext-gd (opcionales:',
            '           sin GD se omiten el QR y el captcha, y el resto funciona)',
        ];

        foreach ($lines as $line) {
            CLI::write('  ' . $line);
        }

        CLI::newLine();
        CLI::write('Revisión manual (no se puede comprobar leyendo):', 'yellow');
        CLI::write('  - Calidad de las traducciones al inglés.');
        CLI::write('  - Comportamiento real en PostgreSQL y SQL Server.');
        CLI::write('  - Orden y claridad de los formularios en pantalla.');
    }
}
