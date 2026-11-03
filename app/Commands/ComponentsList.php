<?php

namespace App\Commands;

use App\Libraries\ComponentManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ComponentsList extends BaseCommand
{
    protected $group       = 'Componentes';
    protected $name        = 'components:list';
    protected $description = 'Lista los componentes detectados en components/.';

    public function run(array $params)
    {
        $components = (new ComponentManager())->discover();
        if (!$components) {
            CLI::write('No hay componentes en components/.', 'yellow');

            return;
        }
        foreach ($components as $folder => $m) {
            CLI::write(sprintf('  [%s] %s v%s  — %s', $m['code'] ?? '?', $m['name'] ?? $folder, $m['version'] ?? '?', $folder), 'green');
            CLI::write('        ' . ($m['description'] ?? ''));
        }
    }
}
