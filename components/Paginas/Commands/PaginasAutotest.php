<?php

namespace Components\Paginas\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Paginas\Libraries\BlockRegistry;
use Components\Paginas\Libraries\HtmlSanitizer;
use Components\Paginas\Libraries\PageService;
use Components\Paginas\Libraries\Renderer;
use Components\Paginas\Libraries\SectionLibrary;

/**
 * Autotest del componente Páginas. Comprueba lo que de verdad duele si se rompe:
 * el **saneado del árbol** (la frontera entre el navegador y la base de datos),
 * el **escapado del renderizador** y las reglas del formulario de contacto.
 *
 * Sigue el precedente de `spark tenant:autotest` del núcleo: mientras el
 * proyecto no tenga suite de PHPUnit, un comando que se pueda ejecutar en
 * cualquier despliegue vale más que ningún test. No toca la base de datos —todo
 * lo que prueba es lógica pura—, así que se puede correr en producción.
 *
 *     php spark paginas:autotest
 */
class PaginasAutotest extends BaseCommand
{
    protected $group       = 'Páginas';
    protected $name        = 'paginas:autotest';
    protected $description = 'Comprueba el saneado del árbol, el renderizador y el formulario de contacto.';

    private int $passed = 0;
    private int $failed = 0;

    public function run(array $params)
    {
        try {
            $this->registry();
            $this->sanitizer();
            $this->tree();
            $this->structure();
            $this->renderer();
            $this->form();
            $this->companyBlocks();
            $this->sections();
            $this->gutenbergParity();
            $this->propertyCoverage();
        } catch (\Throwable $e) {
            CLI::error('Excepción: ' . $e->getMessage());
            CLI::write($e->getFile() . ':' . $e->getLine());
            $this->failed++;
        }

        CLI::newLine();
        CLI::write(
            'Pasaron: ' . $this->passed . ' · Fallaron: ' . $this->failed,
            $this->failed ? 'red' : 'green'
        );

        return $this->failed ? 1 : 0;
    }

    private function check(string $name, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->passed++;
            CLI::write('  ✓ ' . $name, 'green');

            return;
        }
        $this->failed++;
        CLI::write('  ✗ ' . $name . ($detail !== '' ? '  →  ' . $detail : ''), 'red');
    }

    private function section(string $title): void
    {
        CLI::newLine();
        CLI::write($title, 'yellow');
    }

    // ------------------------------------------------------- catálogo de bloques

    private function registry(): void
    {
        $this->section('1. Catálogo de bloques');
        $types = BlockRegistry::types();

        $this->check('hay tipos declarados', count($types) > 10, (string) count($types));
        $this->check('todos declaran valores por defecto', !array_filter($types, static fn ($d) => !isset($d['defaults'])));

        // Un default que no corresponda a ningún campo se perdería en el saneado:
        // el bloque nacería con un valor que nadie puede editar ni guardar.
        $huerfano = '';

        foreach ($types as $type => $def) {
            $names = array_column($def['fields'] ?? [], 'name');

            foreach (array_keys($def['defaults'] ?? []) as $k) {
                if (!in_array($k, $names, true)) {
                    $huerfano = "{$type}.{$k}";
                    break 2;
                }
            }
        }
        $this->check('ningún valor por defecto sin su campo', $huerfano === '', $huerfano);

        // Un default fuera de la lista de opciones haría que el renderizador
        // cayera siempre a su respaldo, ignorando lo elegido.
        $malo = '';

        foreach ($types as $type => $def) {
            foreach ($def['fields'] ?? [] as $f) {
                if (($f['type'] ?? '') !== 'select') {
                    continue;
                }
                $d = $def['defaults'][$f['name']] ?? null;
                if ($d !== null && $d !== '' && !array_key_exists((string) $d, $f['options'] ?? [])) {
                    $malo = "{$type}.{$f['name']} = {$d}";
                    break 2;
                }
            }
        }
        $this->check('cada desplegable tiene su valor por defecto entre sus opciones', $malo === '', $malo);

        // Todo tipo con grupo tiene que poder pintarse.
        $sinRender = '';
        $r         = new Renderer([], ['editor' => true]);

        foreach ($types as $type => $def) {
            if (($def['group'] ?? null) === null) {
                continue; // `column` se pinta desde su sección
            }
            $block = (new PageService())->newBlock($type);
            // Se rellena lo mínimo para que el bloque no quede vacío.
            $block['props'] = array_merge($block['props'], [
                'text' => 'x', 'title' => 'x', 'label' => 'x', 'html' => '<p>x</p>',
                'src' => '/uploads/x.png', 'image' => '/uploads/x.png', 'code' => '<p>x</p>',
                'url' => 'https://youtu.be/abcdefghijk', 'href' => 'https://x.co',
                'items' => [['text' => 'x', 'src' => '/uploads/x.png', 'title' => 'x', 'label' => 'x', 'href' => 'https://x.co']],
                'fields' => [['label' => 'x', 'type' => 'text', 'options' => '', 'required' => '0']],
            ]);
            if ($r->render([$block]) === '') {
                $sinRender = $type;
                break;
            }
        }
        $this->check('todo tipo de la paleta produce HTML', $sinRender === '', $sinRender);
    }

    // ------------------------------------------------------------- saneador HTML

    private function sanitizer(): void
    {
        $this->section('2. Saneador de HTML');
        $s = new HtmlSanitizer();

        $this->check('quita onclick', !str_contains($s->clean('<p onclick="alert(1)">hola</p>'), 'onclick'));
        $this->check('quita href="javascript:"', !str_contains($s->clean('<a href="javascript:alert(1)">x</a>'), 'javascript'));
        $this->check('quita <script>', !str_contains(strtolower($s->clean('<script>alert(1)</script><p>ok</p>')), '<script'));
        $this->check('quita <iframe>', !str_contains(strtolower($s->clean('<iframe src="http://x"></iframe><p>ok</p>')), '<iframe'));
        $this->check('conserva negrita y listas', str_contains($s->clean('<p><strong>a</strong></p><ul><li>b</li></ul>'), '<strong>'));
        $this->check('el enlace sale con rel="noopener"', str_contains($s->clean('<a href="https://x.co">x</a>'), 'noopener'));
        $this->check('rechaza //evil.com (protocolo-relativa)', !$s->safeUrl('//evil.com'));
        $this->check('rechaza data:', !$s->safeUrl('data:text/html;base64,AAA'));
        $this->check('rechaza javascript:', !$s->safeUrl('javascript:alert(1)'));
        $this->check('acepta una ruta relativa', $s->safeUrl('/uploads/a.png'));
        $this->check('acepta mailto:', $s->safeUrl('mailto:a@b.co'));
    }

    // ------------------------------------- frontera navegador → base de datos

    private function tree(): void
    {
        $this->section('3. Saneado del árbol (lo que llega del editor)');
        $svc = new PageService();

        $hostil = [
            ['id' => 'x1', 'type' => 'inventado', 'props' => ['a' => 1]],
            ['id' => '<script>', 'type' => 'heading', 'props' => [
                'text'   => '<b>Hola</b>',
                'level'  => '99',
                'align'  => 'DROP TABLE',
                'color'  => 'red; background:url(x)',
                'colado' => 'no declarado',
            ]],
            ['id' => 'x3', 'type' => 'button', 'props' => [
                'label' => 'Clic', 'href' => 'javascript:alert(1)', 'newTab' => 'true',
            ]],
            ['id' => 'x4', 'type' => 'text', 'props' => ['html' => '<p onmouseover="x()">hola</p>']],
        ];
        $limpio = $svc->sanitizeTree($hostil);

        $this->check('descarta el tipo inventado', count($limpio) === 3, 'quedaron ' . count($limpio));

        $h = $limpio[0] ?? ['props' => [], 'id' => ''];
        $this->check('un id inválido se regenera', (bool) preg_match('/^b[0-9a-f]{10}$/', (string) $h['id']), (string) $h['id']);
        $this->check('el texto pierde sus etiquetas', ($h['props']['text'] ?? '') === 'Hola', var_export($h['props']['text'] ?? null, true));
        $this->check('un valor fuera de lista cae al primero', ($h['props']['level'] ?? '') === '1', (string) ($h['props']['level'] ?? ''));
        $this->check('una alineación inventada cae al primero', ($h['props']['align'] ?? '') === 'left', (string) ($h['props']['align'] ?? ''));

        // Regresión: las opciones NUMÉRICAS tienen que sobrevivir. PHP convierte
        // en enteros las claves de array que son números, así que la comparación
        // estricta contra la cadena del bloque fallaba siempre y todas caían al
        // valor por defecto — en silencio.
        $num = $svc->sanitizeTree([
            ['type' => 'heading', 'id' => 'n1', 'props' => ['text' => 'x', 'level' => '4']],
            ['type' => 'section', 'id' => 'n2', 'props' => ['columns' => '4'], 'children' => []],
            ['type' => 'cards', 'id' => 'n3', 'props' => ['columns' => '2', 'items' => [['title' => 'x']]]],
            ['type' => 'gallery', 'id' => 'n4', 'props' => ['columns' => '4', 'items' => [['src' => '/uploads/a.png']]]],
        ]);
        $this->check('un nivel de título válido se conserva', ($num[0]['props']['level'] ?? '') === '4', (string) ($num[0]['props']['level'] ?? ''));
        $this->check('4 columnas de sección se conservan', ($num[1]['props']['columns'] ?? '') === '4', (string) ($num[1]['props']['columns'] ?? ''));
        $this->check('2 columnas de tarjetas se conservan', ($num[2]['props']['columns'] ?? '') === '2', (string) ($num[2]['props']['columns'] ?? ''));
        $this->check('4 columnas de galería se conservan', ($num[3]['props']['columns'] ?? '') === '4', (string) ($num[3]['props']['columns'] ?? ''));

        $r4 = new Renderer([], ['editor' => true]);
        $this->check('y el renderizador las pinta', str_contains($r4->render([$num[0]]), '<h4'), $r4->render([$num[0]]));
        $this->check('  también en la sección', str_contains($r4->render([$num[1]]), 'pg-cols-4'), $r4->render([$num[1]]));
        $this->check('un color que no es hex se vacía', ($h['props']['color'] ?? 'x') === '', var_export($h['props']['color'] ?? null, true));
        $this->check('la propiedad no declarada desaparece', !array_key_exists('colado', $h['props']));
        $this->check('href="javascript:" se vacía', ($limpio[1]['props']['href'] ?? 'x') === '', var_export($limpio[1]['props']['href'] ?? null, true));
        $this->check('el interruptor se normaliza a 1/0', ($limpio[1]['props']['newTab'] ?? '') === '1', (string) ($limpio[1]['props']['newTab'] ?? ''));
        $this->check('el texto enriquecido se sanea', !str_contains((string) ($limpio[2]['props']['html'] ?? ''), 'onmouseover'));

        // Techos: un árbol absurdo no puede tumbar el render ni la BD.
        $hondo  = ['type' => 'section', 'id' => 'a', 'props' => [], 'children' => []];
        $cursor = &$hondo;

        for ($i = 0; $i < 20; $i++) {
            $cursor['children'] = [['type' => 'section', 'id' => 'a' . $i, 'props' => [], 'children' => []]];
            $cursor             = &$cursor['children'][0];
        }
        unset($cursor);

        $prof  = $svc->sanitizeTree([$hondo]);
        $depth = 0;
        $n     = $prof[0] ?? null;

        while ($n && !empty($n['children'])) {
            $depth++;
            $n = $n['children'][0];
        }
        $this->check('la profundidad se corta', $depth <= 6, 'profundidad ' . $depth);

        $muchos = array_fill(0, 800, ['type' => 'spacer', 'id' => 'z', 'props' => []]);
        $this->check('el número de bloques se corta en 500', count($svc->sanitizeTree($muchos)) === 500);

        $rep = $svc->sanitizeTree([['type' => 'list', 'id' => 'l', 'props' => [
            'style' => 'check',
            'items' => array_fill(0, 90, ['text' => 'x']),
        ]]]);
        $this->check('el repetidor se corta en 50 filas', count($rep[0]['props']['items']) === 50, (string) count($rep[0]['props']['items']));

        // Jerarquía: una columna suelta se pintaría como un div huérfano, y una
        // sección con hijos que no son columnas rompería la rejilla.
        $suelta = $svc->sanitizeTree([['type' => 'column', 'id' => 'c', 'props' => [], 'children' => []]]);
        $this->check('una columna suelta en la raíz se descarta', $suelta === [], json_encode($suelta));

        $mezcla = $svc->sanitizeTree([['type' => 'section', 'id' => 's', 'props' => [], 'children' => [
            ['type' => 'column', 'id' => 'c', 'props' => [], 'children' => []],
            ['type' => 'heading', 'id' => 'h', 'props' => ['text' => 'suelto']],
        ]]]);
        $this->check(
            'una sección solo conserva sus columnas',
            count($mezcla[0]['children'] ?? []) === 1 && ($mezcla[0]['children'][0]['type'] ?? '') === 'column',
            json_encode(array_column($mezcla[0]['children'] ?? [], 'type'))
        );

        $dentro = $svc->sanitizeTree([['type' => 'section', 'id' => 's', 'props' => [], 'children' => [
            ['type' => 'column', 'id' => 'c', 'props' => [], 'children' => [
                ['type' => 'heading', 'id' => 'h', 'props' => ['text' => 'ok']],
            ]],
        ]]]);
        $this->check(
            'dentro de una columna sí cabe cualquier bloque',
            ($dentro[0]['children'][0]['children'][0]['type'] ?? '') === 'heading',
            json_encode($dentro)
        );
    }

    // --------------------------------------------------------------- estructura

    private function structure(): void
    {
        $this->section('4. Estructura del árbol');
        $svc = new PageService();

        $sec = $svc->newBlock('section', ['columns' => '3']);
        $this->check('una sección de 3 nace con 3 columnas', count($sec['children']) === 3, (string) count($sec['children']));
        $this->check('sus hijos son columnas', ($sec['children'][0]['type'] ?? '') === 'column');
        $this->check('reindex cambia los identificadores', ($svc->reindex([$sec])[0]['id'] ?? '') !== $sec['id']);
        $this->check('countBlocks recorre los hijos', $svc->countBlocks([$sec]) === 4, (string) $svc->countBlocks([$sec]));
        $this->check('slugify quita acentos y símbolos', $svc->slugify('Quiénes Somos & Más') === 'quienes-somos-mas', $svc->slugify('Quiénes Somos & Más'));
        $this->check('slugify acota a 180 caracteres', mb_strlen($svc->slugify(str_repeat('a', 300))) <= 180);

        $form = $svc->newBlock('form');
        $tree = [$sec];
        $tree[0]['children'][0]['children'][] = $form;
        $this->check('findBlock encuentra un bloque anidado', ($svc->findBlock($tree, $form['id'])['id'] ?? '') === $form['id']);
        $this->check('findBlock no inventa bloques', $svc->findBlock($tree, 'no-existe') === null);
    }

    // -------------------------------------------------------------- renderizador

    private function renderer(): void
    {
        $this->section('5. Renderizador (la salida buena)');
        $svc = new PageService();
        $r   = new Renderer([], ['editor' => true]);

        $html = $r->render($svc->sanitizeTree([
            ['type' => 'heading', 'id' => 'h', 'props' => ['text' => 'Hola <b>mundo</b>', 'level' => '2', 'align' => 'center']],
        ]));
        $this->check('el título sale escapado', !str_contains($html, '<b>'), $html);
        $this->check('usa la clase de alineación', str_contains($html, 'pg-a-center'));

        $html = $r->render([['type' => 'video', 'id' => 'v', 'props' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'ratio' => '16-9']]]);
        $this->check('YouTube se reescribe a nocookie/embed', str_contains($html, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'), $html);

        $html = $r->render([['type' => 'video', 'id' => 'v', 'props' => ['url' => 'https://evil.com/x?v=1']]]);
        $this->check('una URL ajena no produce iframe', !str_contains($html, '<iframe'), $html);

        $html = $r->render([['type' => 'video', 'id' => 'v', 'props' => ['url' => 'https://vimeo.com/123456789']]]);
        $this->check('Vimeo se reescribe a player.vimeo.com', str_contains($html, 'player.vimeo.com/video/123456789'));

        $html = $r->render([['type' => 'section', 'id' => 's', 'props' => ['background' => '#ff0000', 'columns' => '2'], 'children' => [
            ['type' => 'column', 'id' => 'c1', 'props' => [], 'children' => [['type' => 'heading', 'id' => 'hh', 'props' => ['text' => 'A']]]],
            ['type' => 'column', 'id' => 'c2', 'props' => [], 'children' => []],
        ]]]);
        $this->check('la sección pinta su fondo hex', str_contains($html, 'background-color:#ff0000'));
        $this->check('pinta sus dos columnas', substr_count($html, 'pg-col ') === 2, (string) substr_count($html, 'pg-col '));
        $this->check('el bloque anidado se pinta', str_contains($html, '>A</h2>'));

        $html = $r->render([['type' => 'section', 'id' => 's', 'props' => ['background' => 'red; x:y'], 'children' => []]]);
        $this->check('un fondo que no es hex no llega al atributo style', !str_contains($html, 'background-color'), $html);

        // La portada mete la URL dentro de un `url()` de CSS, dentro de un
        // atributo HTML: dos decodificaciones seguidas, así que una comilla o un
        // paréntesis sin codificar cerraría la regla e inyectaría CSS.
        //
        // Las rutas relativas las percent-codifica `media_url()` al anteponer el
        // dominio, así que llegan inofensivas. La vía que hay que vigilar es la
        // **URL absoluta** (almacenamiento S3/R2), que `media_url()` devuelve tal
        // cual: esa tiene que quedar fuera, no escapada.
        foreach (['https://cdn.ejemplo.com/a").evil(x', '/uploads/a").evil(x'] as $peligrosa) {
            $html = $r->render([['type' => 'hero', 'id' => 'x', 'props' => [
                'title' => 'T', 'image' => $peligrosa, 'overlay' => 'dark',
            ]]]);
            // Se extrae lo que quedaría DENTRO de url("…") tras decodificar el
            // HTML: eso es exactamente lo que va a leer el motor de CSS.
            $dentro = preg_match('/url\(&quot;(.*)&quot;\)/', $html, $m)
                ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')
                : '';
            $this->check(
                'una portada con comillas no puede romper el url() de CSS: ' . $peligrosa,
                $dentro === '' || !preg_match('/["\'()\\\\]/', $dentro),
                $dentro !== '' ? $dentro : '(no se pintó, correcto)'
            );
        }

        $html = $r->render([['type' => 'hero', 'id' => 'x', 'props' => [
            'title' => 'T', 'image' => '/uploads/portada.jpg', 'overlay' => 'dark',
        ]]]);
        $this->check('una imagen de portada normal sí se pinta', str_contains($html, 'background-image'), $html);

        $this->check('un tipo desconocido no pinta nada', $r->render([['type' => 'inventado', 'id' => 'e', 'props' => []]]) === '');
        $this->check('el espaciador se pinta', str_contains($r->render([['type' => 'spacer', 'id' => 's', 'props' => ['size' => 'lg']]]), 'pg-sp-lg'));
        $this->check('el separador se pinta', str_contains($r->render([['type' => 'divider', 'id' => 'd', 'props' => ['style' => 'dashed']]]), 'pg-hr-dashed'));

        // El bloque de HTML avanzado sale crudo, así que lo que se guardó tiene
        // que venir ya limpio: se comprueba la cadena completa guardar → pintar.
        $sucio = $svc->sanitizeTree([['type' => 'html', 'id' => 'x', 'props' => ['code' => '<script>alert(1)</script><p>ok</p>']]]);
        $this->check('el HTML avanzado se guarda ya limpio', !str_contains(strtolower($r->render($sucio)), '<script'), $r->render($sucio));
    }

    // -------------------------------------------------- formulario de contacto

    private function form(): void
    {
        $this->section('6. Formulario de contacto');
        $svc = new PageService();

        $form = $svc->newBlock('form', [
            'title'  => 'Escríbenos',
            'fields' => [
                ['label' => 'Nombre', 'type' => 'text', 'options' => '', 'required' => '1'],
                ['label' => 'Correo', 'type' => 'email', 'options' => '', 'required' => '1'],
                ['label' => 'Ciudad', 'type' => 'select', 'options' => 'Bogotá, Cali', 'required' => '0'],
            ],
        ]);

        $res = $svc->collectSubmission($form, ['f0' => 'Ana', 'f1' => 'ana@x.co', 'f2' => 'Cali', 'colado' => 'x']);
        $this->check('acepta un envío válido', $res['ok'], $res['error']);
        $this->check('guarda etiqueta => valor', ($res['data']['Nombre'] ?? '') === 'Ana');
        $this->check('descarta lo que el bloque no declaró', !array_key_exists('colado', $res['data']));
        $this->check('rechaza un obligatorio vacío', !$svc->collectSubmission($form, ['f0' => '', 'f1' => 'a@b.co'])['ok']);
        $this->check('rechaza un correo inválido', !$svc->collectSubmission($form, ['f0' => 'Ana', 'f1' => 'no-es-correo'])['ok']);

        $pub  = new Renderer([], ['formAction' => 'https://x/enviar', 'csrf' => '<input name="csrf_test">']);
        $html = $pub->render([$form]);
        $this->check('el formulario público es un POST', str_contains($html, '<form class="pg-form" method="post"'));
        $this->check('lleva el token CSRF', str_contains($html, 'name="csrf_test"'));
        $this->check('lleva el id del bloque', str_contains($html, 'name="block"'));
        $this->check('los campos van por posición', str_contains($html, 'name="f0"') && str_contains($html, 'name="f1"'));
        $this->check('el desplegable pinta sus opciones', str_contains($html, '>Bogotá</option>'));

        $edit = new Renderer([], ['editor' => true]);
        $this->check('en el editor no se puede enviar', !str_contains($edit->render([$form]), '<form'));

        $sent = new Renderer([], ['sentBlock' => $form['id'], 'formAction' => 'https://x/enviar']);
        $this->check('tras enviar se muestra el acuse', str_contains($sent->render([$form]), 'pg-form-success'));
    }

    // ----------------------------------------------------- bloques de empresa

    /** Empresa de mentira con todo relleno, para no depender de la BD. */
    private function fakeCompany(): array
    {
        return [
            'name'          => 'Industrias Acme S.A.S.',
            'nit'           => '900.123.456-7',
            'slug'          => 'acme',
            'logo'          => '/uploads/logo.png',
            'banner'        => '/uploads/banner.jpg',
            'description'   => "Fabricamos y mantenemos equipos industriales.\nDesde 1998.",
            'address'       => 'Calle 100 #10-20',
            'city'          => 'Bogotá',
            'website'       => 'https://www.acme.com.co/inicio/',
            'contact_email' => 'contacto@acme.com.co',
            'contact_phone' => '+57 601 555 0100',
            'whatsapp'      => '573001234567',
            'facebook'      => 'https://facebook.com/acme',
            'instagram'     => 'https://instagram.com/acme',
            'linkedin'      => 'https://linkedin.com/company/acme',
            'tiktok'        => '',
            'youtube'       => '',
        ];
    }

    private function companyBlocks(): void
    {
        $this->section('7. Bloques de empresa');
        $svc = new PageService();
        $co  = $this->fakeCompany();

        $pub = new Renderer([], ['company' => $co, 'pageUrl' => 'https://mi.sitio/pagina/acme/inicio']);
        $ed  = new Renderer([], ['company' => $co, 'pageUrl' => 'https://mi.sitio/pagina/acme/inicio', 'editor' => true]);
        $sin = new Renderer([], []);                        // sin empresa, público
        $sinEd = new Renderer([], ['editor' => true]);       // sin empresa, editor

        // --- cabecera y pie: ahora son SECCIONES, no bloques ---------------
        //
        // Eran dos tipos propios (`siteHeader`/`siteFooter`) con veinte campos
        // cada uno. Ahora se arman con los elementos de siempre, así que lo que
        // hay que comprobar es otra cosa: que la sección que ofrece la paleta
        // sigue produciendo una cabecera de verdad —logo de la empresa, menú
        // con anclas, hamburguesa sin guion y fija al desplazar— y que sus
        // piezas son bloques editables uno a uno.
        $cab   = SectionLibrary::header($svc, true);
        $arbol = $svc->sanitizeTree([$cab]);
        $html  = $pub->render($arbol);

        $this->check('la cabecera sobrevive al saneado', $arbol !== [] && ($arbol[0]['type'] ?? '') === 'section',
            json_encode(array_column($arbol, 'type')));
        $this->check('  y está hecha de elementos editables',
            $this->typesIn($arbol) === ['section', 'column', 'image', 'column', 'buttons'],
            implode(', ', $this->typesIn($arbol)));
        $this->check('saca el logo de la empresa', str_contains($html, '/uploads/logo.png'), $html);
        $this->check('  con el ancho que se le pone', str_contains($html, 'width:150px'), $html);
        $this->check('  y su enlace al ancla NO se vuelve absoluto',
            str_contains($html, '<a href="#inicio">'), $html);
        $this->check('el menú admite anclas de la propia página', str_contains($html, 'href="#servicios"'), $html);
        $this->check('  y lleva su botón destacado', str_contains($html, 'pg-btn-primary'), $html);

        // La hamburguesa NO puede depender de JavaScript: la página pública no
        // carga ni una línea de guion (§7.3).
        $this->check('el menú plegable no usa JavaScript',
            str_contains($html, 'pg-nav-check') && !str_contains($html, '<script'), $html);
        $this->check('  y su casilla lleva id único por bloque',
            preg_match('/id="pg-nav-[A-Za-z0-9_-]+"/', $html) === 1, $html);
        $this->check('«fija al desplazar» llega al HTML', str_contains($html, 'pg-sec-sticky'), $html);
        $enEditor = $ed->render($arbol);
        $this->check('  pero NO dentro del editor (taparía el lienzo)', !str_contains($enEditor, 'pg-sec-sticky'), '');

        // Sin empresa, la imagen de logo no inventa nada.
        $logoBloque = [$svc->newBlock('image', ['source' => 'logo'])];
        $this->check('sin empresa el logo no pinta nada',
            $sin->render($svc->sanitizeTree($logoBloque)) === '', '');
        $this->check('  y avisa en el editor',
            str_contains($sinEd->render($svc->sanitizeTree($logoBloque)), 'pg-cempty'), '');

        // --- pie de página --------------------------------------------------
        $arbol = $svc->sanitizeTree(SectionLibrary::footer($svc));
        $html  = $pub->render($arbol);

        $this->check('el pie son DOS secciones (columnas + línea legal)',
            count($arbol) === 2 && ($arbol[1]['type'] ?? '') === 'section',
            count($arbol) . ' bloques');
        $this->check('  la primera va a 4 columnas', str_contains($html, 'pg-cols-4'), '');
        $this->check('  con el logo de la empresa', str_contains($html, '/uploads/logo.png'), '');
        $this->check('  sus dos listas de enlaces en columna',
            substr_count($html, 'pg-btnrow-column') === 2, substr_count($html, 'pg-btnrow-column') . ' listas');
        $this->check('  y las redes de la empresa', str_contains($html, 'facebook.com/acme'), '');

        // --- varios botones en fila ----------------------------------------
        $fila = $svc->newBlock('buttons', [
            'items' => [
                ['label' => 'Uno', 'href' => '#contacto', 'style' => 'primary', 'color' => '#aa0000', 'newTab' => '0'],
                ['label' => 'Dos', 'href' => 'https://x.co', 'style' => 'outline', 'color' => '', 'newTab' => '1'],
            ],
        ]);
        $html = $pub->render($svc->sanitizeTree([$fila]));

        $this->check('la fila pinta los dos botones', substr_count($html, 'pg-btn ') === 2,
            substr_count($html, 'pg-btn ') . ' botones');
        $this->check('  cada uno con su estilo',
            str_contains($html, 'pg-btn-primary') && str_contains($html, 'pg-btn-outline'), '');
        $this->check('  y su color propio', str_contains($html, '--pg-btn-bg:#aa0000'), '');
        $this->check('  el que abre en pestaña nueva lleva rel', str_contains($html, 'rel="noopener noreferrer"'), '');
        $this->check('  y admite anclas de la propia página', str_contains($html, 'href="#contacto"'), '');
        $this->check('por defecto se apila en el teléfono', str_contains($html, 'pg-btnrow-stack'), '');
        $this->check('una fila sin botones no pinta nada',
            $pub->render($svc->sanitizeTree([$svc->newBlock('buttons')])) === '', '');

        // --- QR ------------------------------------------------------------
        $qr   = $svc->newBlock('companyQr', ['target' => 'page', 'title' => 'Escanea']);
        $html = $pub->render([$qr]);
        $this->check('el QR se genera e incrusta como data URI', str_contains($html, 'src="data:image/png;base64,'), substr($html, 0, 160));
        $this->check('el QR no apunta a ningún servicio externo', !preg_match('~src="https?://~', $html), '');
        $this->check('el QR lleva su título', str_contains($html, 'Escanea'), '');

        $qrWeb = $svc->newBlock('companyQr', ['target' => 'website']);
        $this->check('el QR del sitio web también se genera', str_contains($pub->render([$qrWeb]), 'data:image/png'), '');

        $qrWa = $svc->newBlock('companyQr', ['target' => 'whatsapp']);
        $this->check('el QR de WhatsApp también se genera', str_contains($pub->render([$qrWa]), 'data:image/png'), '');

        $qrVacio = $svc->newBlock('companyQr', ['target' => 'custom', 'url' => '']);
        $this->check('sin destino, el QR no pinta nada en público', $pub->render([$qrVacio]) === '', '');

        // --- redes ---------------------------------------------------------
        $soc  = $svc->newBlock('companySocial');
        $html = $pub->render([$soc]);
        $this->check('las redes registradas se pintan', substr_count($html, 'pg-soc-link') === 4,
            substr_count($html, 'pg-soc-link') . ' enlaces (3 redes + web)');
        $this->check('las redes vacías NO se pintan', !str_contains($html, 'tiktok'), '');
        $this->check('cada icono lleva su etiqueta accesible', str_contains($html, 'aria-label="Facebook"'), '');

        $socSinWeb = $svc->newBlock('companySocial', ['showWebsite' => '0']);
        $this->check('se puede excluir el sitio web', substr_count($pub->render([$socSinWeb]), 'pg-soc-link') === 3, '');

        // --- WhatsApp flotante ---------------------------------------------
        $wa   = $svc->newBlock('whatsappFloat', ['message' => 'Hola, ¿me ayudan?', 'label' => 'Escríbenos']);
        $html = $pub->render([$wa]);
        $this->check('usa el WhatsApp de la empresa', str_contains($html, 'https://wa.me/573001234567'), $html);
        $this->check('el mensaje va codificado en la URL', str_contains($html, 'text=Hola%2C%20%C2%BFme%20ayudan%3F'), $html);
        $this->check('lleva su etiqueta', str_contains($html, 'Escríbenos'), '');
        $this->check('en público va flotando', !str_contains($html, 'pg-wa-static'), '');
        $this->check('en el editor NO va flotando', str_contains($ed->render([$wa]), 'pg-wa-static'), '');

        // Prioridad del teléfono: bloque → companies.whatsapp → contact_phone.
        $waProp = $svc->newBlock('whatsappFloat', ['phone' => '573009998877']);
        $this->check('el teléfono del bloque manda sobre el de la empresa',
            str_contains($pub->render([$waProp]), 'wa.me/573009998877'), '');

        $soloFijo = new Renderer([], ['company' => array_merge($co, ['whatsapp' => ''])]);
        $this->check('sin WhatsApp propio cae al teléfono de contacto',
            str_contains($soloFijo->render([$wa]), 'wa.me/576015550100'), '');

        $malNumero = new Renderer([], ['company' => array_merge($co, ['whatsapp' => '123', 'contact_phone' => ''])]);
        $this->check('un número imposible no genera enlace', $malNumero->render([$wa]) === '', $malNumero->render([$wa]));

        // --- sin empresa ---------------------------------------------------
        foreach (BlockRegistry::COMPANY_BLOCKS as $type) {
            $b = $svc->newBlock($type);
            $this->check("sin empresa, «{$type}» no pinta nada en la página publicada", $sin->render([$b]) === '', $sin->render([$b]));
            $this->check("  y avisa en el editor", str_contains($sinEd->render([$b]), 'pg-cempty'), '');
        }

        // --- el catálogo de arranque, vacío a propósito -------------------
        $this->section('8. Plantillas de arranque: retiradas');

        // Eran cinco y se quitaron: el diseño no daba la talla, y una
        // plantilla fea es peor que ninguna porque es la primera impresión de
        // quien entra a montar su web. Lo que se comprueba aquí es que la
        // MAQUINARIA sigue en pie —para volver a poblarlo cuando haya un juego
        // que valga— y que no queda ni rastro de las viejas.
        $lib   = new \Components\Paginas\Libraries\TemplateLibrary();
        $defs  = (new \ReflectionClass($lib))->getMethod('definitions')->invoke($lib);
        $this->check('el catálogo de arranque está vacío', $defs === [], json_encode(array_column($defs, 'code')));
        $this->check('  y sembrar no crea ninguna', $lib->seed() === 0, '');
        $this->check('  la maquinaria sigue existiendo',
            method_exists($lib, 'seed') && method_exists($lib, 'seeded'), '');

        // El árbol que antes traía la plantilla se monta ahora con las
        // secciones prearmadas, que son las que se comprueban en la §9.
    }

    // ------------------------------------------ secciones ya configuradas

    private function sections(): void
    {
        $this->section('9. Secciones prearmadas de la paleta');
        $svc  = new PageService();
        $co   = $this->fakeCompany();
        $pub  = new Renderer([], ['company' => $co, 'pageUrl' => 'https://mi.sitio/pagina/acme/inicio']);
        $todas = \Components\Paginas\Libraries\SectionLibrary::all();

        $this->check('hay secciones declaradas', count($todas) >= 12, count($todas) . ' secciones');

        $codigos = array_column($todas, 'code');
        $this->check('los códigos no se repiten', count($codigos) === count(array_unique($codigos)),
            implode(', ', array_diff_assoc($codigos, array_unique($codigos))));

        $grupos = \Components\Paginas\Libraries\SectionLibrary::GROUPS;
        $malGrupo = '';

        foreach ($todas as $sec) {
            if (!in_array($sec['group'] ?? '', $grupos, true)) {
                $malGrupo = $sec['code'];
                break;
            }
        }
        $this->check('todas pertenecen a un grupo declarado', $malGrupo === '', $malGrupo);

        // Los cuatro grupos que pidió el encargo tienen que tener contenido.
        foreach (['banner', 'cards', 'content', 'footer'] as $g) {
            $n = count(array_filter($todas, static fn ($s) => ($s['group'] ?? '') === $g));
            $this->check("el grupo «{$g}» trae secciones", $n > 0, (string) $n);
        }

        // Lo que de verdad importa: que sobrevivan al saneador (si una usara un
        // bloque mal formado, se perdería al soltarla) y que pinten algo.
        $perdidas = [];
        $vacias   = [];

        foreach ($todas as $sec) {
            $limpia = $svc->sanitizeTree($sec['blocks']);
            if (count($limpia) !== count($sec['blocks'])) {
                $perdidas[] = $sec['code'];

                continue;
            }
            // Y que no se pierda nada DENTRO de las columnas tampoco.
            if ($svc->countBlocks($limpia) !== $svc->countBlocks($sec['blocks'])) {
                $perdidas[] = $sec['code'] . ' (hijos)';

                continue;
            }
            if (trim($pub->render($limpia)) === '') {
                $vacias[] = $sec['code'];
            }
        }
        $this->check('todas sobreviven enteras al saneado', $perdidas === [], implode(', ', $perdidas));
        $this->check('todas producen HTML', $vacias === [], implode(', ', $vacias));

        // Cada sección tiene que ser UNA unidad, no piezas sueltas en la raíz:
        // así se mueve de una vez, se edita con las propiedades de la sección
        // (ancho, fondo, relleno) y en la cascada cuelga de algo en vez de
        // aparecer desperdigada.
        $sueltas = [];
        // El hero gobierna su propio ancho: envolverlo lo encogería. El pie son
        // DOS secciones (columnas + línea legal a todo lo ancho) porque una
        // columna no puede contener otra sección.
        foreach ($todas as $sec) {
            $tipos = array_column($sec['blocks'], 'type');
            if ($tipos === ['hero'] || $tipos === array_fill(0, count($tipos), 'section')) {
                continue;
            }
            $sueltas[] = $sec['code'] . ' (' . implode('+', array_column($sec['blocks'], 'type')) . ')';
        }
        $this->check('cada sección es UNA unidad editable', $sueltas === [], implode(' · ', $sueltas));

        // Y sus elementos siguen siendo editables uno a uno dentro.
        $servicios = $svc->sanitizeTree(
            \Components\Paginas\Libraries\SectionLibrary::get('servicios')['blocks'] ?? []
        );
        $dentro    = [];
        $recoger   = static function (array $t) use (&$recoger, &$dentro): void {
            foreach ($t as $n) {
                $dentro[] = $n['type'];
                if (!empty($n['children'])) {
                    $recoger($n['children']);
                }
            }
        };
        $recoger($servicios);
        $this->check('  con sus elementos dentro, editables uno a uno',
            in_array('section', $dentro, true) && in_array('column', $dentro, true)
            && in_array('heading', $dentro, true) && in_array('cards', $dentro, true),
            implode(', ', $dentro));

        // Ninguna puede llegar con un hueco de «faltan datos» con la empresa
        // completa: sería una sección rota nada más soltarla.
        $conHueco = [];

        foreach ($todas as $sec) {
            if (str_contains($pub->render($svc->sanitizeTree($sec['blocks'])), 'pg-cempty')) {
                $conHueco[] = $sec['code'];
            }
        }
        $this->check('ninguna queda con huecos', $conHueco === [], implode(', ', $conHueco));

        // Comprobaciones concretas de lo pedido: banner, tarjetas de N columnas
        // y pie de página.
        $por = static fn (string $code) => \Components\Paginas\Libraries\SectionLibrary::get($code);

        $cab = $por('cabecera');
        $this->check('«Cabecera con menú» es una sección de elementos',
            ($cab['blocks'][0]['type'] ?? '') === 'section'
            && $this->typesIn($cab['blocks']) === ['section', 'column', 'image', 'column', 'buttons'],
            implode(', ', $this->typesIn($cab['blocks'])));
        $menu = $this->find($cab['blocks'], 'buttons');
        $this->check('  y viene con entradas de menú',
            count($menu['props']['items'] ?? []) >= 3, (string) count($menu['props']['items'] ?? []));
        $this->check('  plegables en el teléfono', ($menu['props']['collapse'] ?? '') === '1', '');
        $this->check('  se pinta con el logo real de la empresa',
            str_contains($pub->render($svc->sanitizeTree($cab['blocks'])), '/uploads/logo.png'), '');

        // Las secciones vienen envueltas en un bloque `section`, así que las
        // tarjetas están DENTRO: hay que buscarlas en el árbol.
        $buscar = static function (array $t, string $tipo) use (&$buscar): ?array {
            foreach ($t as $n) {
                if (($n['type'] ?? '') === $tipo) {
                    return $n;
                }
                if (!empty($n['children'])) {
                    $hallado = $buscar($n['children'], $tipo);
                    if ($hallado) {
                        return $hallado;
                    }
                }
            }

            return null;
        };

        foreach ([2, 3, 4] as $n) {
            $sec  = $por('tarjetas-' . $n);
            $card = $buscar($sec['blocks'], 'cards') ?? [];
            $this->check("«Tarjetas en {$n} columnas» trae {$n} tarjetas",
                count($card['props']['items'] ?? []) === $n, (string) count($card['props']['items'] ?? []));
            $this->check("  y se pinta a {$n} columnas",
                str_contains($pub->render($svc->sanitizeTree($sec['blocks'])), 'pg-cols-' . $n), '');
        }

        $pie  = $por('pie-empresa');
        $html = $pub->render($svc->sanitizeTree($pie['blocks']));
        $this->check('«Pie de página completo» va a 4 columnas', str_contains($html, 'pg-cols-4'), '');
        $this->check('  lleva las redes de la empresa', str_contains($html, 'pg-soc'), '');
        $this->check('  lleva su línea legal', str_contains($html, '©'), $html);
        $this->check('  y sus dos listas de enlaces en columna', substr_count($html, 'pg-btnrow-column') === 2,
            substr_count($html, 'pg-btnrow-column') . ' listas');

        // Una sección se suelta muchas veces: sus ids no pueden repetirse entre
        // dos inserciones (lo hace `reindex`, igual que en el editor).
        $a = $svc->reindex($por('servicios')['blocks']);
        $b = $svc->reindex($por('servicios')['blocks']);
        $this->check('dos inserciones no comparten identificadores',
            array_intersect(array_column($a, 'id'), array_column($b, 'id')) === [], '');
    }

    // -------------------------------- atributos añadidos tras ver Gutenberg

    private function gutenbergParity(): void
    {
        $this->section('10. Atributos contrastados con Gutenberg');
        $svc = new PageService();
        $r   = new Renderer([], ['editor' => true]);

        // --- ancla (block support `anchor`) --------------------------------
        $this->check('todo bloque admite ancla',
            !array_filter(BlockRegistry::types(), static fn ($d) => !in_array('anchor', array_column($d['fields'], 'name'), true)),
            '');

        // El ancla se RESCATA, no se rechaza: quien la escribe quiere enlazarla,
        // y devolver vacío por un carácter de más obligaría a adivinar la regla.
        // Solo se descarta lo que no deja nada aprovechable.
        $casos = [
            'Contáctanos Ya'      => 'contactanos-ya',   // acentos y espacios
            '2malo'               => 'malo',             // un id no puede empezar por dígito
            '<script>x</script>'  => 'script-x-script',  // sin etiquetas: solo queda el slug
            '  Mi Sección  '      => 'mi-seccion',
            '123'                 => '',                 // no queda nada válido
            '###'                 => '',
            'a-' . str_repeat('b', 80) => '',            // más largo de lo admitido
        ];
        $malos = [];

        foreach ($casos as $entrada => $esperado) {
            $out = $svc->sanitizeTree([['type' => 'heading', 'id' => 'a', 'props' => ['text' => 'T', 'anchor' => (string) $entrada]]]);
            $real = (string) ($out[0]['props']['anchor'] ?? '');
            if ($real !== $esperado) {
                $malos[] = "«{$entrada}» → «{$real}» (se esperaba «{$esperado}»)";
            }
        }
        $this->check('el ancla se normaliza y se rescata', $malos === [], implode(' · ', $malos));

        // Pase lo que pase, la salida siempre es un id válido: nada puede
        // escaparse al atributo.
        $sucias = ['" onload="x', "'; DROP--", '../../etc', 'ñ', str_repeat('x', 200)];
        $rotas  = [];

        foreach ($sucias as $s) {
            $out  = $svc->sanitizeTree([['type' => 'heading', 'id' => 'a', 'props' => ['text' => 'T', 'anchor' => $s]]]);
            $real = (string) ($out[0]['props']['anchor'] ?? '');
            if ($real !== '' && !preg_match('/^[a-z][a-z0-9-]{0,60}$/', $real)) {
                $rotas[] = $real;
            }
        }
        $this->check('ninguna entrada produce un id inválido', $rotas === [], implode(', ', $rotas));

        $html = $r->render([$svc->newBlock('heading', ['text' => 'Servicios', 'anchor' => 'servicios'])]);
        $this->check('el ancla llega al HTML como id', str_contains($html, 'id="servicios"'), $html);
        $this->check('  y va en la etiqueta raíz', preg_match('/^<h\d id="servicios"/', $html) === 1, $html);

        $html = $r->render([$svc->newBlock('heading', ['text' => 'X'])]);
        $this->check('sin ancla no se inventa ningún id', !str_contains($html, ' id='), $html);

        // El hueco que destapó el análisis: los botones de las plantillas
        // apuntaban a #contacto y no existía nada con ese id.
        $tpls = (new \ReflectionClass(\Components\Paginas\Libraries\TemplateLibrary::class))
            ->getMethod('definitions')->invoke(new \Components\Paginas\Libraries\TemplateLibrary());

        foreach ($tpls as $tpl) {
            $destinos = [];
            $anclas   = [];
            $recoger  = static function (array $t) use (&$recoger, &$destinos, &$anclas): void {
                foreach ($t as $n) {
                    $p = $n['props'] ?? [];

                    foreach (['href', 'buttonHref'] as $k) {
                        if (str_starts_with((string) ($p[$k] ?? ''), '#')) {
                            $destinos[] = ltrim((string) $p[$k], '#');
                        }
                    }
                    if (!empty($p['anchor'])) {
                        $anclas[] = $p['anchor'];
                    }
                    if (!empty($n['children'])) {
                        $recoger($n['children']);
                    }
                }
            };
            $recoger($tpl['blocks']);

            $rotos = array_values(array_diff(array_unique($destinos), $anclas));
            $this->check("la plantilla «{$tpl['code']}» no deja enlaces internos rotos", $rotos === [], implode(', ', $rotos));
        }

        // --- ancho de columna (`core/column` width) ------------------------
        // El ancho va **en por ciento** sobre una fila flexible. Era una
        // rejilla de 12, y ahí estaba el fallo: sus 11 separaciones se sumaban
        // a las doce pistas, y en un ancho apretado el contenido se salía de
        // su celda amontonándose a la izquierda.
        $reparto = static function ($a, $b) use ($svc, $r) {
            $sec = $svc->newBlock('section', ['columns' => '2']);
            $sec['children'][0]['props']['width'] = $a;
            $sec['children'][1]['props']['width'] = $b;

            return $r->render($svc->sanitizeTree([$sec]));
        };

        $html = $reparto('75%', '25%');
        $this->check('un reparto 75 % + 25 % pasa a fila flexible', str_contains($html, 'pg-cols-free'), $html);
        $this->check('  la primera columna pide su parte', str_contains($html, '--pg-cw:75%'), $html);
        $this->check('  y la segunda la suya', str_contains($html, '--pg-cw:25%'), '');
        $this->check('  el número a secas también vale',
            str_contains($reparto('60', '40'), '--pg-cw:60%'), '');
        $this->check('  y con espacio antes del signo',
            str_contains($reparto('60 %', '40 %'), '--pg-cw:60%'), '');

        // Las seis palabras de la rejilla vieja siguen guardadas en páginas
        // publicadas: se traducen a su porcentaje en vez de perderse.
        foreach (['quarter' => '25%', 'third' => '33.33%', 'half' => '50%',
            'twothirds' => '66.66%', 'threequarters' => '75%'] as $palabra => $pct) {
            $this->check("  «{$palabra}» heredado vale {$pct}",
                str_contains($reparto($palabra, 'auto'), '--pg-cw:' . $pct), '');
        }

        foreach (['abc', '0%', '120%', '50px', '50%;color:red'] as $malo) {
            $this->check('  se rechaza «' . $malo . '»',
                !str_contains($reparto($malo, 'auto'), '--pg-cw'), '');
        }

        $iguales = $r->render($svc->sanitizeTree([$svc->newBlock('section', ['columns' => '3'])]));
        $this->check('sin anchos a medida se conserva el reparto simple',
            str_contains($iguales, 'pg-cols-3') && !str_contains($iguales, 'pg-cols-free'), '');

        // --- no apilar en móvil (`isStackedOnMobile`) ----------------------
        $noStack = $r->render($svc->sanitizeTree([$svc->newBlock('section', ['columns' => '4', 'stackOnMobile' => '0'])]));
        $this->check('«no apilar» marca la sección', str_contains($noStack, 'pg-nostack'), '');
        $this->check('por defecto sí se apila',
            !str_contains($r->render($svc->sanitizeTree([$svc->newBlock('section')])), 'pg-nostack'), '');

        // --- sección con imagen de fondo (`core/cover`) --------------------
        $cover = $r->render($svc->sanitizeTree([
            $svc->newBlock('section', ['backgroundImage' => '/uploads/f.jpg', 'overlay' => 'dark']),
        ]));
        $this->check('la sección admite imagen de fondo', str_contains($cover, 'pg-sec-img'), '');
        $this->check('  con su velo', str_contains($cover, 'pg-sec-scrim') && str_contains($cover, 'pg-ov-dark'), '');
        $this->check('  y la imagen en el style', str_contains($cover, 'background-image:url('), '');

        // --- recorte de imagen (`aspectRatio` / `imageCrop`) ---------------
        $img = $r->render($svc->sanitizeTree([
            $svc->newBlock('image', ['src' => '/uploads/a.png', 'ratio' => '1-1']),
        ]));
        $this->check('la imagen se puede recortar a una proporción', str_contains($img, 'pg-ar-1-1'), $img);

        $sinRecorte = $r->render($svc->sanitizeTree([$svc->newBlock('image', ['src' => '/uploads/a.png', 'ratio' => 'auto'])]));
        $this->check('  y «original» no recorta', !str_contains($sinRecorte, 'pg-ar'), $sinRecorte);

        $gal = $r->render($svc->sanitizeTree([
            $svc->newBlock('gallery', ['ratio' => '16-9', 'items' => [['src' => '/uploads/a.png']]]),
        ]));
        $this->check('la galería también recorta', str_contains($gal, 'pg-ar-16-9'), '');

        // --- párrafo: color y capitular (`core/paragraph`) -----------------
        $txt = $r->render($svc->sanitizeTree([
            $svc->newBlock('text', ['html' => '<p>Hola</p>', 'color' => '#334455', 'dropCap' => '1']),
        ]));
        $this->check('el párrafo admite color propio', str_contains($txt, 'color:#334455'), $txt);
        $this->check('  y letra capital', str_contains($txt, 'pg-dropcap'), '');

        // --- botón: color y ancho completo (`core/button`) -----------------
        $btn = $r->render($svc->sanitizeTree([
            $svc->newBlock('button', ['label' => 'Ir', 'href' => 'https://x.co', 'color' => '#aa0000', 'fullWidth' => '1']),
        ]));
        // El color viaja como VARIABLE, no como propiedad: así lo puede usar
        // también la regla `:hover`, que no puede leer un `style` en línea.
        $this->check('el botón admite color propio', str_contains($btn, '--pg-btn-bg:#aa0000'), $btn);
        $this->check('  y ancho completo', str_contains($btn, 'pg-btn-block'), '');

        $btnBorde = $r->render($svc->sanitizeTree([
            $svc->newBlock('button', ['label' => 'Ir', 'href' => 'https://x.co', 'style' => 'outline', 'color' => '#aa0000']),
        ]));
        $hojaBtn = view('Components\Paginas\Views\public\_styles', ['settings' => []]);
        $this->check('  el color se emite igual en los tres estilos',
            str_contains($btnBorde, '--pg-btn-bg:#aa0000'), $btnBorde);
        $this->check('  y es la HOJA la que decide dónde va (borde en «con borde»)',
            str_contains($hojaBtn, '.pg-btn-outline { background: transparent; color: var(--pg-btn-bg); border-color: var(--pg-btn-bg); }'),
            '');

        // --- los dos anchos de la página (`contentSize` / `wideSize`) ------
        $this->section('11. Anchos de la página y de la sección');

        $anchos = BlockRegistry::optionValues('section', 'width');

        foreach (['boxed', 'wide', 'full'] as $w) {
            $this->check("la sección puede ir a «{$w}»", in_array($w, $anchos, true), implode(', ', $anchos));
        }
        $this->check('sigue existiendo el estrecho de lectura', in_array('narrow', $anchos, true), '');

        foreach (['boxed', 'wide', 'full', 'narrow'] as $w) {
            $html = $r->render($svc->sanitizeTree([$svc->newBlock('section', ['width' => $w])]));
            $this->check("  «{$w}» llega al HTML", str_contains($html, 'pg-w-' . $w), $html);
        }

        // Las claves son las de antes: una página guardada no puede cambiar de
        // sitio porque hayamos añadido una opción.
        $viejo = $svc->sanitizeTree([['type' => 'section', 'id' => 's', 'props' => ['width' => 'boxed'], 'children' => []]]);
        $this->check('una página anterior conserva su ancho', ($viejo[0]['props']['width'] ?? '') === 'boxed',
            (string) ($viejo[0]['props']['width'] ?? ''));

        // La hoja de estilos tiene que declarar las dos medidas y usarlas.
        $hoja = view('Components\Paginas\Views\public\_styles', [
            'settings' => ['contentWidth' => '900', 'wideWidth' => '1400'],
        ]);
        $this->check('la hoja declara el ancho de contenido', str_contains($hoja, '--pg-content: 900px'), '');
        $this->check('la hoja declara el ancho amplio', str_contains($hoja, '--pg-wide: 1400px'), '');
        $this->check('«contenido» usa su medida', str_contains($hoja, '.pg-w-boxed  > .pg-wrap { max-width: var(--pg-content); }'), '');
        $this->check('«amplio» usa la suya', str_contains($hoja, '.pg-w-wide   > .pg-wrap { max-width: var(--pg-wide); }'), '');
        $this->check('«a todo lo ancho» no tiene tope', str_contains($hoja, '.pg-w-full   > .pg-wrap { max-width: none; }'), '');

        // Topes: estos valores acaban en un `max-width`.
        $extremo = view('Components\Paginas\Views\public\_styles', [
            'settings' => ['contentWidth' => '99999', 'wideWidth' => '10'],
        ]);
        $this->check('un ancho absurdo se acota', str_contains($extremo, '--pg-content: 1600px'), '');
        $this->check('el amplio nunca queda por debajo del de contenido',
            str_contains($extremo, '--pg-wide: 1600px'), '');

        // Sin medidas nuevas, se derivan del preajuste anterior.
        $heredado = view('Components\Paginas\Views\public\_styles', ['settings' => ['width' => 'narrow']]);
        $this->check('una página sin medidas hereda su preajuste', str_contains($heredado, '--pg-content: 720px'), '');

        // --- espaciado por bloque (`spacing` support) ----------------------
        $this->section('12. Espaciado por bloque');

        // Todo bloque lo admite… salvo el relleno de la sección, que ya tenía
        // el suyo con otro significado y no puede duplicarse.
        $sinEspacio = [];

        foreach (BlockRegistry::types() as $code => $def) {
            $nombres = array_column($def['fields'], 'name');

            foreach (['marginTop', 'marginBottom', 'padding'] as $campo) {
                if (!in_array($campo, $nombres, true)) {
                    $sinEspacio[] = "{$code}.{$campo}";
                }
            }
        }
        $this->check('todo bloque admite espaciado', $sinEspacio === [], implode(', ', $sinEspacio));

        $padSec = array_filter(BlockRegistry::types()['section']['fields'], static fn ($f) => $f['name'] === 'padding');
        $this->check('la sección conserva UN solo relleno (el suyo)', count($padSec) === 1, count($padSec) . ' campos «padding»');

        $bloque = $svc->sanitizeTree([['type' => 'heading', 'id' => 'e', 'props' => [
            'text' => 'T', 'marginTop' => 'lg', 'marginBottom' => 'none', 'padding' => 'md',
        ]]]);
        $this->check('el espaciado sobrevive al saneado',
            ($bloque[0]['props']['marginTop'] ?? '') === 'lg'
            && ($bloque[0]['props']['marginBottom'] ?? '') === 'none'
            && ($bloque[0]['props']['padding'] ?? '') === 'md',
            json_encode($bloque[0]['props'] ?? []));

        $html = $r->render($bloque);
        $this->check('llega al HTML como clases', str_contains($html, 'pg-mt-lg')
            && str_contains($html, 'pg-mb-none') && str_contains($html, 'pg-p-md'), $html);
        $this->check('  sin perder las clases propias del bloque', str_contains($html, 'pg-h pg-h2'), $html);

        // Vacío = «lo que traiga el bloque»: si no, abrir el inspector de una
        // página ya hecha la recompondría entera.
        $limpio = $r->render($svc->sanitizeTree([$svc->newBlock('heading', ['text' => 'T'])]));
        $this->check('por defecto NO se emite ninguna clase de espaciado',
            !preg_match('/pg-(mt|mb|p)-/', $limpio), $limpio);

        $invento = $svc->sanitizeTree([['type' => 'heading', 'id' => 'e', 'props' => ['text' => 'T', 'marginTop' => 'gigante']]]);
        $this->check('un valor inventado cae a «por defecto»', ($invento[0]['props']['marginTop'] ?? 'x') === '',
            var_export($invento[0]['props']['marginTop'] ?? null, true));

        // El orden en la hoja importa: las clases de espaciado tienen la misma
        // especificidad que las del bloque, así que deben ir DESPUÉS.
        $hoja = view('Components\Paginas\Views\public\_styles', ['settings' => []]);
        $this->check('las clases de espaciado van al final de la hoja',
            strpos($hoja, '.pg-mt-lg') > strpos($hoja, '.pg-text {'),
            'espaciado en ' . strpos($hoja, '.pg-mt-lg') . ', bloques en ' . strpos($hoja, '.pg-text {'));

        // El ancla y el espaciado conviven en la misma etiqueta raíz.
        $ambos = $r->render($svc->sanitizeTree([['type' => 'heading', 'id' => 'e', 'props' => [
            'text' => 'T', 'anchor' => 'seccion', 'marginTop' => 'xl',
        ]]]));
        $this->check('ancla y espaciado conviven en la raíz',
            str_contains($ambos, 'id="seccion"') && str_contains($ambos, 'pg-mt-xl'), $ambos);

        // --- redes con etiqueta (`showLabels`) -----------------------------
        $rc  = new Renderer([], ['company' => $this->fakeCompany()]);
        $soc = $rc->render($svc->sanitizeTree([$svc->newBlock('companySocial', ['showLabels' => '1'])]));
        $this->check('las redes pueden llevar su nombre', str_contains($soc, 'pg-soc-name'), '');
        $this->check('  y cambian a píldora', str_contains($soc, 'pg-soc-labeled'), '');
        $sinNombre = $rc->render($svc->sanitizeTree([$svc->newBlock('companySocial')]));
        $this->check('  por defecto siguen siendo círculos', !str_contains($sinNombre, 'pg-soc-labeled'), '');

        // --- los iconos del lienzo son los MISMOS que se publican --------
        // El editor llevaba su propia copia dibujada a mano: el de WhatsApp
        // era solo el bocadillo, sin el auricular dentro, y las redes eran la
        // inicial en negrita. Ahora se le pasan los de verdad.
        $iconos = Renderer::iconSet();
        $this->check('el juego de iconos es accesible para el editor',
            isset($iconos['whatsapp']) && str_contains($iconos['whatsapp'], '<svg'), '');
        // El glifo de WhatsApp son DOS trazos: el auricular y el bocadillo.
        // Con uno solo queda un bocadillo vacío, que es lo que se veía.
        $this->check('  y el de WhatsApp trae el auricular, no solo el bocadillo',
            substr_count($iconos['whatsapp'] ?? '', '<path') >= 1
            && strlen($iconos['whatsapp'] ?? '') > 900, strlen($iconos['whatsapp'] ?? '') . ' bytes');
        $vistaEd = (string) file_get_contents(dirname(__DIR__) . '/Views/editor.php');
        $this->check('  la vista los inyecta en el editor',
            str_contains($vistaEd, 'id="pagIcons"') && str_contains($vistaEd, 'Renderer::iconSet()'), '');
        $jsEd = (string) file_get_contents(dirname(__DIR__) . '/assets/js/editor.js');
        $this->check('  y el lienzo los usa',
            str_contains($jsEd, 'ICONS.whatsapp') && str_contains($jsEd, "readJson('pagIcons'"), '');
        $this->check('  sin llevar su propia copia dibujada a mano',
            !str_contains($jsEd, 'viewBox="0 0 24 24" fill="currentColor" width="28"'), '');

        // --- flotante de WhatsApp: círculo o píldora ---------------------
        $rc2   = new Renderer([], ['company' => $this->fakeCompany()]);
        $hoja2 = view('Components\Paginas\Views\public\_styles', ['settings' => []]);
        $redondo = $rc2->render($svc->sanitizeTree([$svc->newBlock('whatsappFloat')]));
        $this->check('sin etiqueta el flotante es un círculo', str_contains($redondo, 'pg-wa-round'), $redondo);
        $this->check('  y por defecto NO fija colores (los pone la hoja)',
            !str_contains($redondo, '--pg-wa-bg') && !str_contains($redondo, '--pg-wa-fg'), $redondo);

        // Fondo y tinta propios, como variables: la sombra y el `:hover` los
        // necesitan, y una regla CSS no lee un `style` en línea.
        $color = $rc2->render($svc->sanitizeTree([$svc->newBlock('whatsappFloat', ['background' => '#111111'])]));
        $this->check('admite color de fondo propio', str_contains($color, '--pg-wa-bg:#111111'), $color);
        $this->check('  con tinta legible calculada', str_contains($color, '--pg-wa-fg:#ffffff'), $color);
        $propio = $rc2->render($svc->sanitizeTree([$svc->newBlock('whatsappFloat', [
            'background' => '#111111', 'textColor' => '#ffcc00',
        ])]));
        $this->check('  y una tinta pedida manda sobre la calculada',
            str_contains($propio, '--pg-wa-fg:#ffcc00') && substr_count($propio, '--pg-wa-fg') === 1, $propio);
        $this->check('  la hoja los lee con su valor por defecto',
            str_contains($hoja2 ?? '', 'var(--pg-wa-bg, #25d366)'), '');
        $this->check('  y el lienzo los dibuja igual',
            str_contains($jsEd, '--pg-wa-bg') && str_contains($jsEd, '--pg-wa-fg'), '');

        $conTexto = $rc2->render($svc->sanitizeTree([$svc->newBlock('whatsappFloat', ['label' => 'Escríbenos'])]));
        $this->check('  y con etiqueta, una píldora', !str_contains($conTexto, 'pg-wa-round'), $conTexto);
        $this->check('  la hoja declara el círculo',
            str_contains($hoja2, '.pg-wa-round') && str_contains($hoja2, 'border-radius: 50%'), '');
        $this->check('  y el lienzo lo dibuja igual',
            str_contains((string) file_get_contents(dirname(__DIR__) . '/assets/js/editor.js'), 'pg-wa-round'), '');
        // El icono va EN LÍNEA: una imagen de otro dominio rompería la
        // autosuficiencia de la página publicada (§7.3) y dejaría el botón a
        // merced de que ese dominio siga en pie.
        $this->check('el icono del flotante va en línea, sin pedir nada fuera',
            str_contains($redondo, '<svg') && !str_contains($redondo, '<img'), $redondo);

        $this->colorAndLists($svc, $r);
        $this->textColorEverywhere($svc, $r);
        $this->containersAndButtons($svc, $r);
        $this->headerFooterFromElements($svc, $r);
    }

    /**
     * Color con transparencia (secciones y columnas) y viñetas de la lista.
     *
     * Lo específico que la cobertura genérica no ve: aquella solo comprueba que
     * cambiar una propiedad cambia el HTML, no que lo cambie BIEN.
     */
    private function colorAndLists(PageService $svc, Renderer $r): void
    {
        $this->section('14. Color con transparencia, fondo de columna y viñetas');

        // --- transparencia -------------------------------------------------
        // El hex de 8 dígitos es la forma corta del canal alfa; el de 4 su
        // abreviatura. Si el saneador solo aceptara 3 y 6, el control de
        // opacidad del inspector escribiría un valor que se tira en silencio.
        foreach (['#0a7', '#0a7f', '#00aa77', '#00aa7780'] as $hex) {
            $t = $svc->sanitizeTree([['type' => 'section', 'id' => 's',
                'props' => ['background' => $hex], 'children' => []]]);
            $this->check("se admite el color {$hex}", ($t[0]['props']['background'] ?? '') === $hex,
                var_export($t[0]['props']['background'] ?? null, true));
        }

        foreach (['#12345', 'rgba(0,0,0,.5)', 'red; x:y', '#00aa77801'] as $malo) {
            $t = $svc->sanitizeTree([['type' => 'section', 'id' => 's',
                'props' => ['background' => $malo], 'children' => []]]);
            $this->check('  se rechaza «' . $malo . '»', ($t[0]['props']['background'] ?? '') === '',
                var_export($t[0]['props']['background'] ?? null, true));
        }

        $conAlfa = $r->render($svc->sanitizeTree([['type' => 'section', 'id' => 's',
            'props' => ['background' => '#00aa7780', 'columns' => '1'],
            'children' => [['type' => 'column', 'id' => 'c', 'props' => [],
                'children' => [$svc->newBlock('heading', ['text' => 'T'])]]],
        ]]));
        $this->check('la transparencia llega al HTML', str_contains($conAlfa, '#00aa7780'), $conAlfa);

        // --- fondo de columna ----------------------------------------------
        $col = static fn (array $props): array => [[
            'type' => 'section', 'id' => 's', 'props' => ['columns' => '2'],
            'children' => [['type' => 'column', 'id' => 'c', 'props' => $props,
                'children' => [$svc->newBlock('heading', ['text' => 'T'])]]],
        ]];

        $conFondo = $r->render($svc->sanitizeTree($col(['background' => '#eef2f7', 'radius' => 'lg'])));
        $this->check('la columna pinta su fondo', str_contains($conFondo, 'background-color:#eef2f7'), $conFondo);
        $this->check('  con relleno y redondeo', str_contains($conFondo, 'pg-col-bg pg-r-lg'), $conFondo);

        $sinFondo = $r->render($svc->sanitizeTree($col(['radius' => 'lg'])));
        $this->check('sin color no se añade caja', !str_contains($sinFondo, 'pg-col-bg'), $sinFondo);

        // El relleno de la columna con fondo debe declararse DESPUÉS de `.pg-col`
        // (misma especificidad), o el texto queda pegado al borde del color.
        $hoja = view('Components\Paginas\Views\public\_styles', ['settings' => []]);
        $this->check('.pg-col-bg va después de .pg-col en la hoja',
            strpos($hoja, '.pg-col-bg') > strpos($hoja, '.pg-col {'), '');

        // --- viñetas de la lista -------------------------------------------
        $estilos = BlockRegistry::optionValues('list', 'style');
        $this->check('la lista ofrece las 7 viñetas', count($estilos) === 7, implode(', ', $estilos));

        foreach ($estilos as $e) {
            $html = $r->render($svc->sanitizeTree([$svc->newBlock('list', [
                'style' => $e, 'items' => [['text' => 'Uno', 'href' => '']],
            ])]));
            $this->check("  «{$e}» pinta su clase", str_contains($html, 'pg-list-' . $e), $html);
            $this->check("  y la hoja la declara", str_contains($hoja, '.pg-list-' . $e), '');
            $this->check('  con la etiqueta correcta',
                str_starts_with($html, $e === 'number' ? '<ol' : '<ul'), $html);
        }

        // La hoja se incrusta DENTRO del editor, que carga Tailwind, y su reset
        // pone `ul { list-style: none }`: sin declararlo explícito, las viñetas
        // desaparecían al editar aunque la página publicada las mostrara.
        $this->check('las viñetas se declaran explícitas (Tailwind las quita)',
            str_contains($hoja, '.pg-list-bullet { list-style: disc')
            && str_contains($hoja, '.pg-list-number { list-style: decimal'), '');

        // --- enlace por elemento -------------------------------------------
        $enlace = $r->render($svc->sanitizeTree([$svc->newBlock('list', ['items' => [
            ['text' => 'Interno', 'href' => '#contacto'],
            ['text' => 'Externo', 'href' => 'https://ejemplo.co'],
            ['text' => 'Roto', 'href' => 'javascript:alert(1)'],
            ['text' => 'Suelto', 'href' => ''],
        ]])]));
        $this->check('un elemento con URL se vuelve enlace', str_contains($enlace, '<a href="#contacto">Interno</a>'), $enlace);
        $this->check('  admite destinos externos', str_contains($enlace, 'href="https://ejemplo.co"'), $enlace);
        $this->check('  descarta un javascript:', !str_contains($enlace, 'javascript'), $enlace);
        $this->check('  y sin URL sigue siendo texto', str_contains($enlace, '<li>Suelto</li>'), $enlace);

        // --- lo que el editor debe saber dibujar ---------------------------
        $js = file_get_contents(__DIR__ . '/../assets/js/editor.js');
        $this->check('el lienzo dibuja el fondo de la columna', str_contains($js, 'pg-col-bg pg-r-'), '');
        $this->check('  y el enlace de un elemento de lista', str_contains($js, "i.href ? '<a>'"), '');
        $this->check('el inspector tiene control de color con opacidad',
            str_contains($js, 'function colorControl') && str_contains($js, 'opacity'), '');

        // --- código de la página ------------------------------------
        // La página no es HTML guardado sino un árbol de bloques: eso es lo
        // que la hace copiable de una a otra, y este cuadro es lo único que
        // hacía falta para aprovecharlo.
        $v = file_get_contents(__DIR__ . '/../Views/editor.php');
        foreach (['pagCode', 'pagCodeBox', 'pagCodeText', 'pagCodeCopy', 'pagCodeApply'] as $id) {
            $this->check("la vista trae «{$id}»", str_contains($v, 'id="' . $id . '"'), '');
        }
        $this->check('  y la vista previa sigue estando', str_contains($v, 'id="pagPreview"'), '');
        $this->check('el cuadro no tiene texto incrustado',
            !preg_match('/>\s*(Código|Copiar|Aplicar)\s*</u', $v), '');

        $js = (string) file_get_contents(__DIR__ . '/../assets/js/editor.js');
        $this->check('el código que se copia lleva la marca del componente',
            str_contains($js, 'pag: 1'), '');
        $this->check('  y se rechaza el que no la lleve',
            str_contains($js, 'datos.pag !== 1'), '');
        $this->check('  el título y la dirección NO viajan',
            !preg_match('/pag: 1,\s*title:/', $js), '');
        $this->check('los bloques pegados reciben ids nuevos',
            preg_match('/tree\.push\(cloneBlock\(/', $js) === 1, '');
        // `navigator.clipboard` no existe fuera de un origen seguro, y el
        // editor se usa por IP en la red local: sin respaldo, copiar fallaría
        // justo en la mitad de los casos.
        $this->check('copiar tiene respaldo sin portapapeles',
            str_contains($js, 'navigator.clipboard') && str_contains($js, 'execCommand'), '');
        $this->check('  y avisa antes de reemplazar lo que ya hay',
            str_contains($js, 'codeConfirm'), '');

        $vista = file_get_contents(__DIR__ . '/../Views/editor.php');
        $this->check('la barra ofrece las tres vistas por dispositivo',
            substr_count($vista, 'class="btn btn-ghost btn-sm pag-device"') === 3, '');
        $this->check('  abriendo la página YA renderizada (no encogiendo el lienzo)',
            str_contains($js, 'previsualizar/') || str_contains($js, 'pagPreview'), '');
    }


    /**
     * El color del texto, elemento por elemento.
     *
     * La cobertura genérica (§13) solo comprueba que cambiar una propiedad
     * cambia el HTML, y la del lienzo solo que su nombre se menciona en
     * `editor.js`. Ninguna de las dos habría cazado lo que se arregla aquí: que
     * de los 21 elementos **solo cinco** tenían color de texto, así que en una
     * cita, una lista, unas tarjetas o el contacto de la empresa no había forma
     * de aclarar el texto sobre un fondo oscuro — y como la opacidad vive
     * dentro del control de color, donde no había color tampoco había opacidad.
     */
    private function textColorEverywhere(PageService $svc, Renderer $r): void
    {
        $this->section('15. Color del texto en TODOS los elementos');

        $sinCampo = [];
        $sinEfecto = [];
        $sinAlfa   = [];
        $n         = 0;

        foreach (BlockRegistry::types() as $type => $def) {
            if (in_array($type, BlockRegistry::COMMON_SKIP['textColor'] ?? [], true)) {
                continue;
            }
            $n++;

            if ((BlockRegistry::field($type, 'textColor')['type'] ?? '') !== 'color') {
                $sinCampo[] = $type;

                continue;
            }

            $base = $this->baseProps($type);

            // Opaco y con alfa: el de 8 dígitos es el que produce el deslizador
            // de opacidad del inspector, y es el que se caía en silencio.
            foreach (['#ff0044' => &$sinEfecto, '#ff004480' => &$sinAlfa] as $hex => &$fallos) {
                $html = $r->render($svc->sanitizeTree(
                    $this->inContext($type, array_merge(BlockRegistry::defaults($type), $base, ['textColor' => $hex]))
                ));
                if (!str_contains($html, 'color:' . $hex)) {
                    $fallos[] = $type;
                }
            }
            unset($fallos);
        }

        $this->check("los {$n} elementos ofrecen color de texto", $sinCampo === [], implode(', ', $sinCampo));
        $this->check('  y lo pintan de verdad', $sinEfecto === [], implode(', ', $sinEfecto));
        $this->check('  también con opacidad (hex de 8)', $sinAlfa === [], implode(', ', $sinAlfa));

        // Los tres exentos: ofrecer un control que no hace nada es peor que no
        // tenerlo. El separador ya tiene el color de SU línea.
        foreach (BlockRegistry::COMMON_SKIP['textColor'] as $type) {
            $this->check("«{$type}» no lo ofrece, a propósito",
                BlockRegistry::field($type, 'textColor') === null,
                var_export(BlockRegistry::field($type, 'textColor'), true));
        }
        $this->check('  y el separador conserva el color de su línea',
            (BlockRegistry::field('divider', 'color')['type'] ?? '') === 'color', '');

        // --- cascada -------------------------------------------------------
        // Es lo que hace útil el campo: una banda oscura se aclara entera desde
        // la sección, sin ir bloque a bloque.
        $arbol = static fn (array $secProps, array $colProps, array $hijoProps): array => [[
            'type' => 'section', 'id' => 's', 'props' => array_merge(['columns' => '1'], $secProps),
            'children' => [['type' => 'column', 'id' => 'c', 'props' => $colProps,
                'children' => [['type' => 'heading', 'id' => 'h',
                    'props' => array_merge(['text' => 'Título'], $hijoProps), 'children' => []]]]],
        ]];

        $html = $r->render($svc->sanitizeTree($arbol(['textColor' => '#ffffff'], [], [])));
        $this->check('la sección tiñe lo que lleva dentro', str_contains($html, 'color:#ffffff'), $html);

        $html = $r->render($svc->sanitizeTree($arbol([], ['textColor' => '#00ff00'], [])));
        $this->check('  y la columna también', str_contains($html, 'color:#00ff00'), $html);

        // El hijo manda sobre la sección: su `color` va en SU etiqueta, que se
        // pinta después. Sin esto, teñir la banda impediría destacar un título.
        $html = $r->render($svc->sanitizeTree($arbol(['textColor' => '#ffffff'], [], ['color' => '#ff0000'])));
        $this->check('el hijo con color propio gana a la sección',
            str_contains($html, 'color:#ffffff') && str_contains($html, '#ff0000'), $html);

        // --- no se cuela lo que no es un color ------------------------------
        foreach (['red; background:url(x)', 'rgba(0,0,0,.5)', '#12345', 'javascript:1'] as $malo) {
            $t = $svc->sanitizeTree($this->inContext('quote', ['text' => 'Hola', 'textColor' => $malo]));
            $this->check('se rechaza «' . $malo . '»', ($t[0]['props']['textColor'] ?? '') === '',
                var_export($t[0]['props']['textColor'] ?? null, true));
        }

        // --- el lienzo del editor -------------------------------------------
        // El chequeo genérico solo mira que el NOMBRE aparezca en `editor.js`, y
        // `textColor` ya aparecía (lo tenía el botón): habría pasado sin que el
        // lienzo pintase nada. Aquí se comprueba que lo aplica en el sitio
        // común, que es el que sirve para los 18.
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/editor.js');
        $this->check('el lienzo aplica la tinta en `withSpacing` (los 18 a la vez)',
            (bool) preg_match('~function withSpacing\([^)]*\)\s*\{.*?hexA\(p\.textColor~s', $js), '');
        $this->check('  y con la MISMA regla de hex que el servidor (acepta el alfa)',
            (bool) preg_match('~function withSpacing\([^)]*\)\s*\{.*?hexA\(p\.textColor.*?color:~s', $js), '');
    }

    /**
     * Lo que la cobertura genérica no ve de la SECCIÓN y la COLUMNA.
     *
     * Las dos son contenedores y por eso van por un camino distinto al de los
     * elementos sueltos —tanto en el servidor como en el lienzo—, y ahí es donde
     * se les perdían los ajustes comunes: el espaciado no hacía nada, y el
     * relleno de la sección se aplicaba DOS veces (el suyo y el común).
     */
    private function containersAndButtons(PageService $svc, Renderer $r): void
    {
        $this->section('16. Espaciado de contenedores, alineación vertical y hover');

        $arbol = static fn (array $sec, array $col): array => [[
            'type' => 'section', 'id' => 's', 'props' => array_merge(['columns' => '1'], $sec),
            'children' => [['type' => 'column', 'id' => 'c', 'props' => $col,
                'children' => [['type' => 'heading', 'id' => 'h', 'props' => ['text' => 'T'], 'children' => []]]]],
        ]];

        // --- espaciado ------------------------------------------------------
        $html = $r->render($svc->sanitizeTree($arbol(
            ['marginTop' => 'xl', 'marginBottom' => 'lg'],
            ['marginTop' => 'md', 'marginBottom' => 'sm', 'padding' => 'lg']
        )));
        $this->check('la sección lleva sus márgenes', str_contains($html, 'pg-mt-xl') && str_contains($html, 'pg-mb-lg'), $html);
        $this->check('  y la columna los suyos', str_contains($html, 'pg-mt-md') && str_contains($html, 'pg-mb-sm'), $html);
        $this->check('  y su relleno', str_contains($html, 'pg-p-lg'), $html);

        // El `padding` de la sección es SUYO (el aire de la banda): no puede
        // salir además como relleno de bloque, o se aplicaría dos veces y por
        // los cuatro lados.
        $html = $r->render($svc->sanitizeTree($arbol(['padding' => 'xl'], [])));
        $this->check('el relleno de la sección sale UNA vez', str_contains($html, 'pg-pad-xl'), $html);
        $this->check('  y no se duplica como relleno de bloque', !str_contains($html, 'pg-p-xl'), $html);

        // Con anchos a medida la sección pinta sus columnas por otra rama; el
        // espaciado tiene que llegar igual.
        $html = $r->render($svc->sanitizeTree($arbol([], ['width' => '30%', 'marginTop' => 'xl'])));
        $this->check('la columna con ancho a medida también', str_contains($html, 'pg-mt-xl'), $html);

        // --- alineación vertical del contenido de la columna ----------------
        foreach (['center', 'bottom', 'between'] as $v) {
            $html = $r->render($svc->sanitizeTree($arbol([], ['vAlign' => $v])));
            $this->check("la columna se alinea «{$v}»", str_contains($html, 'pg-cv-' . $v), $html);
        }
        $html = $r->render($svc->sanitizeTree($arbol([], ['vAlign' => 'top'])));
        $this->check('«arriba» no emite clase (es el reparto de siempre)', !str_contains($html, 'pg-cv-'), $html);

        $hoja = view('Components\Paginas\Views\public\_styles', ['settings' => []]);
        $this->check('la hoja alinea el contenido de la columna',
            str_contains($hoja, '.pg-cv-center') && str_contains($hoja, '.pg-cv-bottom')
            && str_contains($hoja, '.pg-cv-between'), '');

        // --- color al pasar por encima, en la FILA de botones ---------------
        // Lo tenía el botón suelto —que está fuera de la paleta— y no la fila,
        // que es la que se usa: un menú se quedaba sin reacción.
        $fila = $svc->sanitizeTree([['type' => 'buttons', 'id' => 'b', 'props' => ['items' => [
            ['label' => 'Uno', 'href' => 'https://ejemplo.co', 'style' => 'primary',
                'color' => '#123456', 'hoverColor' => '#abcdef'],
        ]], 'children' => []]]);
        $this->check('el hoverColor sobrevive al saneador',
            ($fila[0]['props']['items'][0]['hoverColor'] ?? '') === '#abcdef',
            json_encode($fila[0]['props']['items'][0] ?? []));
        $html = $r->render($fila);
        $this->check('  y llega al HTML como variable', str_contains($html, '--pg-btn-hover:#abcdef'), $html);

        // Los cuatro estilos tienen que usarla; el de «enlace» era el único que
        // ignoraba por completo el color elegido.
        foreach (['primary', 'outline', 'ghost', 'link'] as $estilo) {
            $this->check("«{$estilo}» usa la variable al pasar por encima",
                (bool) preg_match('~\.pg-btn-' . $estilo . ':hover \{[^}]*--pg-btn-hover~', $hoja), '');
        }

        // --- el lienzo del editor -------------------------------------------
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/editor.js');
        $this->check('el lienzo da espaciado a la sección',
            (bool) preg_match('~function sectionBody\(.*?spacingClasses\(p, \'padding\'\)~s', $js), '');
        $this->check('  y a la columna',
            (bool) preg_match('~function columnBody\(.*?spacingClasses\(cp\)~s', $js), '');
        $this->check('  y alinea su contenido', str_contains($js, 'pg-cv-'), '');
        $this->check('  y pinta el hover de la fila de botones',
            str_contains($js, "'--pg-btn-hover:' + b.hoverColor"), '');
    }
    /**
     * Cabecera y pie armados con elementos, y lo que hizo falta añadirles.
     *
     * Los dos bloques monolíticos (`siteHeader`/`siteFooter`) se retiraron. Lo
     * que aquí se comprueba es que **ningún trozo de lo que hacían se perdió
     * por el camino**: el logo que sale de la empresa, la medida exacta de una
     * imagen, el menú en fila o en columna, la hamburguesa sin guion y la
     * sección fija al desplazar.
     */
    private function headerFooterFromElements(PageService $svc, Renderer $r): void
    {
        $this->section('17. Cabecera y pie con elementos');

        // --- ya no son elementos de la paleta ------------------------------
        foreach (['siteHeader', 'siteFooter'] as $viejo) {
            $this->check("«{$viejo}» ya no es un tipo de bloque", !BlockRegistry::exists($viejo), '');
        }
        $tipos = array_keys(BlockRegistry::types());
        $this->check('el botón suelto sigue existiendo (hay páginas que lo usan)',
            in_array('button', $tipos, true), '');
        $this->check('  pero fuera de la paleta', !empty(BlockRegistry::forJs()['button']['hidden']), '');
        $this->check('  y «Botones» sí se ofrece', empty(BlockRegistry::forJs()['buttons']['hidden']), '');
        $vista = (string) file_get_contents(dirname(__DIR__) . '/Views/editor.php');
        $this->check('  la paleta filtra los ocultos', str_contains($vista, "empty(\$d['hidden'])"), '');

        // --- imagen: de dónde sale y cuánto mide ---------------------------
        $co  = $this->fakeCompany();
        $rc  = new Renderer([], ['company' => $co]);
        $img = static fn (array $p) => [$svc->newBlock('image', $p)];

        $logo = $rc->render($svc->sanitizeTree($img(['source' => 'logo'])));
        $this->check('la imagen puede ser el logo de la empresa', str_contains($logo, '/uploads/logo.png'), $logo);
        $banner = $rc->render($svc->sanitizeTree($img(['source' => 'banner'])));
        $this->check('  o su portada', str_contains($banner, '/uploads/banner.jpg'), $banner);
        $subida = $rc->render($svc->sanitizeTree($img(['source' => 'upload', 'src' => '/uploads/otra.png'])));
        $this->check('  o una subida a mano', str_contains($subida, '/uploads/otra.png'), '');
        $this->check('  y con «logo» NO se usa la subida',
            !str_contains($rc->render($svc->sanitizeTree($img(['source' => 'logo', 'src' => '/uploads/otra.png']))), 'otra.png'), '');

        foreach ([
            ['150px', 'width:150px'],
            ['60%', 'width:60%'],
            ['240', 'width:240px'],
            ['1.50rem', 'width:1.5rem'],
        ] as [$dado, $esperado]) {
            $html = $rc->render($svc->sanitizeTree($img(['source' => 'upload', 'src' => '/uploads/a.png', 'width' => $dado])));
            $this->check("el ancho «{$dado}» sale como {$esperado}", str_contains($html, $esperado), $html);
        }

        foreach (['abc', '0px', '5000px', '120%', '100px;color:red', 'calc(100% - 10px)'] as $malo) {
            $html = $rc->render($svc->sanitizeTree($img(['source' => 'upload', 'src' => '/uploads/a.png', 'width' => $malo])));
            $this->check('  se rechaza «' . $malo . '»', !str_contains($html, 'width:'), $html);
        }

        // --- botones: fila, columna, enlace y menú plegable -----------------
        $btn = static fn (array $p) => [$svc->newBlock('buttons', array_merge([
            'items' => [
                ['label' => 'Uno', 'href' => '#uno', 'style' => 'link', 'color' => '', 'newTab' => '0'],
                ['label' => 'Dos', 'href' => '#dos', 'style' => 'primary', 'color' => '', 'newTab' => '0'],
            ],
        ], $p))];

        $fila = $r->render($svc->sanitizeTree($btn(['direction' => 'row'])));
        $this->check('en fila lleva su clase', str_contains($fila, 'pg-btnrow-row'), $fila);
        $col = $r->render($svc->sanitizeTree($btn(['direction' => 'column'])));
        $this->check('en columna también', str_contains($col, 'pg-btnrow-column'), $col);
        $this->check('  y en columna NO se apila (ya lo está)',
            !str_contains($col, 'pg-btnrow-stack'), $col);

        $this->check('el estilo «enlace» existe', str_contains($fila, 'pg-btn-link'), '');
        $conColor = $r->render($svc->sanitizeTree($btn(['items' => [
            ['label' => 'Uno', 'href' => '#uno', 'style' => 'link', 'color' => '#aa0000', 'newTab' => '0'],
        ]])));
        $this->check('  y en él el color es el del TEXTO, no el del fondo',
            str_contains($conColor, '--pg-btn-fg:#aa0000') && !str_contains($conColor, '--pg-btn-bg'), $conColor);

        // --- tinta propia -----------------------------------------------
        // Sin esto la decidía sola la luminancia del fondo: blanca o casi
        // negra y nada más, que no siempre es lo que se quiere.
        $tinta = $r->render($svc->sanitizeTree($btn(['items' => [
            ['label' => 'Uno', 'href' => '#uno', 'style' => 'primary',
                'color' => '#111111', 'textColor' => '#ffcc00', 'newTab' => '0'],
        ]])));
        $this->check('el botón admite color de texto propio', str_contains($tinta, '--pg-btn-fg:#ffcc00'), $tinta);
        // Sustituye a la calculada en vez de añadirse detrás: la variable se
        // emite UNA vez, con el valor que gana.
        $this->check('  y manda sobre la tinta calculada',
            substr_count($tinta, '--pg-btn-fg') === 1 && str_contains($tinta, '--pg-btn-bg:#111111'), $tinta);
        $suelto = $r->render($svc->sanitizeTree([$svc->newBlock('button', [
            'label' => 'Uno', 'href' => '#uno', 'color' => '#111111', 'textColor' => '#ffcc00',
        ])]));
        $this->check('  el botón suelto también', str_contains($suelto, '--pg-btn-fg:#ffcc00'), $suelto);
        $this->check('  y el lienzo lo dibuja',
            substr_count((string) file_get_contents(dirname(__DIR__) . '/assets/js/editor.js'), 'textColor') >= 2, '');

        $plegado = $r->render($svc->sanitizeTree($btn(['collapse' => '1'])));
        $this->check('el menú plegable no usa JavaScript',
            str_contains($plegado, 'pg-nav-check') && !str_contains($plegado, '<script'), $plegado);
        $this->check('  con hamburguesa y panel', str_contains($plegado, 'pg-nav-burger')
            && str_contains($plegado, 'pg-btnrow-collapse'), $plegado);
        $this->check('  e id único por bloque',
            preg_match('/id="pg-nav-[A-Za-z0-9_-]+"/', $plegado) === 1, $plegado);

        $dos = $r->render($svc->sanitizeTree(array_merge($btn(['collapse' => '1']), $btn(['collapse' => '1']))));
        preg_match_all('/id="(pg-nav-[A-Za-z0-9_-]+)"/', $dos, $m);
        $this->check('  dos menús en la misma página no comparten casilla',
            count($m[1]) === 2 && $m[1][0] !== $m[1][1], implode(' / ', $m[1]));

        $sinPlegar = $r->render($svc->sanitizeTree($btn(['collapse' => '0'])));
        $this->check('sin plegar no se emite la casilla', !str_contains($sinPlegar, 'pg-nav-check'), '');

        // La hoja tiene que declarar lo que el HTML usa, o nada de esto se ve.
        $hoja = view('Components\Paginas\Views\public\_styles', ['settings' => []]);
        foreach (['.pg-btnrow-column', '.pg-btn-link', '.pg-nav-burger', '.pg-btnrow-collapse', '.pg-sec-sticky'] as $clase) {
            $this->check("la hoja declara {$clase}", str_contains($hoja, $clase), '');
        }
        $this->check('y ya no arrastra el CSS de los bloques retirados',
            !str_contains($hoja, '.pg-foot-') && !str_contains($hoja, '.pg-nav-link'), '');

        // --- sección fija ---------------------------------------------------
        $sec = static fn (array $p) => [array_merge(
            $svc->newBlock('section', array_merge(['columns' => '1'], $p)),
            ['children' => [array_merge($svc->newBlock('column'), ['children' => [$svc->newBlock('heading', ['text' => 'T'])]])]]
        )];
        // OJO: $r es el renderizador DEL EDITOR (así lo construye la sección
        // 10 y así llega hasta aquí). Para juzgar lo que se publica hace falta
        // uno sin contexto.
        $fija = (new Renderer())->render($svc->sanitizeTree($sec(['sticky' => '1'])));
        $this->check('la sección puede quedar fija al desplazar', str_contains($fija, 'pg-sec-sticky'), $fija);
        $enEditor = $r->render($svc->sanitizeTree($sec(['sticky' => '1'])));
        $this->check('  pero NO dentro del editor (taparía el lienzo)', !str_contains($enEditor, 'pg-sec-sticky'), '');

        // --- lo que el lienzo del editor debe saber ------------------------
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/editor.js');
        $this->check('el lienzo ya no dibuja los bloques retirados',
            !str_contains($js, 'siteHeader') && !str_contains($js, 'siteFooter'), '');
        $this->check('  y sí el origen y el ancho de la imagen',
            str_contains($js, "p.source === 'logo'") && str_contains($js, 'cssLength(p.width)'), '');
        $this->check('  y la dirección de los botones', str_contains($js, "pg-btnrow-' + dir"), '');
    }

    // =======================================================================
    // COBERTURA DE PROPIEDADES
    //
    // Recorre **todos los campos de todos los tipos** y comprueba dos cosas por
    // cada uno:
    //
    //   1. que cambiarlo cambia el HTML que produce el servidor, y
    //   2. que el editor lo tiene en cuenta al dibujar el lienzo.
    //
    // La segunda es la que faltaba: el color y el ancho completo del botón
    // existían y funcionaban al publicar, pero el lienzo los ignoraba, así que
    // mientras se editaba no pasaba nada. Un fallo así no lo caza ninguna
    // prueba que solo mire la salida del servidor.
    // =======================================================================

    /** Valores mínimos para que cada tipo pinte algo con lo que comparar. */
    private function baseProps(string $type): array
    {
        $comunes = [
            'text' => 'Texto', 'title' => 'Título', 'label' => 'Etiqueta',
            'html' => '<p>Hola</p>', 'code' => '<p>Hola</p>',
            'src' => '/uploads/a.png', 'image' => '/uploads/a.png',
            'backgroundImage' => '/uploads/f.jpg',
            'href' => 'https://ejemplo.co', 'url' => 'https://youtu.be/abcdefghijk',
            'buttonLabel' => 'Ir', 'buttonHref' => 'https://ejemplo.co',
            'subtitle' => 'Sub', 'caption' => 'Pie', 'alt' => 'Alt',
            'author' => 'Autor', 'role' => 'Cargo', 'intro' => 'Intro',
            'linkLabel' => 'Ver', 'submitLabel' => 'Enviar', 'tagline' => 'Lema',
            // Una fila de repetidor que sirve para TODOS: cada tipo se queda con
            // los sub-campos que declara. Sin `label` la fila de botones y el
            // menú de la cabecera salían vacíos y parecía que sus ajustes no
            // hacían nada.
            'items' => [[
                'text' => 'Uno', 'label' => 'Etiqueta', 'src' => '/uploads/a.png', 'alt' => 'Alt',
                'caption' => 'Pie', 'title' => 'Tarjeta', 'image' => '/uploads/a.png',
                'linkLabel' => 'Ver', 'href' => 'https://ejemplo.co', 'style' => 'primary',
            ]],
            'col2Links' => [['label' => 'Uno', 'href' => 'https://ejemplo.co']],
            'col3Links' => [['label' => 'Dos', 'href' => 'https://ejemplo.co']],
            'fields' => [['label' => 'Nombre', 'type' => 'text', 'options' => '', 'required' => '0']],
            // Sin destino, la etiqueta del botón destacado no pinta nada, y sin
            // etiqueta tampoco lo hace el destino: se necesitan los dos.
            'ctaLabel' => 'Contacto', 'ctaHref' => 'https://ejemplo.co',
        ];

        $props = [];

        foreach (BlockRegistry::propNames($type) as $name) {
            if (array_key_exists($name, $comunes)) {
                $props[$name] = $comunes[$name];
            }
        }

        // El QR solo mira `url` cuando su destino es «a medida»; con cualquier
        // otro, la dirección escrita se ignora a propósito.
        if ($type === 'companyQr') {
            $props['target'] = 'custom';
            $props['url']    = 'https://ejemplo.co/uno';
        }

        // El redondeo de una columna solo se nota si la columna tiene fondo:
        // sin fondo no hay caja que redondear, y la propiedad —con razón— no
        // cambia nada. Se le da uno para poder juzgarla.
        if ($type === 'column') {
            $props['background'] = '#eef2f7';
        }

        // El logo propio de la cabecera solo se usa si la fuente es «subir otro».
        if ($type === 'siteHeader') {
            $props['logoSource'] = 'custom';
            $props['logoImage']  = '/uploads/logo-propio.png';
        }

        return $props;
    }

    /**
     * Un bloque en el sitio del árbol donde de verdad se pinta.
     *
     * Una `column` suelta en la raíz la descarta el saneador —solo vive dentro
     * de una sección—, así que compararla consigo misma daba siempre vacío. No
     * era un fallo del código: era la prueba mirando donde no debía.
     */
    private function inContext(string $type, array $props): array
    {
        if ($type !== 'column') {
            return [['type' => $type, 'id' => 'c', 'props' => $props, 'children' => []]];
        }

        return [[
            'type' => 'section', 'id' => 's', 'props' => ['columns' => '2'],
            'children' => [['type' => 'column', 'id' => 'c', 'props' => $props, 'children' => []]],
        ]];
    }

    /** El primer bloque de ese tipo en el árbol, o []. */
    private function find(array $blocks, string $tipo): array
    {
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === $tipo) {
                return $b;
            }
            $hallado = $this->find($b['children'] ?? [], $tipo);
            if ($hallado) {
                return $hallado;
            }
        }

        return [];
    }

    /** Los tipos del árbol, en orden de recorrido (para juzgar su forma). */
    private function typesIn(array $blocks): array
    {
        $out = [];

        foreach ($blocks as $b) {
            if (!is_array($b)) {
                continue;
            }
            $out[] = (string) ($b['type'] ?? '');
            $out = array_merge($out, $this->typesIn($b['children'] ?? []));
        }

        return $out;
    }

    /** Un valor distinto del que trae por defecto, para ver si cambia algo. */
    private function otherValue(string $type, array $field, $actual)
    {
        switch ($field['type'] ?? 'text') {
            case 'select':
                foreach (BlockRegistry::optionValues($type, $field['name']) as $v) {
                    if ($v !== (string) $actual && $v !== '') {
                        return $v;
                    }
                }

                return null;

            case 'toggle':
                return (string) $actual === '1' ? '0' : '1';

            case 'color':
                return '#8a1f3c';

            case 'anchor':
                return 'ancla-de-prueba';

            case 'text':
                // Un teléfono tiene que seguir siendo un teléfono: con texto
                // suelto, el bloque cae —con razón— al de la empresa.
                if ($field['name'] === 'phone') {
                    return '573009998877';
                }
                // Y un ancho, una medida: «Valor bien distinto» no es CSS y el
                // renderizador lo descarta, con razón.
                if ($field['name'] === 'width') {
                    // El de la columna es un porcentaje; el de la imagen, px.
                    return $type === 'column' ? '40%' : '240px';
                }
                break;

            case 'image':
            case 'link':
                return 'https://ejemplo.co/otra.png';

            case 'richtext':
                return '<p>Otro contenido bien distinto</p>';

            case 'repeater':
                return null;   // se cubren por su contenido base
        }

        return 'Valor bien distinto del anterior';
    }

    private function propertyCoverage(): void
    {
        $this->section('13. Cobertura: toda propiedad de todo elemento');

        $svc = new PageService();
        $co  = $this->fakeCompany();
        $ctx = ['company' => $co, 'pageUrl' => 'https://mi.sitio/p/a/b', 'formAction' => 'https://x/e', 'csrf' => ''];
        $r   = new Renderer([], $ctx);

        $sinEfecto = [];
        $noProbados = [];
        $total = 0;

        foreach (BlockRegistry::types() as $type => $def) {
            $base = $this->baseProps($type);

            foreach ($def['fields'] as $field) {
                $name = $field['name'];
                $total++;

                $props = array_merge(BlockRegistry::defaults($type), $base);
                $otro  = $this->otherValue($type, $field, $props[$name] ?? '');

                if ($otro === null) {
                    $noProbados[] = "{$type}.{$name}";

                    continue;
                }

                // El acuse del formulario solo existe cuando ya se envió: hay
                // que pintarlo en ese estado o el mensaje no aparece nunca.
                $pintor = ($type === 'form' && $name === 'successMessage')
                    ? new Renderer([], array_merge($ctx, ['sentBlock' => 'c']))
                    : $r;

                $antes  = $pintor->render($svc->sanitizeTree($this->inContext($type, $props)));
                $props[$name] = $otro;
                $despues = $pintor->render($svc->sanitizeTree($this->inContext($type, $props)));

                if ($antes === $despues) {
                    $sinEfecto[] = "{$type}.{$name}";
                }
            }
        }

        $this->check(
            "las {$total} propiedades cambian la salida del servidor",
            $sinEfecto === [],
            implode(', ', $sinEfecto)
        );
        // Los repetidores se prueban por su contenido, no cambiando un valor
        // suelto. Se comprueba por TIPO de campo y no por su nombre: con
        // `col2Links` y compañía, mirar el nombre dejaba de valer.
        $noRepetidores = array_values(array_filter($noProbados, static function (string $n): bool {
            [$tipo, $campo] = explode('.', $n, 2);

            return (BlockRegistry::field($tipo, $campo)['type'] ?? '') !== 'repeater';
        }));
        $this->check('los campos no comparables son solo repetidores', $noRepetidores === [],
            implode(', ', $noRepetidores));

        // ---- el lienzo del editor tiene que conocerlas todas ---------------
        //
        // Comprobación estática sobre `editor.js`: si una propiedad no se
        // menciona, el lienzo no puede estar dibujándola. Es tosca, pero es
        // exactamente lo que faltaba cuando el botón ignoraba `color` y
        // `fullWidth` mientras se editaba.
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/editor.js');

        // Los campos comunes los aplica `withSpacing()`/el saneador, no cada
        // rama del `preview()`; y estos tres no se dibujan en el lienzo por
        // decisión explícita (el QR lo genera el servidor; el HTML avanzado no
        // se inyecta para no ejecutar lo que traiga).
        $exentos = [
            // Los aplica `withSpacing()` y el saneador, no cada rama del render.
            'anchor', 'marginTop', 'marginBottom', 'padding',
            // El QR lo genera el servidor (GD); en el lienzo va un hueco con su
            // medida, que es lo que importa para maquetar.
            'companyQr.target', 'companyQr.url', 'companyQr.size', 'companyQr.frame',
            // El HTML avanzado NO se inyecta en el lienzo: un `<script>` pegado
            // ahí se ejecutaría dentro del editor.
            'html.code',
            'video.ratio',
            // Estos cuatro solo afectan al ENLACE, y el lienzo dibuja los
            // botones como `<span>` a propósito —para que un clic mientras se
            // maqueta no saque al usuario del editor—. No tienen nada que
            // pintar aquí; se ven en la vista previa y al publicar.
            'button.newTab', 'hero.buttonHref', 'whatsappFloat.message',
            // El acuse del formulario solo aparece tras enviarlo, y en el
            // lienzo el formulario nunca se envía.
            'form.successMessage',
            // Una sección fija se pegaría al lienzo tapando lo que hay debajo:
            // el editor la pinta siempre estática (igual que el WhatsApp), y el
            // servidor tampoco la fija cuando pinta para el editor.
            'section.sticky',
        ];

        $faltan = [];

        foreach (BlockRegistry::types() as $type => $def) {
            foreach ($def['fields'] as $field) {
                $name = $field['name'];
                if (in_array($name, $exentos, true) || in_array("{$type}.{$name}", $exentos, true)) {
                    continue;
                }
                // Se busca `p.<nombre>` —como el editor lee las propiedades— o
                // el nombre entrecomillado, que es como se leen las que van por
                // una tabla (`[['col2Title','col2Links'], …]`).
                $q = preg_quote($name, '/');
                if (!preg_match('/\b(?:p|c|i|f|it|col|b|l)\.' . $q . '\b/', $js)
                    && !preg_match("/'" . $q . "'/", $js)) {
                    $faltan[] = "{$type}.{$name}";
                }
            }
        }

        $faltan = array_values(array_unique($faltan));
        $this->check('el lienzo del editor las tiene todas en cuenta', $faltan === [], implode(', ', $faltan));

        // ---- el botón, campo por campo (es el que se rompió) --------------
        $btnBase = ['label' => 'Ir', 'href' => 'https://ejemplo.co'];
        $pinta   = fn (array $extra): string => $r->render($svc->sanitizeTree([[
            'type' => 'button', 'id' => 'b', 'props' => array_merge($btnBase, $extra),
        ]]));

        $this->check('el botón: color propio llega como variable',
            str_contains($pinta(['color' => '#aa0000']), '--pg-btn-bg:#aa0000'), $pinta(['color' => '#aa0000']));
        $this->check('  y con tinta legible calculada',
            str_contains($pinta(['color' => '#aa0000']), '--pg-btn-fg:#ffffff')
            && str_contains($pinta(['color' => '#ffee00']), '--pg-btn-fg:#14161a'), '');
        $this->check('el botón: color al pasar por encima',
            str_contains($pinta(['hoverColor' => '#004400']), '--pg-btn-hover:#004400'), '');
        $this->check('el botón: ancho completo', str_contains($pinta(['fullWidth' => '1']), 'pg-btn-block'), '');
        $this->check('el botón: radio de las esquinas',
            str_contains($pinta(['radius' => 'full']), 'pg-btn-r-full')
            && str_contains($pinta(['radius' => 'none']), 'pg-btn-r-none'), '');

        $hoja = view('Components\Paginas\Views\public\_styles', ['settings' => []]);
        $this->check('la hoja usa las variables del botón',
            str_contains($hoja, 'background: var(--pg-btn-bg)'), '');
        $this->check('  y tiene estado con el puntero encima en los tres estilos',
            str_contains($hoja, '.pg-btn-primary:hover') && str_contains($hoja, '.pg-btn-outline:hover')
            && str_contains($hoja, '.pg-btn-ghost:hover'), '');
        $this->check('  el radio del botón se declara DESPUÉS de .pg-btn',
            strpos($hoja, '.pg-btn-r-none') > strpos($hoja, '.pg-btn {'), '');
    }
}
