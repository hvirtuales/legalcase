<?php

namespace App\Commands;

use App\Libraries\ComponentManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ComponentsInstall extends BaseCommand
{
    protected $group       = 'Componentes';
    protected $name        = 'components:install';
    protected $description = 'Instala un componente: corre sus migraciones y siembra sus permisos.';
    protected $usage       = 'components:install <Carpeta>';
    protected $arguments   = ['Carpeta' => 'Nombre de la carpeta del componente (p. ej. Contabilidad).'];

    public function run(array $params)
    {
        $folder = $params[0] ?? CLI::prompt('Carpeta del componente');
        $res    = (new ComponentManager())->install($folder);
        CLI::write('  ' . $res['message'], $res['ok'] ? 'green' : 'red');
    }
}
