<?php

namespace Components\LegalCase\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\LegalCase\Libraries\DemoData;

/**
 * `php spark lgc:demo --company 5 --lead 17 --second 1`
 *
 * Carga datos de ejemplo en un despacho VACÍO para validar el componente desde la
 * interfaz (ver `Libraries/DemoData`). No envía correos: al terminar imprime los
 * enlaces de invitación al portal (válidos 72 h, de un solo uso).
 *
 * `--lead` es quien responde de casi todo y `--second` la otra persona que aprueba y
 * hace la segunda validación; las dos deben ser miembros de la empresa.
 */
class LgcDemo extends BaseCommand
{
    protected $group       = 'LegalCase';
    protected $name        = 'lgc:demo';
    protected $description = 'Carga datos de ejemplo en un despacho vacío (sin enviar correos).';
    protected $usage       = 'lgc:demo --company <id> --lead <user_id> --second <user_id> [--base <url>]  |  lgc:demo --company <id> --links';
    protected $options     = [
        '--company' => 'Empresa (despacho) donde cargar los datos. Debe no tener asuntos.',
        '--lead'    => 'Usuario responsable principal (necesita lgc.approve y lgc.share).',
        '--second'  => 'Segundo abogado del despacho (redacta, colabora y valida).',
        '--base'    => 'URL base del sitio para imprimir los enlaces del portal (por omisión http://localhost/pms/).',
        '--links'   => 'No carga nada: emite enlaces nuevos para las cuentas demo del portal.',
    ];

    public function run(array $params)
    {
        $cid    = (int) $this->opt('company');
        $lead   = (int) $this->opt('lead');
        $second = (int) $this->opt('second');
        $base   = rtrim($this->opt('base') ?: 'http://localhost/pms/', '/') . '/';

        // Solo enlaces nuevos para las cuentas demo, sin cargar nada.
        if ($cid && CLI::getOption('links') !== null) {
            $this->printLinks(DemoData::reissueLinks($cid), $base);

            return EXIT_SUCCESS;
        }
        if (!$cid || !$lead || !$second) {
            CLI::error('Uso: ' . $this->usage);

            return EXIT_ERROR;
        }

        $db   = db_connect();
        $demo = new DemoData($cid, $lead, $second);
        $db->transStart();
        try {
            $res = $demo->run();
        } catch (\Throwable $e) {
            $db->transRollback();
            $demo->discardFiles();
            CLI::error('No se cargó nada: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());

            return EXIT_ERROR;
        }
        if (!$res['ok']) {
            $db->transRollback();
            CLI::error($res['message']);

            return EXIT_ERROR;
        }
        $db->transComplete();
        if (!$db->transStatus()) {
            $demo->discardFiles();
            CLI::error('La transacción falló: no se cargó nada.');

            return EXIT_ERROR;
        }

        CLI::write($res['message'], 'green');
        CLI::write('Asuntos: ' . implode(', ', $res['matters']));
        $this->printLinks($res['links'], $base);

        return EXIT_SUCCESS;
    }

    private function printLinks(array $links, string $base): void
    {
        CLI::newLine();
        CLI::write('Enlaces de acceso al portal (72 h, un solo uso; `--links` emite otros):', 'yellow');
        foreach ($links as $l) {
            CLI::write('  ' . $l['who'] . ' <' . $l['email'] . '>');
            CLI::write('  ' . $base . $l['url'], 'cyan');
        }
    }

    /** `--clave valor` (la forma de CI4) y también `--clave=valor`, que es la que se escribe por costumbre. */
    private function opt(string $name): string
    {
        $v = CLI::getOption($name);
        if (is_string($v) && $v !== '') {
            return $v;
        }
        foreach (CLI::getOptions() as $k => $val) {
            if (str_starts_with((string) $k, $name . '=')) {
                return substr((string) $k, strlen($name) + 1);
            }
        }

        return '';
    }
}
