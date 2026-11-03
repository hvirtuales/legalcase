<?php

namespace Components\Paginas\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Paginas\Libraries\TemplateLibrary;

/**
 * Siembra las plantillas de arranque (globales, `company_id` NULL).
 *
 * Existe porque el gestor de componentes **no dispara** los eventos de ciclo de
 * vida que menciona el contrato §5 (`component.installed` y compañía): no hay
 * gancho donde colgar la siembra al instalar. Las alternativas eran peores —
 * sembrar dentro de un GET convierte una lectura en escritura y obliga a
 * comprobar en cada visita algo que casi siempre ya está hecho.
 *
 * Es idempotente (comprueba por `code`), así que se puede repetir sin duplicar.
 * También se dispara desde el botón «Recuperar plantillas de arranque» de la
 * pantalla de plantillas.
 *
 *     php spark paginas:seed
 */
class PaginasSeed extends BaseCommand
{
    protected $group       = 'Páginas';
    protected $name        = 'paginas:seed';
    protected $description = 'Siembra las plantillas de arranque del constructor de páginas (idempotente).';

    public function run(array $params)
    {
        try {
            $n = (new TemplateLibrary())->seed();
        } catch (\Throwable $e) {
            CLI::error('No se pudo sembrar: ' . $e->getMessage());

            return 1;
        }

        CLI::write(
            $n > 0
                ? "Se crearon {$n} plantillas de arranque."
                : 'Las plantillas de arranque ya estaban. No se creó ninguna.',
            'green'
        );

        return 0;
    }
}
