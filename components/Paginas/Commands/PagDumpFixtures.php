<?php

namespace Components\Paginas\Commands;

use CodeIgniter\CLI\BaseCommand;
use Components\Paginas\Libraries\BlockRegistry;
use Components\Paginas\Libraries\PageService;
use Components\Paginas\Libraries\SectionLibrary;

/**
 * Vuelca el registro de bloques y un árbol de ejemplo para `editor_test.js`.
 *
 * La prueba del lienzo corre en Node y necesita los mismos datos que recibe el
 * editor en el navegador. Vivían en dos ficheros sueltos que se generaban a
 * mano y se perdieron; con este comando se rehacen y la prueba deja de depender
 * de que alguien recuerde el paso.
 */
class PagDumpFixtures extends BaseCommand
{
    protected $group       = 'Páginas';
    protected $name        = 'paginas:fixtures';
    protected $description = 'Vuelca el registro de bloques y un árbol de ejemplo para las pruebas del lienzo.';

    public function run(array $params)
    {
        $destino = $params[0] ?? WRITEPATH . 'pag_fixtures';
        @mkdir($destino, 0777, true);

        file_put_contents($destino . '/registry.json', json_encode(BlockRegistry::forJs()));

        // Un árbol con cabecera, un par de secciones prearmadas y el pie: es
        // lo que ejercita secciones, columnas y elementos anidados a la vez,
        // sin depender de que haya nada en la base.
        $svc   = new PageService();
        $arbol = SectionLibrary::header($svc);

        foreach (array_slice(SectionLibrary::all(), 0, 3) as $sec) {
            foreach ($sec['blocks'] ?? [] as $b) {
                $arbol[] = $b;
            }
        }
        foreach (SectionLibrary::footer($svc) as $b) {
            $arbol[] = $b;
        }

        // `array_values` a conciencia: con claves de texto, `json_encode` produce
        // un objeto y en Node deja de ser un array — la prueba reventaba con un
        // «tree.map is not a function» que no dice nada de la causa.
        // Pasa por el saneador, que es lo que hay guardado de verdad: así los
        // bloques llevan su id, igual que cuando el editor abre una página.
        file_put_contents($destino . '/tree.json', json_encode(array_values($svc->sanitizeTree(array_values($arbol)))));

        // Lo demás que el editor recibe en bloques JSON: las secciones ya
        // armadas de la paleta y los iconos con los que dibuja el lienzo.
        // La MISMA forma que el bloque `#pagSections` de la vista del editor: si
        // aquí se volcara otra, la prueba correría contra datos que el editor
        // nunca recibe.
        file_put_contents($destino . '/sections.json', json_encode(array_map(
            static fn ($x) => ['code' => $x['code'], 'blocks' => $x['blocks']],
            SectionLibrary::all()
        )));
        file_put_contents($destino . '/icons.json', json_encode(\Components\Paginas\Libraries\Renderer::iconSet()));

        \CodeIgniter\CLI\CLI::write('Volcado en ' . $destino, 'green');

        return 0;
    }
}
