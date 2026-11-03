<?php

namespace App\Commands;

use App\Libraries\ComponentManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ComponentsUninstall extends BaseCommand
{
    protected $group       = 'Componentes';
    protected $name        = 'components:uninstall';
    protected $description = 'Desinstala un componente. Por defecto conserva los datos; con --drop-data revierte sus migraciones (borra tablas) y quita sus permisos.';
    protected $usage       = 'components:uninstall <Carpeta> [--drop-data] [--purge] [--force]';
    protected $arguments   = ['Carpeta' => 'Nombre de la carpeta del componente (p. ej. Contabilidad).'];
    protected $options     = [
        '--drop-data' => 'Elimina las tablas del componente y sus permisos (irreversible).',
        '--purge'     => 'Eliminación total: borra además la carpeta en components/ (implica --drop-data).',
        '--force'     => 'No pide confirmación.',
    ];

    public function run(array $params)
    {
        $folder = $params[0] ?? CLI::prompt('Carpeta del componente');
        $purge  = array_key_exists('purge', CLI::getOptions());
        // Borrar los archivos sin revertir las migraciones dejaría tablas huérfanas.
        $drop  = $purge || array_key_exists('drop-data', CLI::getOptions());
        $force = array_key_exists('force', CLI::getOptions());

        $mgr = new ComponentManager();
        if (!$mgr->isInstalled($folder) && !$purge) {
            CLI::write("  El componente '{$folder}' no está instalado.", 'yellow');

            return;
        }

        if ($drop && !$force) {
            CLI::write("  ¡ATENCIÓN! Se eliminarán las tablas y TODOS los datos de '{$folder}'.", 'red');
            if ($purge) {
                CLI::write('  Además se BORRARÁ la carpeta components' . DIRECTORY_SEPARATOR . $folder . ' del disco.', 'red');
            }
            if (CLI::prompt('  ¿Continuar?', ['n', 'y']) !== 'y') {
                CLI::write('  Cancelado.', 'yellow');

                return;
            }
        }

        $res = $mgr->uninstall($folder, $drop, $purge);
        CLI::write('  ' . $res['message'], $res['ok'] ? 'green' : 'red');
    }
}
