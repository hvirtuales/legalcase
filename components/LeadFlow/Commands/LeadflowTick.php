<?php

namespace Components\LeadFlow\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\LeadFlow\Libraries\AutomationEngine;
use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Libraries\ScoringService;
use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\IntegrationModel;
use Components\LeadFlow\Models\LeadModel;

/**
 * `php spark leadflow:tick [--company=N]`
 *
 * Lo que depende del paso del tiempo y no de un clic:
 *  · recalcula el score de los leads abiertos (el criterio «sin respuesta en N días»
 *    se cumple solo, sin que nadie toque el lead);
 *  · renueva los tokens de Docketwise a los que les quedan menos de 7 días.
 *
 * No hay Redis ni colas en la plataforma: se programa con el Programador de tareas
 * de Windows o con cron (una vez por hora basta). Sin programarlo el componente
 * funciona igual; el score «sin respuesta» se aplica entonces al tocar el lead.
 */
class LeadflowTick extends BaseCommand
{
    protected $group       = 'LeadFlow';
    protected $name        = 'leadflow:tick';
    protected $description = 'Recalcula los scores por tiempo y renueva los tokens de Docketwise próximos a vencer.';
    protected $options     = ['--company' => 'Solo esta empresa'];

    public function run(array $params)
    {
        $only = (int) (CLI::getOption('company') ?? 0);

        $q = (new LeadModel())->select('id, company_id')->whereIn('status', ['open', 'won']);
        if ($only) {
            $q->where('company_id', $only);
        }

        $svc = new ScoringService();
        $n   = 0;
        foreach ($q->findAll(20000) as $l) {
            $svc->recalculate((int) $l['id'], (int) $l['company_id'], 'tick');
            $n++;
        }
        CLI::write("Scores recalculados: {$n}", 'green');

        // Las oportunidades abiertas que nadie reclamó a tiempo: también las cierra el
        // reloj y no una pantalla, igual que «actividad vencida».
        $cerradas = (new \Components\LeadFlow\Libraries\Directory\OpportunityService())->expire();
        CLI::write("Oportunidades vencidas cerradas: {$cerradas}", 'green');

        // «Actividad vencida» (§19) es el único disparador que no lo provoca nadie:
        // lo provoca el reloj. Se dispara aquí y el UNIQUE de `ref` («activity:<id>»)
        // impide que la misma actividad avise en cada pasada.
        $acts = (new ActivityModel())->where('status', 'pending')
            ->where('due_at IS NOT NULL', null, false)
            ->where('due_at <', date('Y-m-d H:i:s'));
        if ($only) {
            $acts->where('company_id', $only);
        }
        $leads = new LeadModel();
        $a     = 0;
        foreach ($acts->orderBy('due_at', 'asc')->findAll(5000) as $act) {
            $lead = $leads->find((int) $act['lead_id']);
            if ($lead) {
                AutomationEngine::fire('activity_overdue', $lead, ['ref' => 'activity:' . (int) $act['id'], 'activity_id' => (int) $act['id']]);
                $a++;
            }
        }
        CLI::write("Actividades vencidas revisadas: {$a}", 'green');

        $ints = (new IntegrationModel())->where('status', 'connected');
        if ($only) {
            $ints->where('company_id', $only);
        }
        $int = new IntegrationService();
        $r   = 0;
        foreach ($ints->findAll() as $row) {
            $int->refreshIfNeeded($row);
            $r++;
        }
        CLI::write("Conexiones revisadas: {$r}", 'green');

        /*
         * Los casos de USCIS de los leads activos.
         *
         * **Con cupo y con tope por pasada.** Una firma con 400 leads abiertos no puede
         * gastarse media cuota diaria en un solo tick: se refrescan los más antiguos
         * sin mirar, hasta el tope, y el resto le toca en la siguiente pasada. Así el
         * cupo se reparte en el día en lugar de agotarse a las 3 de la mañana.
         *
         * El disparador salta solo cuando USCIS dice algo DISTINTO de lo que ya
         * sabíamos: refrescar y encontrar lo mismo no es un acontecimiento.
         */
        $casos = (new \Components\LeadFlow\Libraries\Uscis\UscisService())->refreshLeads($only ?: null);
        CLI::write("Casos de USCIS revisados: {$casos['checked']} · con cambio: {$casos['changed']}", 'green');

        /*
         * La caché de estados de USCIS guarda recibos y trámites de personas. Se
         * purga lo que nadie consulta hace un mes: existe para ahorrar llamadas, no
         * para archivar el historial migratorio de quien pasó una vez por la página
         * de una firma.
         */
        $purgados = (new \Components\LeadFlow\Models\CaseStatusModel())->purgeStale();
        CLI::write("Estados de caso purgados: {$purgados}", 'green');

        /*
         * Plazos de los expedientes.
         *
         * `next_deadline_at` está desnormalizado para que el listado pueda ordenar por
         * plazo sin dos subconsultas por fila, y como todo rollup se desfasa solo: un
         * requisito que vence hoy no cambia ninguna fila, así que nadie recalcula. Esto
         * lo repasa una vez por pasada.
         *
         * NO dispara avisos propios: los plazos son actividades de tipo `deadline`, y
         * de avisar ya se encarga `activity_overdue` unas líneas más arriba. Un segundo
         * recordatorio sobre lo mismo sería un correo duplicado.
         */
        $matters = (new \Components\LeadFlow\Models\MatterModel())
            ->select('cmp_lfl_matters.id')
            ->whereIn('cmp_lfl_matters.status', ['active', 'on_hold']);
        if ($only > 0) {
            $matters->where('cmp_lfl_matters.company_id', $only);
        }

        $svcMatters = new \Components\LeadFlow\Libraries\MatterService();
        $tocados    = 0;
        foreach ($matters->findAll(5000) as $m) {
            $svcMatters->refreshDeadline((int) $m['id']);
            $tocados++;
        }
        CLI::write("Plazos de expedientes revisados: {$tocados}", 'green');
    }
}
