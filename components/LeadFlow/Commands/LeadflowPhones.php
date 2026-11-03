<?php

namespace Components\LeadFlow\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\LeadFlow\Libraries\Dedupe;
use Components\LeadFlow\Libraries\IntegrationService;
use Components\LeadFlow\Libraries\Timeline;
use Components\LeadFlow\Models\ExtContactModel;
use Components\LeadFlow\Models\IntegrationLogModel;
use Components\LeadFlow\Models\IntegrationModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\MappingModel;

/**
 * `php spark leadflow:phones [--company=N] [--dry-run]`
 *
 * Completa en Docketwise el teléfono de los contactos que se crearon sin él.
 *
 * Existió un fallo con dos mitades: al **leer** los contactos se miraba solo
 * `lead_phone` —que únicamente traen los marcados como lead—, y al **crearlos** se
 * enviaba `number` suelto, sin el objeto `data` que Docketwise guarda al lado. Las
 * dos están corregidas, pero los contactos ya creados se quedaron sin número, y sin
 * número no se detectan duplicados por teléfono la próxima vez.
 *
 * **Es idempotente y solo añade.** Sincroniza la caché de contactos, compara con el
 * teléfono del lead y toca únicamente los que están vacíos del otro lado: volver a
 * ejecutarlo no repite nada. Con `--dry-run` no escribe: solo dice qué haría.
 *
 * Deja rastro donde se mira cuando algo sale mal: una línea por contacto en la
 * bitácora de la integración y otra en la línea de tiempo del lead.
 */
class LeadflowPhones extends BaseCommand
{
    protected $group       = 'LeadFlow';
    protected $name        = 'leadflow:phones';
    protected $description = 'Completa en Docketwise el teléfono de los contactos que se crearon sin él.';
    protected $options     = [
        '--company' => 'Solo esta empresa (por defecto, todas las conectadas)',
        '--dry-run' => 'No escribe: enseña lo que haría',
    ];

    public function run(array $params)
    {
        $only = (int) (CLI::getOption('company') ?? 0);
        $dry  = (bool) CLI::getOption('dry-run');

        $q = (new IntegrationModel())->where('provider', IntegrationService::PROVIDER)->where('status', 'connected');
        if ($only) {
            $q->where('company_id', $only);
        }
        $rows = $q->findAll();

        if (! $rows) {
            CLI::write('No hay ninguna firma con la cuenta conectada.', 'yellow');

            return;
        }

        foreach ($rows as $row) {
            $this->company((int) $row['company_id'], $dry);
        }
    }

    private function company(int $cid, bool $dry): void
    {
        $svc = new IntegrationService();
        CLI::write('Empresa ' . $cid, 'yellow');

        // La caché es la única forma de saber qué tiene ya teléfono: la API no busca
        // por él, así que primero se refresca. (Ya lee `phone_numbers`.)
        $sync = $svc->syncContacts($cid);
        if (! $sync['ok']) {
            CLI::error('  no se pudieron sincronizar los contactos: ' . $sync['message']);

            return;
        }
        CLI::write('  contactos en la cuenta: ' . (int) $sync['count']);

        $ext = [];
        foreach ((new ExtContactModel())->where('company_id', $cid)->where('provider', IntegrationService::PROVIDER)->findAll() as $c) {
            $ext[(string) $c['external_id']] = (string) ($c['phone_norm'] ?? '');
        }

        $maps  = (new MappingModel())->where('company_id', $cid)->where('provider', IntegrationService::PROVIDER)
            ->where('entity_type', 'contact')->findAll();
        $leads = new LeadModel();
        $logs  = new IntegrationLogModel();

        $done = $skip = $fail = $alien = 0;

        foreach ($maps as $m) {
            $lead  = $leads->find((int) $m['local_id']);
            $phone = $lead ? trim((string) ($lead['phone'] ?: $lead['whatsapp'] ?: '')) : '';
            $extId = (string) $m['external_id'];

            if ($phone === '') {
                $skip++;
                continue;
            }

            /*
             * **Solo se toca lo que está en la cuenta conectada.** Un mapeo puede
             * apuntar a un id de OTRA cuenta: los que se crearon en modo simulado
             * llevan ids pequeños (46, 47…) que en la cuenta real pertenecen a otra
             * persona. Sin esta comprobación, «reparar un teléfono» podía escribir
             * en el contacto de un desconocido. La caché acaba de sincronizarse, así
             * que es la foto de lo que de verdad hay ahí.
             */
            if (! array_key_exists($extId, $ext)) {
                CLI::write('  omitido  Contact #' . $extId . ' no está en esta cuenta (mapeo de otro modo o de otra cuenta)', 'yellow');
                $alien++;
                continue;
            }
            // Ya tiene número allí: no se toca, aunque sea otro. El de Docketwise lo
            // pudo corregir el abogado y no es esta tarea la que decide cuál vale.
            if (($ext[$extId] ?? '') !== '') {
                $skip++;
                continue;
            }

            $name = trim((string) $lead['first_name'] . ' ' . (string) $lead['last_name']);
            if ($dry) {
                CLI::write('  [dry-run] Contact #' . $extId . '  ' . $name . '  ← ' . $phone);
                $done++;
                continue;
            }

            try {
                $svc->adapter($cid)->updateContact($extId, ['phone' => $phone]);
                $logs->log($cid, IntegrationService::PROVIDER, 'update_contact', true,
                    lang('Lfl.logPhoneFixed', [$extId]), (int) $lead['id']);
                Timeline::log($cid, (int) $lead['id'], 'sync', lang('Lfl.tlPhoneFixed', [$extId]));
                CLI::write('  OK  Contact #' . $extId . '  ' . $name . '  ← ' . $phone, 'green');
                $done++;
            } catch (\Throwable $e) {
                $logs->log($cid, IntegrationService::PROVIDER, 'update_contact', false,
                    mb_substr($e->getMessage(), 0, 200), (int) $lead['id']);
                CLI::error('  FALLA Contact #' . $extId . ': ' . $e->getMessage());
                $fail++;
            }

            // El límite es de 120 peticiones por minuto: media décima entre llamadas
            // sobra para no acercarse, y una reparación no tiene prisa.
            if ($svc->adapter($cid)->mode() === 'live') {
                usleep(500000);
            }
        }

        CLI::write(sprintf('  %s: %d · sin cambios: %d · fuera de esta cuenta: %d · con error: %d',
            $dry ? 'se repararían' : 'reparados', $done, $skip, $alien, $fail), $fail ? 'red' : 'green');

        if (! $dry && $done > 0) {
            // La caché se queda vieja en el mismo momento en que se escribe fuera.
            $svc->syncContacts($cid);
            CLI::write('  caché de contactos actualizada');
        }
    }
}
