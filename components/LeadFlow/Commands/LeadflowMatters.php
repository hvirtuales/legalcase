<?php

namespace Components\LeadFlow\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\LeadFlow\Libraries\Access;
use Components\LeadFlow\Libraries\Catalog;
use Components\LeadFlow\Libraries\MatterService;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\MatterModel;

/**
 * `php spark leadflow:expedientes --backfill [--company=N] [--apply]`
 *
 * Abre el expediente de los leads que ya estaban ganados o convertidos antes de que
 * existieran los expedientes.
 *
 * ── Por qué un comando y no una migración ────────────────────────────────────
 *
 * Una migración no puede llamar al servicio: depende de `lang()`, de `Access` y de la
 * sesión. Tendría que reimplementar el consecutivo, el snapshot y la línea de tiempo,
 * y una segunda implementación de la numeración es exactamente la que se desincroniza.
 *
 * Y lo más importante: esto **inventa datos de negocio de un cliente real**. Abrir un
 * expediente congela un snapshot y consume número; no es una operación que deba
 * ocurrir en silencio al desplegar. Es el criterio de `spark <comp>:seed` del
 * contrato: los datos de arranque opcionales los pide una persona.
 *
 * ── No escribe salvo que se lo pidas ─────────────────────────────────────────
 *
 * Por omisión hace un ensayo e imprime lo que haría. Hay que pasar `--apply` para que
 * escriba. Es al revés que el `--dry-run` de `leadflow:phones` a propósito: ese solo
 * completaba un teléfono vacío, este crea registros nuevos.
 *
 * Es **idempotente**: salta los leads que ya tienen expediente (el `UNIQUE(lead_id)`
 * lo garantiza aunque dos ejecuciones se solaparan), así que se puede repetir.
 */
class LeadflowMatters extends BaseCommand
{
    protected $group       = 'LeadFlow';
    protected $name        = 'leadflow:expedientes';
    protected $description = 'Abre expedientes para los leads ya ganados o convertidos (ensayo salvo --apply).';
    protected $options     = [
        '--backfill' => 'Obligatorio: confirma que es esta operación y no otra',
        '--company'  => 'Solo esta empresa (por defecto, todas)',
        '--apply'    => 'Escribe de verdad. Sin esto solo enseña lo que haría',
    ];

    public function run(array $params)
    {
        if (! array_key_exists('backfill', $params) && CLI::getOption('backfill') === null) {
            CLI::error('Hace falta --backfill. Es el único modo que tiene este comando.');

            return;
        }

        $apply   = CLI::getOption('apply') !== null;
        $soloCid = (int) (CLI::getOption('company') ?? 0);

        CLI::write($apply ? 'Abriendo expedientes…' : 'ENSAYO (sin --apply no se escribe nada)', 'yellow');

        $leads = new LeadModel();
        $b     = $leads->select('cmp_lfl_leads.*')
            ->whereIn('cmp_lfl_leads.status', ['won', 'converted'])
            ->where('cmp_lfl_leads.deleted_at', null)
            ->orderBy('cmp_lfl_leads.company_id', 'asc')
            ->orderBy('cmp_lfl_leads.won_at', 'asc')
            ->orderBy('cmp_lfl_leads.id', 'asc');
        if ($soloCid > 0) {
            $b->where('cmp_lfl_leads.company_id', $soloCid);
        }

        $candidatos = $b->findAll();
        if ($candidatos === []) {
            CLI::write('No hay leads ganados ni convertidos.', 'green');

            return;
        }

        $matters = new MatterModel();
        $hechos  = 0;
        $saltados = 0;
        $fallos  = 0;
        $vistas  = [];

        foreach ($candidatos as $lead) {
            $cid    = (int) $lead['company_id'];
            $nombre = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));

            if ($matters->existsForLead((int) $lead['id'])) {
                $saltados++;
                continue;
            }

            // El catálogo por firma tiene que existir antes: sin etapas procesales el
            // expediente nacería sin etapa. En CLI nadie pasó por `BaseLfl::render()`.
            if (! isset($vistas[$cid])) {
                $vistas[$cid] = true;
                if ($apply) {
                    Catalog::ensure($cid);
                }
            }

            $svc = new MatterService();

            if (! $apply) {
                $problemas = $svc->problems($lead);
                $estado    = $problemas === [] ? CLI::color('se abriría', 'green') : CLI::color(implode(' ', $problemas), 'red');
                CLI::write('  [' . $cid . '] #' . $lead['id'] . ' ' . $nombre . ': ' . $estado);
                $problemas === [] ? $hechos++ : $fallos++;
                continue;
            }

            /*
             * Con `Access::system()`: ve y escribe dentro de esa empresa y de ninguna
             * otra, sin suplantar a nadie. Al pasar `userId = 0` la línea de tiempo lo
             * registra como del sistema, que es la verdad — este expediente no lo abrió
             * una persona.
             */
            $acceso = Access::system($cid);
            $r      = $svc->open($lead, [], $acceso->userId ?: null);

            if ($r['ok']) {
                $hechos++;
                CLI::write('  [' . $cid . '] #' . $lead['id'] . ' ' . $nombre . ': ' . CLI::color($r['message'], 'green'));
            } else {
                $fallos++;
                CLI::write('  [' . $cid . '] #' . $lead['id'] . ' ' . $nombre . ': ' . CLI::color($r['message'], 'red'));
            }
        }

        CLI::newLine();
        CLI::write(($apply ? 'Abiertos: ' : 'Se abrirían: ') . $hechos
            . ' · ya tenían: ' . $saltados
            . ' · sin poder abrir: ' . $fallos, $fallos > 0 ? 'yellow' : 'green');

        if (! $apply && $hechos > 0) {
            CLI::write('Para escribirlos: php spark leadflow:expedientes --backfill --apply', 'yellow');
        }
    }
}
