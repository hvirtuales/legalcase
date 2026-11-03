<?php

namespace Components\LegalCase\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\LegalCase\Libraries\Clock;

/**
 * `php spark lgc:tick [--company=N]`
 *
 * El reloj del componente. Programarlo cada 15 minutos (cron o Programador de tareas):
 *   * / 15 * * * *  php /ruta/spark lgc:tick
 * Crea las tareas «toca informar», reintenta avisos fallidos y limpia enlaces caducados.
 * Idempotente: se puede ejecutar tantas veces como se quiera.
 */
class LgcTick extends BaseCommand
{
    protected $group       = 'LegalCase';
    protected $name        = 'lgc:tick';
    protected $description = 'Recordatorios de comunicación, reintento de avisos y limpieza de enlaces del portal.';
    protected $options     = ['--company' => 'Limitar los recordatorios a una empresa (id).'];

    public function run(array $params)
    {
        $company = CLI::getOption('company');
        $out     = (new Clock())->run($company ? (int) $company : null);

        CLI::write(sprintf(
            'LegalCase: %d recordatorio(s) de comunicación · %d aviso(s) enviado(s) · %d fallido(s) · %d omitido(s) · %d enlace(s) caducado(s) eliminados',
            $out['update_due'], $out['sent'], $out['failed'], $out['skipped'], $out['tokens']
        ), 'green');
    }
}
