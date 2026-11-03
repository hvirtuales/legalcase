<?php

namespace Components\Formularios\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Formularios\Libraries\TemplateLibrary;

/**
 * Siembra las plantillas de arranque (globales, `company_id` NULL).
 *
 * Va en un comando y no en una escritura escondida dentro de un GET: los
 * eventos de ciclo de vida del contrato (5) nunca se implementaron en el
 * núcleo, así que instalar el componente no dispara nada. Es idempotente por
 * `code`: correrlo dos veces actualiza, no duplica.
 */
class FormulariosSeed extends BaseCommand
{
    protected $group       = 'Formularios';
    protected $name        = 'formularios:seed';
    protected $description = 'Siembra o repone las plantillas de arranque del componente Formularios.';

    public function run(array $params)
    {
        $n = TemplateLibrary::seed();

        CLI::write('Plantillas de arranque sembradas: ' . $n, 'green');

        return 0;
    }
}
