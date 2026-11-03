<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\ActivityModel;
use Components\LeadFlow\Models\AutomationModel;
use Components\LeadFlow\Models\CampaignModel;
use Components\LeadFlow\Models\CommunicationModel;
use Components\LeadFlow\Models\FormModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\NoteModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;
use Components\LeadFlow\Models\StageModel;
use Components\LeadFlow\Models\SubmissionModel;

/**
 * Datos de ejemplo para ver el flujo completo sin teclear: dos campañas, un
 * formulario de precalificación con lógica condicional (el ejemplo del §13), doce
 * leads repartidos por el pipeline con sus actividades (algunas vencidas),
 * consultas (una hoy), notas, comunicaciones y respuestas — y una lead ganada,
 * «María López», lista para convertir, cuyo email coincide con un contacto de la
 * cuenta simulada de Docketwise para ver la detección de duplicados.
 *
 * Solo corre con la firma **vacía** (`isEmpty()`, comprobado aquí y no en la
 * vista) y pasa por `LeadService::create()`, así que respeta el plan.
 */
class DemoData
{
    public static function isEmpty(int $companyId): bool
    {
        return (new LeadModel())->withDeleted()->where('company_id', $companyId)->countAllResults() === 0;
    }

    public function run(int $companyId, ?int $userId): array
    {
        if (! self::isEmpty($companyId)) {
            return ['ok' => false, 'message' => lang('Lfl.demoNotEmpty')];
        }
        Catalog::ensure($companyId);

        // Los leads de ejemplo son fabricados: **no deben disparar las
        // automatizaciones de la firma** (§19). Si lo hicieran, cargar datos demo
        // le mandaría un correo de bienvenida a una dirección inventada y llenaría
        // la bitácora de ejecuciones que nadie provocó. Se apagan mientras dura la
        // siembra, y se vuelven a encender pase lo que pase.
        AutomationEngine::disable();

        try {
            return $this->seed($companyId, $userId);
        } finally {
            AutomationEngine::enable();
        }
    }

    private function seed(int $companyId, ?int $userId): array
    {
        $this->runAutomations($companyId, $userId);

        $sources  = new SourceModel();
        $meta     = $sources->byKind($companyId, 'meta');
        $google   = $sources->byKind($companyId, 'google');
        $referral = $sources->byKind($companyId, 'referral');
        $whats    = $sources->byKind($companyId, 'whatsapp');

        $camps = new CampaignModel();
        $camps->insert(['company_id' => $companyId, 'source_id' => $meta['id'] ?? null, 'name' => lang('Lfl.demoCampaign1'),
            'utm_campaign' => 'fb_visas_familia', 'budget' => 1200, 'starts_on' => date('Y-m-01'), 'is_active' => 1]);
        $c1 = (int) $camps->getInsertID();
        $camps->insert(['company_id' => $companyId, 'source_id' => $google['id'] ?? null, 'name' => lang('Lfl.demoCampaign2'),
            'utm_campaign' => 'g_asilo', 'budget' => 800, 'starts_on' => date('Y-m-01'), 'is_active' => 1]);
        $c2 = (int) $camps->getInsertID();

        $services = array_flip((new ServiceModel())->options($companyId));
        $svc      = static fn (string $key) => $services[lang('Lfl.' . $key)] ?? null;

        // El formulario del ejemplo del §13, con su lógica condicional.
        $engine = new FormEngine();
        $schema = $engine->sanitize([
            ['id' => 'f_inus', 'type' => 'yesno', 'label' => lang('Lfl.demoQInUs'), 'required' => true],
            ['id' => 'f_status', 'type' => 'select', 'label' => lang('Lfl.demoQStatus'), 'required' => true,
                'options' => [lang('Lfl.demoOptTourist'), lang('Lfl.demoOptStudent'), lang('Lfl.demoOptNoInspection'), lang('Lfl.demoOptOther')],
                'rule' => ['field' => 'f_inus', 'op' => 'equals', 'value' => 'yes']],
            ['id' => 'f_country', 'type' => 'country', 'label' => lang('Lfl.demoQCountry'), 'required' => true,
                'rule' => ['field' => 'f_inus', 'op' => 'equals', 'value' => 'no']],
            ['id' => 'f_relative', 'type' => 'select', 'label' => lang('Lfl.demoQRelative'), 'required' => false,
                'options' => [lang('Lfl.demoOptSpouse'), lang('Lfl.demoOptParent'), lang('Lfl.demoOptChild'), lang('Lfl.demoOptSibling'), lang('Lfl.demoOptNone')]],
            ['id' => 'f_story', 'type' => 'textarea', 'label' => lang('Lfl.demoQStory'), 'required' => false],
            ['id' => 'f_docs', 'type' => 'file', 'label' => lang('Lfl.demoQDocs'), 'required' => false],
        ]);
        $forms = new FormModel();
        $forms->insert(['company_id' => $companyId, 'name' => lang('Lfl.demoFormName'), 'description' => lang('Lfl.demoFormDesc'),
            'token' => FormModel::newToken(), 'service_id' => $svc('svcFamilyVisa'), 'campaign_id' => $c1, 'is_public' => 1,
            'status' => 'published', 'schema' => json_encode($schema, JSON_UNESCAPED_UNICODE), 'ask_consultation' => 1,
            'created_by' => $userId]);
        $formId = (int) $forms->getInsertID();

        $people = [
            // nombre, apellido, email, teléfono, país, servicio, fuente, campaña, código de etapa, horas desde el alta, valor
            ['María', 'López', 'maria.lopez@example.com', '+1 305 555 0199', 'US', 'svcFamilyVisa', $meta, $c1, 'won', 240, 3500],
            ['Jorge', 'Castillo', 'jorge.castillo@example.com', '+1 786 555 0133', 'US', 'svcAsylum', $google, $c2, 'consultation_scheduled', 30, 4200],
            ['Lucía', 'Herrera', 'lucia.herrera@example.com', '+52 55 5555 0144', 'MX', 'svcFamilyVisa', $meta, $c1, 'new', 1, 3000],
            ['Pedro', 'Gómez', 'pedro.gomez@example.com', '+1 305 555 0155', 'US', 'svcDeportation', $referral, null, 'qualified', 70, 6000],
            ['Sofía', 'Ramos', 'sofia.ramos@example.com', '+57 300 555 0166', 'CO', 'svcWorkVisa', $google, null, 'contacted', 20, 2500],
            ['Andrés', 'Morales', 'andres.morales@example.com', '+1 407 555 0177', 'US', 'svcAdjustment', $whats, null, 'prequalification', 48, 2800],
            ['Valentina', 'Cruz', 'valentina.cruz@example.com', '+1 305 555 0188', 'US', 'svcCitizenship', $meta, $c1, 'proposal', 160, 1500],
            ['Diego', 'Vargas', 'diego.vargas@example.com', '+502 5555 0111', 'GT', 'svcAsylum', $google, $c2, 'new', 5, 4200],
            ['Camila', 'Rojas', 'camila.rojas@example.com', '+1 212 555 0122', 'US', 'svcResidence', $referral, null, 'consultation_done', 120, 3200],
            ['Mateo', 'Silva', 'mateo.silva@example.com', '+1 305 555 0100', 'US', 'svcConsultation', $whats, null, 'follow_up', 200, 250],
            ['Isabella', 'Mendoza', 'isabella.mendoza@example.com', '+1 832 555 0145', 'US', 'svcFamilyVisa', $meta, $c1, 'lost', 300, 3000],
            ['Tomás', 'Navarro', 'tomas.navarro@example.com', '+1 713 555 0156', 'US', 'svcWorkVisa', $google, null, 'no_response', 400, 2500],
        ];

        $leads   = new LeadService();
        $stages  = new StageModel();
        $model   = new LeadModel();
        $created = 0;
        $team    = array_keys(Team::users($companyId));
        $me      = $userId && in_array($userId, $team, true) ? $userId : ($team[0] ?? null);

        foreach ($people as $i => [$first, $last, $email, $phone, $country, $svcKey, $source, $camp, $code, $hours, $value]) {
            $res = $leads->create($companyId, [
                'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone, 'whatsapp' => $phone,
                'country' => $country, 'preferred_language' => 'es', 'service_id' => $svc($svcKey), 'source_id' => $source['id'] ?? null,
                'campaign_id' => $camp, 'form_id' => $camp === $c1 ? $formId : null, 'potential_value' => $value,
                'assigned_user_id' => $i % 3 === 2 ? null : $me, 'requested_consultation' => $i % 2 === 0 ? 1 : 0,
                'summary' => lang('Lfl.demoSummary' . ($i % 3)),
            ], ['channel' => 'demo', 'force' => true, 'user_id' => $userId]);

            if (! $res['ok']) {
                break; // plan agotado o servicio desactivado
            }
            $id = (int) $res['id'];
            $created++;

            // Fechas creíbles: sin esto todo sería «de hoy» y ni el Kanban ni los
            // reportes tendrían nada que enseñar.
            $createdAt = date('Y-m-d H:i:s', time() - $hours * 3600);
            $model->skipValidation(true)->update($id, ['created_at' => $createdAt, 'stage_entered_at' => $createdAt]);

            if ($code !== 'new') {
                $lead = $model->find($id);
                // Primer contacto a los pocos minutos (distinto por lead, para el reporte).
                $leads->touchOutbound($companyId, $id, date('Y-m-d H:i:s', strtotime($createdAt) + [4, 22, 45, 150, 400][$i % 5] * 60));
                (new CommunicationModel())->insert(['company_id' => $companyId, 'lead_id' => $id, 'channel' => 'call',
                    'direction' => 'out', 'outcome' => 'answered', 'duration_sec' => 420, 'status' => 'logged', 'created_by' => $userId]);
                if ($i % 2 === 0) {
                    (new CommunicationModel())->insert(['company_id' => $companyId, 'lead_id' => $id, 'channel' => 'whatsapp',
                        'direction' => 'in', 'body' => lang('Lfl.demoInbound'), 'outcome' => 'received', 'status' => 'logged']);
                    $leads->touchInbound($companyId, $id);
                }

                $stage = $stages->byCode($companyId, $code);
                if ($stage) {
                    $lead = $model->find($id);
                    $leads->moveStage($lead, (int) $stage['id'], [
                        'reason' => $code === 'lost' ? 'chose_competitor' : ($code === 'no_response' ? 'no_response' : null),
                        'system' => true,
                    ], $userId);
                }
            }

            if ($camp === $c1) {
                $answers = ['f_inus' => 'yes', 'f_status' => lang('Lfl.demoOptTourist'), 'f_relative' => lang('Lfl.demoOptSpouse'), 'f_story' => lang('Lfl.demoSummary0')];
                (new SubmissionModel())->insert(['company_id' => $companyId, 'lead_id' => $id, 'form_id' => $formId,
                    'answers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
                    'labels' => json_encode(array_column($schema, 'label', 'id'), JSON_UNESCAPED_UNICODE),
                    'is_complete' => $i === 0 ? 1 : 0, 'channel' => 'public', 'created_at' => $createdAt]);
                (new ScoringService())->recalculate($id, $companyId, 'demo');
            }

            $act = new ActivityModel();
            if (in_array($code, ['new', 'contacted', 'prequalification', 'qualified'], true)) {
                $act->insert(['company_id' => $companyId, 'lead_id' => $id, 'assigned_user_id' => $me, 'activity_type' => 'call',
                    'title' => lang('Lfl.demoActCall'), 'due_at' => date('Y-m-d H:i:s', time() + ($i % 2 === 0 ? -86400 : 86400)),
                    'status' => 'pending', 'priority' => 'high', 'created_by' => $userId]);
            }
            if ($code === 'proposal' || $code === 'follow_up') {
                $act->insert(['company_id' => $companyId, 'lead_id' => $id, 'assigned_user_id' => $me, 'activity_type' => 'follow_up',
                    'title' => lang('Lfl.demoActFollow'), 'due_at' => date('Y-m-d H:i:s', time() - 3600 * 5),
                    'status' => 'pending', 'priority' => 'medium', 'created_by' => $userId]);
            }
            $leads->refreshNextActivity($companyId, $id);

            if (in_array($code, ['consultation_scheduled', 'consultation_done', 'won'], true)) {
                $lead = $model->find($id);
                $at   = $code === 'consultation_scheduled' ? date('Y-m-d') : date('Y-m-d', time() - 86400 * 3);
                (new AppointmentService())->schedule($lead, ['date' => $at, 'time' => $code === 'consultation_scheduled' ? '16:00' : '10:00',
                    'mode' => 'video', 'duration_min' => 45, 'lawyer_user_id' => $me, 'location' => 'https://meet.example.com/demo']);
                if ($code !== 'consultation_scheduled') {
                    $appt = (new \Components\LeadFlow\Models\AppointmentModel())->forCompany($companyId)->where('lead_id', $id)->orderBy('id', 'desc')->first();
                    if ($appt) {
                        (new AppointmentService())->complete($appt, $model->find($id), 'likely_hire', null);
                    }
                }
                // El paso por la agenda no debe deshacer la etapa final del ejemplo.
                if ($code === 'won') {
                    $won = $stages->byCode($companyId, 'won');
                    if ($won) {
                        $leads->moveStage($model->find($id), (int) $won['id'], ['system' => true], $userId);
                    }
                }
            }

            if ($i === 0) {
                (new NoteModel())->insert(['company_id' => $companyId, 'lead_id' => $id, 'body' => lang('Lfl.demoNote1'), 'is_transferable' => 1, 'created_by' => $userId]);
                (new NoteModel())->insert(['company_id' => $companyId, 'lead_id' => $id, 'body' => lang('Lfl.demoNote2'), 'is_transferable' => 0, 'created_by' => $userId]);
            }
        }

        return ['ok' => true, 'message' => lang('Lfl.demoCreated', [$created])];
    }

    // ── Automatizaciones de ejemplo (§19) ────────────────────────────────────

    /** ¿La firma no tiene ninguna opinión todavía? */
    public static function isEmptyReviews(int $companyId): bool
    {
        return (new \Components\LeadFlow\Models\ReviewModel())
            ->withDeleted()->where('company_id', $companyId)->countAllResults() === 0;
    }

    /**
     * Opiniones de ejemplo, para ver el módulo de calificación funcionando.
     *
     * **Entran por el mismo camino que una de verdad**: se invita al cliente
     * convertido, se responde su formulario con `submit()` —que exige las tres notas,
     * el consentimiento y el modo de firma— y la plataforma las publica con
     * `moderate()`. Ninguna fila se escribe a mano: si mañana cambia una regla de
     * `ReviewService`, estos datos cambian con ella en vez de quedarse como una
     * excepción que nadie vuelve a mirar.
     *
     * Lo único que el seeder se salta es el reto anti-robot: el captcha y el sello de
     * tiempo defienden de un visitante público, y aquí quien siembra es un usuario
     * autenticado dentro de la aplicación. Se emiten al vuelo, igual que hace el
     * autotest, para no tener que abrir una puerta trasera en el servicio.
     *
     * **Solo clientes convertidos**, porque es la regla del módulo: antes de eso el
     * servicio no ha empezado. Si la firma no tiene ninguno, no se inventa uno.
     */
    public function runReviews(int $companyId, ?int $userId): array
    {
        if (! self::isEmptyReviews($companyId)) {
            return ['ok' => false, 'message' => lang('Lfl.revDemoNotEmpty')];
        }

        $svc = new \Components\LeadFlow\Libraries\Directory\ReviewService();
        if (! $svc->enabledFor($companyId)) {
            return ['ok' => false, 'message' => lang('Lfl.planNoReviews')];
        }

        $leads = $svc->invitableLeads($companyId, 4);
        if ($leads === []) {
            return ['ok' => false, 'message' => lang('Lfl.revDemoNoClients')];
        }

        /*
         * Cuatro respuestas distintas a propósito: una redonda, dos muy buenas con un
         * matiz y una sin comentario. Un demo donde todo el mundo pone cinco no enseña
         * el desglose, que es justo la pieza que hay que ver.
         */
        $guion = [
            ['scores' => [5, 5, 5], 'mode' => 'first', 'key' => 'revDemo1'],
            ['scores' => [5, 4, 5], 'mode' => 'anon',  'key' => 'revDemo2'],
            ['scores' => [4, 4, 4], 'mode' => 'first', 'key' => 'revDemo3'],
            ['scores' => [5, 5, 4], 'mode' => 'first', 'key' => null],
        ];

        $captcha = new \Components\LeadFlow\Libraries\Captcha();
        $t       = time() - 20;
        $sello   = $t . '.' . hash_hmac('sha256', (string) $t, (string) config('Encryption')->key);
        $hechas  = 0;

        foreach ($leads as $i => $lead) {
            $guionado = $guion[$i] ?? $guion[0];

            $inv = $svc->invite($lead);
            if (! $inv['ok'] || empty($inv['id'])) {
                continue;
            }

            $row = (new \Components\LeadFlow\Models\ReviewModel())->find((int) $inv['id']);
            if (! $row) {
                continue;
            }

            [$cl, $re, $ca] = $guionado['scores'];
            $res = $svc->submit($row, [
                'score_clarity'  => $cl,
                'score_response' => $re,
                'score_care'     => $ca,
                'comment'        => $guionado['key'] ? lang('Lfl.' . $guionado['key']) : '',
                'author_mode'    => $guionado['mode'],
                'consent'        => true,
                // El sello se firma a mano con antigüedad suficiente: `stamp()` lo
                // emite siempre con la hora actual y `humanSpeed()` lo rechazaría por
                // demasiado rápido. Es el mismo truco que usa el autotest.
                'stamp'          => $sello,
                'captcha'        => $captcha->issue(),
                // Una IP distinta por opinión: el freno del formulario es por IP y
                // enlace, y cuatro envíos seguidos desde la misma se frenarían solos.
                'ip'             => '203.0.113.' . (10 + $i),
            ]);
            if (! $res['ok']) {
                continue;
            }

            // La plataforma las publica, que es lo que haría un moderador de verdad.
            $svc->moderate((new \Components\LeadFlow\Models\ReviewModel())->find((int) $row['id']), true, $userId);
            $hechas++;
        }

        if ($hechas === 0) {
            return ['ok' => false, 'message' => lang('Lfl.revDemoFailed')];
        }

        return ['ok' => true, 'message' => lang('Lfl.revDemoDone', [$hechas])];
    }

    public static function isEmptyAutomations(int $companyId): bool
    {
        return (new AutomationModel())->withDeleted()->where('company_id', $companyId)->countAllResults() === 0;
    }

    /**
     * Las cuatro automatizaciones de ejemplo del §19, una por disparador distinto:
     * lead creado, formulario completado, score alcanzado y no-show.
     *
     * **Nacen como plantillas para editar, no como algo definitivo**, y por eso la
     * única que habla con el exterior —el correo de bienvenida al prospecto— nace
     * **desactivada**: cargar datos de ejemplo no puede acabar mandándole un correo
     * con texto de relleno a un prospecto real. Las otras tres solo crean trabajo
     * interno (actividades y notas), así que se pueden encender sin riesgo.
     *
     * Pasan por el MISMO saneador que el editor (`AutomationRegistry`), de modo que
     * los datos demo no pueden colarse por una puerta más ancha que la del usuario.
     */
    public function runAutomations(int $companyId, ?int $userId): array
    {
        if (! self::isEmptyAutomations($companyId)) {
            return ['ok' => false, 'message' => lang('Lfl.autDemoNotEmpty')];
        }
        Catalog::ensure($companyId);

        $definitions = [
            [
                'name'    => lang('Lfl.autDemo1Name'),
                'trigger' => 'lead_created',
                'active'  => 1,
                'actions' => [
                    ['type' => 'assign_advisor', 'mode' => 'round_robin'],
                    ['type' => 'create_activity', 'activity_type' => 'call', 'title' => lang('Lfl.autDemoActCall'),
                        'due_in_hours' => 4, 'assign_to' => 'lead_owner'],
                ],
            ],
            [
                'name'    => lang('Lfl.autDemo2Name'),
                'trigger' => 'form_completed',
                'active'  => 0,
                'actions' => [
                    ['type' => 'send_email', 'subject' => lang('Lfl.autDemoMailSubject'), 'body' => lang('Lfl.autDemoMailBody')],
                ],
            ],
            [
                'name'       => lang('Lfl.autDemo3Name'),
                'trigger'    => 'score_reached',
                'active'     => 1,
                'conditions' => [['field' => 'score', 'op' => 'greater_than', 'value' => '69']],
                'actions'    => [
                    ['type' => 'create_activity', 'activity_type' => 'review', 'title' => lang('Lfl.autDemoActReview'),
                        'due_in_hours' => 2, 'assign_to' => 'lead_owner'],
                    ['type' => 'add_note', 'body' => lang('Lfl.autDemoNoteHot')],
                ],
            ],
            [
                'name'    => lang('Lfl.autDemo4Name'),
                'trigger' => 'no_show',
                'active'  => 1,
                'actions' => [
                    ['type' => 'create_activity', 'activity_type' => 'call', 'title' => lang('Lfl.autDemoActReschedule'),
                        'due_in_hours' => 24, 'assign_to' => 'lead_owner'],
                    ['type' => 'add_note', 'body' => lang('Lfl.autDemoNoteNoShow')],
                ],
            ],
        ];

        $registry = new AutomationRegistry();
        $model    = new AutomationModel();
        $sort     = 10;
        $created  = 0;

        foreach ($definitions as $d) {
            $ok = $model->insert([
                'company_id'    => $companyId,
                'name'          => $d['name'],
                'trigger_event' => $d['trigger'],
                'match_mode'    => 'all',
                'conditions'    => json_encode($registry->sanitizeConditions($d['conditions'] ?? []), JSON_UNESCAPED_UNICODE),
                'actions'       => json_encode($registry->sanitizeActions($d['actions'], $companyId), JSON_UNESCAPED_UNICODE),
                'once_per_lead' => 1,
                'is_active'     => $d['active'],
                'sort'          => $sort,
                'created_by'    => $userId,
            ]);
            if ($ok) {
                $created++;
                $sort += 10;
            }
        }

        return ['ok' => true, 'message' => lang('Lfl.autDemoCreated', [$created])];
    }
}
