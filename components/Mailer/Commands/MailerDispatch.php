<?php

namespace Components\Mailer\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Mailer\Libraries\MailerService;
use Components\Mailer\Libraries\PlanGuard;
use Components\Mailer\Libraries\ReputationGuard;
use Components\Mailer\Models\CampaignModel;

/**
 * Despacho de las campañas encoladas, para el cron.
 *
 * Encolar una campaña desde el panel solo escribe los destinatarios; quien los
 * envía de verdad es esto, en lotes, para que un envío de miles no dependa de
 * que alguien deje una pestaña abierta.
 *
 *   * * * * *  php spark mailer:dispatch
 *
 * Comprueba lo mismo que el panel antes de cada campaña —servicio del plan,
 * cupo del mes y reputación—, porque entre encolar y despachar pueden pasar
 * horas y cualquiera de las tres cosas puede haber cambiado.
 *
 * Texto en español y sin `lang()` a propósito: es una herramienta de operador,
 * como el resto de comandos de la plataforma.
 */
class MailerDispatch extends BaseCommand
{
    protected $group       = 'Mailer';
    protected $name        = 'mailer:dispatch';
    protected $description = 'Envía los lotes pendientes de las campañas encoladas.';
    protected $usage       = 'mailer:dispatch [--max N] [--campaign ID]';
    protected $options     = [
        '--max'      => 'Máximo de correos a procesar en esta corrida (default 500).',
        '--campaign' => 'Despachar solo esta campaña.',
    ];

    public function run(array $params)
    {
        $max  = (int) ($params['max'] ?? CLI::getOption('max') ?? 500);
        $solo = (int) ($params['campaign'] ?? CLI::getOption('campaign') ?? 0);
        if ($max < 1) {
            $max = 500;
        }

        $campaigns = new CampaignModel();
        $query     = $campaigns->where('status', 'sending');
        if ($solo > 0) {
            $query->where('id', $solo);
        }
        $pendientes = $query->orderBy('id', 'asc')->findAll();

        if (!$pendientes) {
            CLI::write('No hay campañas en cola.', 'yellow');

            return 0;
        }

        $svc    = new MailerService();
        $plan   = new PlanGuard();
        $rep    = new ReputationGuard();
        $hechos = 0;

        foreach ($pendientes as $c) {
            if ($hechos >= $max) {
                CLI::write("Tope de {$max} correos alcanzado en esta corrida; el resto queda para la próxima.", 'yellow');
                break;
            }

            $id        = (int) $c['id'];
            $companyId = (int) $c['company_id'];

            if (!$plan->serviceEnabled($companyId)) {
                CLI::write("#{$id}: el plan de la empresa no incluye correos masivos — se salta.", 'yellow');
                continue;
            }
            if (!$rep->canSend($companyId)) {
                CLI::write("#{$id}: envío pausado por reputación — " . $rep->status($companyId)['reason'], 'red');
                continue;
            }

            // Cupo del plan y tope de la corrida: manda el más pequeño de los dos.
            $lote = min(MailerService::BATCH, $max - $hechos);
            $left = $plan->emailsLeft($companyId);
            if ($left !== null) {
                if ($left <= 0) {
                    CLI::write("#{$id}: cupo mensual del plan agotado — se salta.", 'yellow');
                    continue;
                }
                $lote = min($lote, $left);
            }

            $res     = $svc->processBatch($id, $lote);
            $hechos += (int) $res['processed'];

            CLI::write(sprintf(
                '#%d %s: %d enviados, %d fallidos, quedan %d.',
                $id,
                $c['name'],
                $res['sent'],
                $res['failed'],
                $res['remaining']
            ), $res['failed'] > 0 ? 'yellow' : 'green');
        }

        CLI::write("Listo. {$hechos} correos procesados.", 'green');

        return 0;
    }
}
