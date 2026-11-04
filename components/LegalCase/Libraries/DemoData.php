<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\CalendarDayModel;
use Components\LegalCase\Models\ContactModel;
use Components\LegalCase\Models\DeadlineModel;
use Components\LegalCase\Models\DocumentModel;
use Components\LegalCase\Models\MatterModel;
use Components\LegalCase\Models\ParticipantModel;
use Components\LegalCase\Models\PortalAccountModel;
use Components\LegalCase\Models\SettingsModel;
use Components\LegalCase\Models\ThreadModel;
use Components\LegalCase\Models\UpdateModel;

/**
 * Datos de ejemplo para recorrer el componente de punta a punta: cinco asuntos con sus
 * procesos, actuaciones, términos, audiencias, documentos, actualizaciones, mensajes,
 * contrato a firma y portal del cliente.
 *
 * Tres reglas, las mismas de los demás componentes:
 *
 *  1. **Solo en un despacho vacío** (sin ningún asunto), comprobado aquí y no en la
 *     pantalla: los datos demo no pueden mezclarse con casos reales.
 *  2. **Todo entra por los servicios** (`MatterService`, `DeadlineService`, `UpdateService`,
 *     `Portal`…) con dos personas reales del despacho, así que numeración, conflicto de
 *     interés, capacidad legal, bitácora y alertas se comportan como en el uso normal. Lo
 *     único que se inserta a mano son los archivos, porque `DocumentService::store()` pide
 *     un `UploadedFile` de una petición HTTP.
 *  3. **No sale ningún correo.** Los contactos usan el dominio reservado `.invalid` y el
 *     `Outbox` lleva un remitente que no envía: los avisos quedan registrados como lo
 *     harían, pero nadie los recibe. El acceso al portal se entrega como enlaces de
 *     invitación (72 h) que devuelve `run()`.
 *
 * Deja a propósito cosas PENDIENTES para que quien valida las resuelva desde la interfaz:
 * un término crítico con una sola validación, una actualización esperando aprobación, un
 * mensaje del cliente sin responder, un documento por entregar y un contrato por firmar.
 */
class DemoData
{
    public const DOMAIN = 'demo-legalcase.invalid';

    private int $cid;
    private Access $lead;
    private Access $second;
    private Outbox $outbox;
    private string $dir;
    /** @var list<array{who:string,email:string,url:string}> */
    private array $links = [];
    /** @var list<string> archivos escritos en esta pasada, para retirarlos si se deshace la transacción */
    private array $files = [];

    /**
     * Enlaces nuevos para las cuentas demo del portal (las invitaciones caducan a las 72 h
     * y son de un solo uso). Solo toca cuentas con el dominio demo: nunca emite un acceso
     * para la cuenta de un cliente real.
     *
     * @return list<array{who:string,email:string,url:string}>
     */
    public static function reissueLinks(int $companyId): array
    {
        $out = [];
        foreach ((new PortalAccountModel())->where('company_id', $companyId)->where('status', 'active')
            ->like('email', '@' . self::DOMAIN, 'before')->findAll() as $acc) {
            $c     = (new ContactModel())->find((int) $acc['contact_id']);
            $out[] = ['who' => (string) ($c['name'] ?? $acc['email']), 'email' => (string) $acc['email'],
                'url' => 'legalcase-portal/entrar/' . (new Portal())->issue($acc, 'invite')];
        }

        return $out;
    }

    /** Si la carga se deshace, los archivos no pueden quedarse huérfanos en disco. */
    public function discardFiles(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        $this->files = [];
    }

    /**
     * @param int $leadId   abogado responsable de la mayoría de asuntos (aprueba y valida)
     * @param int $secondId segundo abogado: redacta, colabora y hace la segunda validación
     */
    public function __construct(int $companyId, private int $leadId, private int $secondId)
    {
        $this->cid    = $companyId;
        $this->lead   = new Access($companyId, $leadId);
        $this->second = new Access($companyId, $secondId);
        // Remitente que NO envía: registra el aviso como entregado al proveedor simulado.
        $this->outbox = new Outbox(static fn (int $c, string $to, string $s, string $h): array => ['ok' => true, 'message' => 'demo: no enviado']);
        $this->dir    = WRITEPATH . 'uploads/lgc/' . $companyId;
    }

    /** Por qué no se puede sembrar, o null si se puede. */
    public function problem(): ?string
    {
        if ($this->leadId === $this->secondId) {
            return 'Hacen falta dos personas distintas del despacho (aprobar y validar exigen otra persona).';
        }
        Team::flush();
        foreach ([$this->leadId, $this->secondId] as $uid) {
            if (!Team::isMember($this->cid, $uid)) {
                return "El usuario {$uid} no es miembro de la empresa {$this->cid}.";
            }
        }
        if (!$this->lead->can('lgc.share') || !$this->lead->can('lgc.approve')) {
            return "El usuario {$this->leadId} necesita lgc.approve y lgc.share para publicar y compartir.";
        }
        if ((new MatterModel())->withDeleted()->where('company_id', $this->cid)->countAllResults() > 0) {
            return 'El despacho ya tiene asuntos: los datos demo solo se cargan en un despacho vacío.';
        }
        if (!(new PlanGuard())->serviceEnabled($this->cid)) {
            return 'El plan de la empresa no incluye LegalCase.';
        }

        return null;
    }

    /** @return array{ok:bool,message:string,matters?:list<string>,links?:list<array{who:string,email:string,url:string}>} */
    public function run(): array
    {
        if ($p = $this->problem()) {
            return ['ok' => false, 'message' => $p];
        }
        @mkdir($this->dir, 0750, true);

        // Políticas exigentes, para que la doble validación y la revisión por otra persona se vean.
        $settings = new SettingsModel();
        $row      = $settings->forCompanyOrDefault($this->cid);
        $settings->update((int) $row['id'], ['approval_policy' => 'reviewer', 'deadline_policy' => 'dual', 'calendar_mode' => 'judicial', 'portal_enabled' => 1]);
        (new CalendarDayModel())->insert(['company_id' => $this->cid, 'day' => date('Y-m-d', strtotime('next friday +7 days')), 'kind' => 'closure',
            'name' => 'Jornada interna del despacho (demo)', 'created_at' => date('Y-m-d H:i:s')]);

        $numbers = [];
        foreach (['labor', 'civil', 'family', 'administrative', 'closed'] as $step) {
            $numbers[] = $this->{$step}();
        }
        // Una acción del reloj: deja las alertas internas de plazos y audiencias próximas.
        (new Clock($this->outbox))->run($this->cid);

        return ['ok' => true, 'message' => 'Datos demo cargados.', 'matters' => $numbers, 'links' => $this->links];
    }

    // ------------------------------------------------------------------ asuntos

    /** Laboral: el asunto «completo». Portal, contrato a firma, pendientes de aprobar y validar. */
    private function labor(): string
    {
        $ms      = new MatterService();
        $client  = $this->contact('person', 'María Fernanda Ospina Rivera', 'CC', '52814937', 'maria.ospina', '3104567890', 'Bogotá', 'written');
        $company = $this->contact('org', 'Transportes Andinos del Sur S.A.S.', 'NIT', '900458712-3', 'juridica.tandinos', '6017458800', 'Bogotá');

        $m = $this->matter($ms->create([
            'title'             => 'Ospina vs. Transportes Andinos — despido sin justa causa',
            'public_title'      => 'Su reclamación laboral',
            'specialty'         => 'labor',
            'jurisdiction'      => 'Bogotá D.C.',
            'risk_level'        => 'medium',
            'client_contact_id' => $client,
            'lead_lawyer_id'    => $this->leadId,
            'description'       => 'La clienta trabajó 6 años como coordinadora logística. Fue despedida el 14 de julio de 2026 sin justa causa y sin pago de la indemnización del art. 64 CST. Hay correos que contradicen la versión de la empresa.',
            'objectives'        => 'Indemnización por despido injusto, reliquidación de prestaciones y sanción moratoria del art. 65 CST.',
            'strategy'          => 'Ofrecer conciliación por el 70 % de la indemnización antes de la audiencia del art. 77; si no hay ánimo conciliatorio, insistir en la sanción moratoria, que es lo que más pesa a la empresa.',
            'public_stage'      => 'Primera instancia',
        ], $this->lead));
        $ms->addTeam($m, $this->secondId, 'collaborator', $this->lead);
        $ms->addParticipant($m, $company, 'counterparty', $this->lead);

        $ds   = new DeadlineService();
        $proc = $ds->saveProceeding($m, [
            'kind' => 'judicial', 'filing_number' => '11001310501220260041200', 'authority' => 'Juzgado 12 Laboral del Circuito de Bogotá',
            'jurisdiction' => 'Laboral', 'procedure_type' => 'Ordinario laboral de primera instancia', 'stage' => 'Contestación de la demanda',
            'status' => 'active', 'started_on' => date('Y-m-d', strtotime('-50 days')),
        ], $this->lead)->id;
        $ev = [
            ['-50 days', 'filing', 'Radicación de la demanda', 'Demanda radicada por ventanilla virtual con 14 anexos.', 1],
            ['-31 days', 'ruling', 'Auto admisorio de la demanda', 'El juzgado admite la demanda y ordena notificar a la demandada.', 1],
            ['-24 days', 'notification', 'Notificación personal a la demandada', 'Notificación por correo electrónico (Ley 2213 de 2022).', 0],
            ['-6 days', 'filing', 'La demandada contesta y propone excepciones', 'Excepciones de prescripción y de pago. Se aportan dos testigos.', 1],
        ];
        foreach ($ev as [$when, $kind, $title, $detail, $relevant]) {
            $ds->createEvent($m, ['proceeding_id' => $proc, 'occurred_at' => date('Y-m-d 10:00', strtotime($when)), 'kind' => $kind, 'title' => $title,
                'detail' => $detail, 'source' => 'Consulta de procesos de la Rama Judicial', 'is_relevant' => $relevant], $this->lead);
        }

        // Término calculado (lo propone el calendario y queda «por validar») y uno crítico con UNA sola validación.
        $ds->createDeadline($m, ['title' => 'Pronunciarse sobre las excepciones de la contestación', 'proceeding_id' => $proc,
            'starts_on' => date('Y-m-d', strtotime('-1 day')), 'term_days' => 5, 'term_unit' => 'business', 'is_critical' => 0,
            'basis' => 'Traslado de excepciones.'], $this->lead);
        $crit = $ds->createDeadline($m, ['title' => 'Aportar pruebas documentales antes de la audiencia', 'proceeding_id' => $proc,
            'due_at' => date('Y-m-d 17:00', strtotime('+5 days')), 'is_critical' => 1, 'basis' => 'Art. 77 CPTSS.', 'assigned_to' => $this->secondId], $this->lead);
        $ds->validate((new DeadlineModel())->find((int) $crit->id), $this->lead);

        (new HearingService($this->outbox))->save($m, [
            'title' => 'Audiencia de conciliación, excepciones y fijación del litigio (art. 77 CPTSS)', 'proceeding_id' => $proc,
            'scheduled_at' => date('Y-m-d 09:00', strtotime('+6 days')), 'duration_min' => 120, 'mode' => 'virtual',
            'link' => 'https://audiencias.' . self::DOMAIN . '/j12-laboral', 'client_attendance' => 1, 'is_shared' => 1,
            'client_notes' => 'Conéctese 15 minutos antes con su cédula a la mano. Le llamaremos el día anterior para prepararla.',
            'internal_notes' => 'Llevar el cálculo de la sanción moratoria actualizado al día de la audiencia.',
        ], $this->lead);
        $ds->createTask($m, ['title' => 'Preparar a la clienta para el interrogatorio de parte', 'due_at' => date('Y-m-d 16:00', strtotime('+4 days')),
            'assigned_to' => $this->leadId], $this->lead);

        $this->document($m, 'Demanda ordinaria laboral.pdf', 'shared', ['Demanda ordinaria laboral', 'María Fernanda Ospina Rivera contra Transportes Andinos del Sur S.A.S.', 'Juzgado 12 Laboral del Circuito de Bogotá']);
        $this->document($m, 'Contestación de la demanda.pdf', 'review', ['Contestación de la demanda', 'Transportes Andinos del Sur S.A.S.', 'Excepciones: prescripción y pago']);
        $this->document($m, 'Memorando de estrategia.pdf', 'privileged', ['Memorando interno de estrategia', 'Documento privilegiado: no se comparte con el cliente']);
        $contrato = $this->document($m, 'Contrato de prestación de servicios.pdf', 'shared', ['Contrato de prestación de servicios profesionales', 'Honorarios de cuota litis: 25 % de lo obtenido']);

        $acc = $this->portal($m, $client, 'María Fernanda Ospina (clienta)', false);

        // Una actualización publicada (redacta el segundo abogado, aprueba el responsable) y otra esperando aprobación.
        $us = new UpdateService($this->outbox);
        $d1 = $us->createDraft($m, 'regular', [
            'title' => 'La empresa contestó la demanda', 'stage_label' => 'Primera instancia',
            'what_happened' => 'Transportes Andinos contestó la demanda y se opuso a sus pretensiones.',
            'what_it_means' => 'Es lo normal en este tipo de procesos. Las excepciones que propuso no cambian nuestra posición.',
            'what_we_do' => 'Vamos a pronunciarnos sobre las excepciones y a preparar la audiencia.',
            'client_action' => 'Por ahora no necesita hacer nada. Le pediremos un documento en los próximos días.',
            'verified_source' => 'Consulta de procesos de la Rama Judicial', 'verified_at' => date('Y-m-d H:i', strtotime('-5 days')),
        ], $this->second);
        $this->approveAndPublish((int) $d1->id, $m);
        $d2 = $us->createDraft($m, 'hearing', [
            'title' => 'Fecha de la audiencia', 'stage_label' => 'Primera instancia',
            'what_happened' => 'El juzgado fijó la audiencia de conciliación para dentro de seis días.',
            'what_it_means' => 'Es la primera oportunidad de llegar a un acuerdo con la empresa.',
            'what_we_do' => 'Le llamaremos el día anterior para prepararla.',
            'client_action' => 'Confirme por el portal que puede asistir.',
            'verified_source' => 'Auto que fija fecha', 'verified_at' => date('Y-m-d H:i', strtotime('-1 day')),
        ], $this->second);
        $us->submit((new UpdateModel())->find((int) $d2->id), $this->second);

        $msg = new MessageService($this->outbox);
        if ($acc) {
            $view = new ClientView();
            $pub  = $view->matter($acc, (int) $m['id']);
            $t    = $msg->clientWrites($acc, $pub, null, 'Pregunta sobre la audiencia', 'Buenas tardes. ¿Tengo que ir al juzgado o la audiencia es virtual? ¿Debo llevar algún documento?');
            $msg->staffReplies($m, (new ThreadModel())->find((int) $t->id), 'Buenas tardes, María Fernanda. La audiencia es virtual; le enviaremos el enlace por el portal. Solo necesita su cédula.', 'answered', $this->lead);
            // Este queda SIN responder: aparece en la bandeja con su hora objetivo.
            $msg->clientWrites($acc, $view->matter($acc, (int) $m['id']), null, 'Certificado laboral', 'Encontré un certificado laboral de 2023 firmado por el gerente. ¿Les sirve?');
            $msg->requestDocument($m, ['title' => 'Certificado de ingresos y retenciones 2025', 'account_id' => (int) $acc['id'],
                'instructions' => 'Puede descargarlo del portal de su empleador anterior o pedirlo a recursos humanos.', 'due_at' => date('Y-m-d', strtotime('+7 days'))], $this->lead);

            $es = new EngagementService();
            $es->save($m, ['kind' => 'services_contract', 'fee_type' => 'contingency', 'contingency_pct' => '25', 'currency' => 'COP',
                'scope' => 'Representación judicial en el proceso ordinario laboral de primera y segunda instancia.',
                'exclusions' => 'Recurso extraordinario de casación y procesos ejecutivos posteriores.',
                'starts_on' => date('Y-m-d', strtotime('-55 days')), 'document_id' => (int) $contrato['id']], $this->lead);
            $es->sendForSignature($m, (int) $acc['id'], $this->lead);
        }

        return (string) $m['number'];
    }

    /** Civil: conciliación prejudicial, término que cruza un festivo, «sin novedades» publicado. */
    private function civil(): string
    {
        $ms      = new MatterService();
        $client  = $this->contact('person', 'Carlos Andrés Mejía Duarte', 'CC', '80123456', 'carlos.mejia', '3157894561', 'Medellín', 'contract');
        $counter = $this->contact('org', 'Inmobiliaria Horizonte Urbano Ltda.', 'NIT', '811045632-1', 'contacto.horizonte', '6044441122', 'Medellín');

        $m = $this->matter($ms->create([
            'title' => 'Mejía vs. Inmobiliaria Horizonte — incumplimiento de promesa de compraventa', 'public_title' => 'Su reclamación por el apartamento',
            'specialty' => 'civil', 'jurisdiction' => 'Medellín', 'risk_level' => 'low', 'client_contact_id' => $client, 'lead_lawyer_id' => $this->secondId,
            'description' => 'El cliente pagó $180.000.000 de cuota inicial. La inmobiliaria no entregó el apartamento en la fecha pactada y retiene el dinero.',
            'objectives' => 'Restitución de lo pagado con intereses y la cláusula penal pactada.',
            'strategy' => 'Conciliación prejudicial como requisito de procedibilidad; si fracasa, verbal de mayor cuantía.', 'public_stage' => 'Conciliación prejudicial',
        ], $this->second));
        $ms->addParticipant($m, $counter, 'counterparty', $this->second);

        $ds   = new DeadlineService();
        $proc = $ds->saveProceeding($m, ['kind' => 'other', 'authority' => 'Centro de Conciliación de la Cámara de Comercio de Medellín',
            'procedure_type' => 'Conciliación extrajudicial en derecho', 'stage' => 'Citación', 'status' => 'active', 'started_on' => date('Y-m-d', strtotime('-12 days'))], $this->second)->id;
        $ds->createEvent($m, ['proceeding_id' => $proc, 'occurred_at' => date('Y-m-d 11:00', strtotime('-12 days')), 'kind' => 'filing',
            'title' => 'Solicitud de conciliación radicada', 'is_relevant' => 1, 'source' => 'Radicado del centro de conciliación'], $this->second);
        // 10 días hábiles en modo judicial: el cómputo salta fines de semana, festivos y el día propio del despacho.
        $ds->createDeadline($m, ['title' => 'Término máximo para celebrar la audiencia de conciliación', 'proceeding_id' => $proc,
            'starts_on' => date('Y-m-d'), 'term_days' => 10, 'term_unit' => 'business', 'basis' => 'Ley 2220 de 2022.'], $this->second);

        (new HearingService($this->outbox))->save($m, ['title' => 'Audiencia de conciliación', 'proceeding_id' => $proc,
            'scheduled_at' => date('Y-m-d 15:00', strtotime('+9 days')), 'mode' => 'in_person', 'location' => 'Cámara de Comercio de Medellín, sede El Poblado, sala 4',
            'client_attendance' => 1, 'is_shared' => 1, 'client_notes' => 'Traiga la promesa de compraventa original y los recibos de pago.'], $this->second);

        $this->document($m, 'Promesa de compraventa.pdf', 'shared', ['Promesa de compraventa', 'Apartamento 1203, Torre 2', 'Firmada el 3 de marzo de 2025']);
        $this->document($m, 'Recibos de pago de la cuota inicial.pdf', 'internal', ['Recibos de pago', 'Cuota inicial: $180.000.000']);

        $acc = $this->portal($m, $client, 'Carlos Andrés Mejía (cliente)', true);

        $us = new UpdateService($this->outbox);
        $nn = $us->createDraft($m, 'no_news', [
            'title' => 'Sin novedades esta semana', 'stage_label' => 'Conciliación prejudicial',
            'what_happened' => 'No hubo actuaciones nuevas. El centro de conciliación ya citó a la inmobiliaria.',
            'what_we_do' => 'Seguimos atentos a la confirmación de la audiencia.',
            'verified_source' => 'Llamada al centro de conciliación', 'verified_at' => date('Y-m-d H:i', strtotime('-2 days')),
        ], $this->second);
        $this->approveAndPublish((int) $nn->id, $m, $this->lead);

        if ($acc) {
            // Solicitud de derechos sobre datos personales: plazo legal contado en días hábiles.
            (new MessageService($this->outbox))->dataRightsRequest($acc, (new ClientView())->matter($acc, (int) $m['id']), 'consulta',
                'Quisiera saber qué datos personales míos tiene el despacho y con quién los ha compartido.');
        }

        return (string) $m['number'];
    }

    /** Familia: cliente menor de edad → asunto sensible y restringido; accede la madre con documento. */
    private function family(): string
    {
        $ms     = new MatterService();
        $minor  = $this->contact('person', 'Samuel Gómez Peña', 'TI', '1023456789', '', '', 'Bogotá', 'none', 'minor');
        $mother = $this->contact('person', 'Laura Sofía Peña Castro', 'CC', '1018456321', 'laura.pena', '3201234567', 'Bogotá', 'written');
        $father = $this->contact('person', 'Jorge Iván Gómez Ruiz', 'CC', '79654123', '', '3009876543', 'Chía');

        $m = $this->matter($ms->create([
            'title' => 'Alimentos — Samuel Gómez Peña', 'public_title' => 'Proceso de alimentos de Samuel', 'specialty' => 'family',
            'jurisdiction' => 'Bogotá D.C.', 'risk_level' => 'low', 'lead_lawyer_id' => $this->leadId,
            'description' => 'El padre dejó de aportar la cuota acordada ante la comisaría hace ocho meses.',
            'objectives' => 'Fijación de cuota provisional y definitiva, y pago de lo adeudado.', 'public_stage' => 'Demanda presentada',
        ], $this->lead));
        $ms->addParticipant($m, $minor, 'client', $this->lead);
        $ms->addParticipant($m, $mother, 'guardian', $this->lead);
        $ms->addParticipant($m, $father, 'counterparty', $this->lead);
        $m = (new MatterModel())->find((int) $m['id']);

        $registro = $this->document($m, 'Registro civil de nacimiento.pdf', 'internal', ['Registro civil de nacimiento', 'Samuel Gómez Peña', 'Acredita la representación legal de la madre']);
        $guardian = (new ParticipantModel())->where('matter_id', (int) $m['id'])->where('contact_id', $mother)->first();
        $ms->setAuthority($m, (int) $guardian['id'], ['authority_document_id' => (int) $registro['id'], 'authority_scope' => 'Representación legal del menor (patria potestad)'], $this->lead);

        $ds = new DeadlineService();
        $ds->saveProceeding($m, ['kind' => 'judicial', 'filing_number' => '11001311000520260078900', 'authority' => 'Juzgado 5 de Familia de Bogotá',
            'procedure_type' => 'Verbal sumario de alimentos', 'stage' => 'Admisión', 'status' => 'active', 'started_on' => date('Y-m-d', strtotime('-8 days'))], $this->lead);
        (new HearingService($this->outbox))->save($m, ['title' => 'Audiencia inicial del verbal sumario', 'scheduled_at' => date('Y-m-d 08:30', strtotime('+2 days')),
            'mode' => 'in_person', 'location' => 'Juzgado 5 de Familia de Bogotá, piso 3', 'internal_notes' => 'Pedir la cuota provisional al inicio.'], $this->lead);

        $this->portal($m, $mother, 'Laura Sofía Peña (madre y representante del menor)', false);

        return (string) $m['number'];
    }

    /** Administrativo: cliente persona jurídica que accede por su apoderado, con poder vigente. */
    private function administrative(): string
    {
        $ms     = new MatterService();
        $client = $this->contact('org', 'Constructora Altos del Río S.A.S.', 'NIT', '901234567-8', 'gerencia.altosdelrio', '6018765432', 'La Calera', 'contract');
        $rep    = $this->contact('person', 'Andrés Felipe Rincón Ávila', 'CC', '1020304050', 'andres.rincon', '3176543210', 'Bogotá', 'written');

        $m = $this->matter($ms->create([
            'title' => 'Altos del Río — recurso contra la negación de la licencia de construcción', 'public_title' => 'Licencia de construcción del proyecto Mirador',
            'specialty' => 'administrative', 'jurisdiction' => 'Cundinamarca', 'risk_level' => 'high', 'visibility' => 'team',
            'client_contact_id' => $client, 'lead_lawyer_id' => $this->leadId,
            'description' => 'La curaduría negó la licencia por una supuesta afectación de ronda hídrica que el estudio topográfico desvirtúa.',
            'strategy' => 'Recurso de reposición y, en subsidio, apelación; preparar la nulidad y restablecimiento si se confirma.', 'public_stage' => 'Vía administrativa',
        ], $this->lead));
        $ms->addTeam($m, $this->secondId, 'observer', $this->lead);
        $ms->addParticipant($m, $rep, 'representative', $this->lead);

        $poder = $this->document($m, 'Poder especial.pdf', 'internal', ['Poder especial', 'Otorgado por el representante legal de Constructora Altos del Río S.A.S.']);
        $part  = (new ParticipantModel())->where('matter_id', (int) $m['id'])->where('contact_id', $rep)->first();
        $ms->setAuthority($m, (int) $part['id'], ['authority_document_id' => (int) $poder['id'], 'authority_scope' => 'Actuaciones administrativas del proyecto Mirador',
            'authority_until' => date('Y-m-d', strtotime('+6 months'))], $this->lead);

        $ds   = new DeadlineService();
        $proc = $ds->saveProceeding($m, ['kind' => 'administrative', 'filing_number' => 'RES-1450-2026', 'authority' => 'Curaduría Urbana de La Calera',
            'procedure_type' => 'Recurso de reposición y en subsidio apelación', 'stage' => 'Recurso interpuesto', 'status' => 'active',
            'started_on' => date('Y-m-d', strtotime('-3 days'))], $this->lead)->id;
        $crit = $ds->createDeadline($m, ['title' => 'Vence el término para sustentar la apelación', 'proceeding_id' => $proc, 'is_critical' => 1,
            'starts_on' => date('Y-m-d', strtotime('-3 days')), 'term_days' => 10, 'term_unit' => 'business', 'basis' => 'Art. 76 CPACA.'], $this->lead);
        // Este sí queda validado por las dos personas: muestra el estado final.
        $ds->validate((new DeadlineModel())->find((int) $crit->id), $this->lead);
        $ds->validate((new DeadlineModel())->find((int) $crit->id), $this->second);

        $this->portal($m, $rep, 'Andrés Felipe Rincón (apoderado de la constructora)', true);

        return (string) $m['number'];
    }

    /** Un asunto cerrado, para ver el estado final y el filtro de cerrados. */
    private function closed(): string
    {
        $ms     = new MatterService();
        $client = (int) (new ContactModel())->where('company_id', $this->cid)->where('name', 'Carlos Andrés Mejía Duarte')->first()['id'];
        $m      = $this->matter($ms->create(['title' => 'Mejía — tutela por derecho de petición sin respuesta', 'public_title' => 'Su tutela',
            'specialty' => 'administrative', 'client_contact_id' => $client, 'lead_lawyer_id' => $this->secondId, 'public_stage' => 'Fallo favorable'], $this->second));
        (new DeadlineService())->createEvent($m, ['occurred_at' => date('Y-m-d 12:00', strtotime('-20 days')), 'kind' => 'ruling',
            'title' => 'Fallo de tutela favorable', 'detail' => 'Se ordena responder el derecho de petición en 48 horas.', 'is_relevant' => 1], $this->second);
        $ms->close($m, 'completed', $this->second, true);

        return (string) $m['number'];
    }

    // ------------------------------------------------------------------ piezas

    private function matter(Result $r): array
    {
        if (!$r->ok) {
            throw new \RuntimeException('No se pudo crear el asunto demo: ' . $r->message);
        }

        return (new MatterModel())->find((int) $r->id);
    }

    private function contact(string $kind, string $name, string $docType, string $doc, string $mailbox, string $phone, string $city,
        string $consent = 'none', string $capacity = 'full'): int
    {
        $model = new ContactModel();
        $model->insert([
            'company_id' => $this->cid, 'kind' => $kind, 'name' => $name, 'doc_type' => $docType, 'doc_number' => $doc,
            'email' => $mailbox !== '' ? $mailbox . '@' . self::DOMAIN : '', 'phone' => $phone !== '' ? '57' . $phone : '', 'city' => $city,
            'country' => 'CO', 'locale' => 'es', 'legal_capacity' => $capacity, 'notes' => 'Contacto de los datos demo de LegalCase.',
            'consent_status' => $consent === 'none' ? 'none' : 'granted',
            'consent_at' => $consent === 'none' ? null : date('Y-m-d H:i:s', strtotime('-60 days')),
            // Las columnas de autorización son NOT NULL con '' por defecto: sin autorización van vacías.
            'consent_method' => $consent === 'none' ? '' : $consent, 'consent_version' => $consent === 'none' ? '' : '1',
            'consent_evidence' => $consent === 'none' ? '' : 'Autorización firmada (demo)',
            'created_by' => $this->leadId,
        ]);

        return (int) $model->getInsertID();
    }

    /**
     * Da acceso al portal a una parte y guarda un enlace de invitación (72 h).
     * `$privacy` deja aceptado el aviso de privacidad; sin él, el primer ingreso lo pide.
     */
    private function portal(array $matter, int $contactId, string $who, bool $privacy): ?array
    {
        $part = (new ParticipantModel())->where('matter_id', (int) $matter['id'])->where('contact_id', $contactId)->first();
        $r    = (new Portal())->grant($matter, $part, $this->lead, static fn (): array => ['ok' => true, 'message' => 'demo']);
        if (!$r->ok) {
            throw new \RuntimeException('No se pudo dar acceso al portal a ' . $who . ': ' . $r->message);
        }
        $acc = (new PortalAccountModel())->find((int) $r->id);
        if ($privacy) {
            (new Portal())->acceptPrivacy($acc);
            $acc = (new PortalAccountModel())->find((int) $r->id);
        }
        // Ruta relativa: desde la CLI `base_url()` no conoce el host real; la completa quien llama.
        $this->links[] = ['who' => $who, 'email' => (string) $acc['email'], 'url' => 'legalcase-portal/entrar/' . (new Portal())->issue($acc, 'invite')];

        return $acc;
    }

    private function approveAndPublish(int $updateId, array $matter, ?Access $approver = null): void
    {
        $us       = new UpdateService($this->outbox);
        $approver ??= $this->lead;
        $u        = (new UpdateModel())->find($updateId);
        if ((int) $u['author_id'] === $approver->userId) {
            $approver = $approver === $this->lead ? $this->second : $this->lead;
        }
        $us->submit($u, new Access($this->cid, (int) $u['author_id']));
        $us->approve((new UpdateModel())->find($updateId), $approver);
        $us->publish((new UpdateModel())->find($updateId), $matter, $approver);
    }

    /** Guarda un PDF real (legible al descargarlo) con la misma forma que `DocumentService::store()`. */
    private function document(array $matter, string $name, string $class, array $lines): array
    {
        $bytes = self::pdf($lines);
        $file  = bin2hex(random_bytes(16)) . '.bin';
        file_put_contents($this->dir . '/' . $file, $bytes);
        $this->files[] = $this->dir . '/' . $file;

        $model = new DocumentModel();
        $model->insert([
            'company_id' => $this->cid, 'matter_id' => (int) $matter['id'], 'name' => $name, 'path' => $file, 'mime' => 'application/pdf',
            'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'classification' => $class, 'source' => 'staff',
            'uploaded_by' => $this->leadId, 'version' => 1, 'is_current' => 1,
            'shared_by' => $class === 'shared' ? $this->leadId : null, 'shared_at' => $class === 'shared' ? date('Y-m-d H:i:s') : null,
        ]);
        $id = (int) $model->getInsertID();
        Audit::log($this->cid, (int) $matter['id'], 'document.uploaded', 'document', $id, ['class' => $class], 'user', $this->leadId);

        return (new DocumentModel())->find($id);
    }

    /** PDF mínimo de una página con texto (Helvetica, WinAnsi). */
    public static function pdf(array $lines): string
    {
        $esc = static fn (string $s): string => strtr(mb_convert_encoding($s, 'Windows-1252', 'UTF-8'), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $txt = "BT /F1 18 Tf 72 760 Td (" . $esc((string) array_shift($lines)) . ") Tj /F1 11 Tf";
        foreach ($lines as $l) {
            $txt .= " 0 -22 Td (" . $esc((string) $l) . ") Tj";
        }
        $txt .= " 0 -40 Td (" . $esc('Documento de ejemplo generado por los datos demo de LegalCase.') . ") Tj ET";

        $objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length ' . strlen($txt) . " >>\nstream\n" . $txt . "\nendstream",
        ];
        $out  = "%PDF-1.4\n";
        $offs = [];
        foreach ($objs as $i => $o) {
            $offs[] = strlen($out);
            $out   .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offs as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }

        return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }
}
