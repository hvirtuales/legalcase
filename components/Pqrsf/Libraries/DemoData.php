<?php

namespace Components\Pqrsf\Libraries;

use App\Models\CompanyUserModel;
use Components\Pqrsf\Models\EventModel;
use Components\Pqrsf\Models\PointModel;
use Components\Pqrsf\Models\RequestModel;
use Components\Pqrsf\Models\SettingsModel;
use Components\Pqrsf\Models\TypeModel;

/**
 * Datos de ejemplo para ver el componente funcionando: tres puntos QR y doce
 * solicitudes que cubren los cinco tipos, los cinco estados, anónimas e
 * identificadas, con y sin autorización de comunicaciones, un par vencidas y
 * otro par respondidas dentro del mes (para que las cuatro métricas de la
 * bandeja tengan contenido).
 *
 * Se crean **solo si la empresa aún no tiene solicitudes**, para no ensuciar
 * datos reales, y se borran como cualquier otra solicitud.
 *
 * **No llama a la IA**: el resumen y el borrador de las que los tienen van
 * escritos a mano. Generar datos de prueba no debe gastar cuota del proveedor
 * ni depender de que la IA esté configurada.
 */
class DemoData
{
    /** [nombre, área, visitas] */
    private const POINTS = [
        ['Recepción sede principal', 'Recepción', 138],
        ['Punto de venta · Caja', 'Comercial', 96],
        ['Servicio al cliente', 'Servicio al cliente', 71],
    ];

    /**
     * Cada solicitud:
     * [tipo, asunto, mensaje, estado, punto (0..2), días desde que se radicó,
     *  anónima, autoriza comunicaciones, asignada, días desde la respuesta (null
     *  = sin responder), vencida, con borrador de IA]
     */
    private const REQUESTS = [
        ['reclamo', 'Pedido incompleto: faltó un artículo',
            "Compré tres artículos el sábado y en la caja solo me entregaron dos. Tengo la factura y el comprobante de pago.\n\nNecesito que me entreguen el faltante o me devuelvan el dinero.",
            'en_gestion', 0, 3, false, true, true, null, false, true],

        ['queja', 'Esperé 40 minutos en caja',
            'Había una sola caja abierta un sábado al mediodía. La fila daba la vuelta al pasillo y varias personas se fueron sin comprar.',
            'recibida', 1, 1, false, true, false, null, false, true],

        ['peticion', 'Solicito copia de mi factura de julio',
            'Necesito la factura del mes de julio para presentarla en mi empresa. Mi número de documento está en el formulario.',
            'respondida', 2, 9, false, true, true, 1, false, false],

        ['felicitacion', 'Excelente atención de Marcela en el punto de venta',
            'Quiero destacar la amabilidad y la paciencia de Marcela. Me explicó todas las opciones sin apurarme y me ayudó a elegir. Ojalá todos atendieran así.',
            'cerrada', 0, 13, false, false, true, 11, false, false],

        ['sugerencia', 'Habiliten el pago con QR en las cajas',
            'Muchos clientes ya no cargamos efectivo ni tarjeta. Un código QR en cada caja agilizaría bastante la fila.',
            'recibida', 1, 2, true, false, false, null, false, false],

        ['queja', 'El baño del segundo piso estaba sucio',
            'A media tarde el baño no tenía papel ni jabón y el piso estaba mojado. No vi a nadie del aseo.',
            'cerrada', 0, 21, true, false, true, 18, false, false],

        ['reclamo', 'Me cobraron dos veces el mismo servicio',
            "En el extracto aparecen dos cobros idénticos del mismo día con quince minutos de diferencia. Solo autoricé uno.\n\nAdjunto el extracto del banco.",
            'en_gestion', 2, 26, false, true, true, null, true, false],

        ['peticion', 'Necesito un certificado de la compra para la garantía',
            'El fabricante me pide un certificado con la fecha de compra y el número de serie. ¿Me lo pueden expedir?',
            'recibida', 0, 23, false, false, false, null, true, false],

        ['sugerencia', 'Amplíen el horario los sábados',
            'Cierran a la una de la tarde y para quienes trabajamos entre semana es el único día posible. Con dos horas más sería suficiente.',
            'respondida', 1, 7, false, true, false, 2, false, false],

        ['peticion', 'Información sobre la garantía extendida',
            '¿Cuánto cuesta la garantía extendida y hasta cuándo puedo contratarla después de la compra? No encontré esa información en la página.',
            'respondida', 2, 5, false, true, true, 0, false, true],

        ['queja', 'La aplicación no me deja actualizar mis datos',
            'Cada vez que intento cambiar mi dirección la aplicación se cierra. Ya la reinstalé dos veces.',
            'anulada', 2, 16, false, true, false, null, false, false],

        ['felicitacion', 'Gracias por la entrega tan rápida',
            'Pedí el jueves por la tarde y el viernes a primera hora ya estaba en mi casa, bien empacado. Muy buen servicio.',
            'cerrada', 0, 31, false, true, true, 29, false, false],
    ];

    /** Ciudadanos de ejemplo: [nombre, documento, correo, teléfono, ciudad] */
    private const CITIZENS = [
        ['Ana María Restrepo', '1020304050', 'ana.restrepo@example.test', '573001234567', 'Bogotá'],
        ['Carlos Andrés Peña', '79845612', 'carlos.pena@example.test', '573109876543', 'Medellín'],
        ['Luisa Fernanda Gómez', '52734891', 'luisa.gomez@example.test', '573155551212', 'Cali'],
        ['Jorge Iván Ramírez', '80123456', 'jorge.ramirez@example.test', '573024445566', 'Bucaramanga'],
        ['Paula Andrea Torres', '1015998877', 'paula.torres@example.test', '573187778899', 'Bogotá'],
    ];

    /** Respuestas de ejemplo, por tipo de solicitud. */
    private const ANSWERS = [
        'peticion' => 'Con gusto atendemos su solicitud. Ya generamos el documento y lo enviamos al correo registrado; también puede reclamarlo en el punto de servicio presentando su documento de identidad. Si no le llega en las próximas horas, escríbanos por este mismo medio y lo reenviamos.',
        'queja'    => 'Le ofrecemos disculpas por lo ocurrido. Revisamos el caso con el equipo del área y ya tomamos medidas para que no se repita: ajustamos la programación del personal en las horas de mayor afluencia. Agradecemos que se haya tomado el tiempo de contárnoslo.',
        'reclamo'  => 'Verificamos su caso y le damos la razón. Autorizamos la corrección y usted verá el ajuste reflejado en un plazo máximo de cinco días hábiles. Le pedimos disculpas por el inconveniente y por el tiempo que le tomó reportarlo.',
        'sugerencia' => 'Gracias por la sugerencia. La llevamos al comité de servicio y quedó incluida en el plan de mejoras del próximo trimestre. Le contaremos por este medio cuando esté implementada.',
        'felicitacion' => 'Muchas gracias por escribirnos. Compartimos su mensaje con la persona y con todo el equipo: reconocer lo que se hace bien anima tanto como corregir lo que falla. Nos alegra haberle servido.',
    ];

    /** Resumen y borrador que la IA habría producido (aquí van escritos). */
    private const AI = [
        0  => ['El cliente reclama que le entregaron dos de los tres artículos que compró y pide el faltante o el reembolso.',
            'Lamentamos el inconveniente con su pedido. Verificamos la factura que nos indica y confirmamos que quedó un artículo pendiente de entrega. Estamos coordinando el despacho para las próximas 48 horas y le confirmaremos por este mismo medio en cuanto salga. Si prefiere el reembolso, podemos tramitarlo en su lugar; solo indíquenos cuál opción prefiere.'],
        1  => ['El cliente esperó 40 minutos en la fila un sábado porque solo había una caja abierta.',
            'Le ofrecemos disculpas por la espera. Revisamos la programación del personal para los sábados al mediodía, que es la franja de mayor afluencia, y vamos a reforzarla. Agradecemos que nos lo haya contado: es la única forma de detectar estos casos a tiempo.'],
        9  => ['El cliente pregunta el costo de la garantía extendida y el plazo para contratarla después de la compra.',
            'Con gusto le ampliamos la información. La garantía extendida se puede contratar dentro de los 30 días siguientes a la compra y su costo depende del valor del producto. Si nos indica la referencia o el número de la factura, le enviamos la cotización exacta.'],
    ];

    /** ¿La empresa está sin solicitudes? El botón solo aparece si sí. */
    public function isEmpty(int $companyId): bool
    {
        return (new RequestModel())->where('company_id', $companyId)->countAllResults() === 0;
    }

    /** Cuántas solicitudes va a crear (para comprobar el cupo del plan antes). */
    public function size(): int
    {
        return count(self::REQUESTS);
    }

    /**
     * Crea los puntos y las solicitudes de ejemplo, cada una con su hilo.
     *
     * @return array{points:int,requests:int}
     */
    public function generate(int $companyId, int $userId): array
    {
        $db       = \Config\Database::connect();
        $points   = new PointModel();
        $requests = new RequestModel();
        $events   = new EventModel();
        $settings = new SettingsModel();

        $cfg = $settings->forCompany($companyId);

        // Los tipos son datos de la empresa: hay que asegurarse de que existan
        // antes de radicar nada con sus códigos.
        $types = new TypeModel();
        $types->forCompany($companyId);

        // ------------------------------------------------------------ puntos
        $pointIds = [];

        foreach (self::POINTS as [$name, $area, $visits]) {
            $ok = $points->insert([
                'company_id' => $companyId,
                'name'       => $name,
                'area'       => $area,
                'notes'      => 'Punto de ejemplo. Puedes editarlo, imprimir su afiche o eliminarlo.',
                'token'      => bin2hex(random_bytes(16)),
                'visits'     => $visits,
                'is_active'  => 1,
            ]);
            $pointIds[] = $ok ? (int) $points->getInsertID() : null;
        }

        // Se reservan de una vez todos los radicados, igual que `nextNumber()`
        // pero en un solo viaje: la demo no puede pisarse con una radicación real.
        $db->transStart();
        $cfg    = $settings->forCompany($companyId);
        $prefix = trim((string) $cfg['prefix']) ?: 'PQR';
        $start  = (int) $cfg['last_number'];
        $db->table('cmp_pqr_settings')->where('company_id', $companyId)
            ->update(['last_number' => $start + count(self::REQUESTS)]);
        $db->transComplete();

        $agents = $this->agents($companyId, $userId);
        $made   = 0;

        foreach (self::REQUESTS as $i => $r) {
            [$type, $subject, $message, $status, $pointIdx, $daysAgo, $anon, $comms, $assigned, $answeredAgo, $late, $withAi] = $r;

            $pointId  = $pointIds[$pointIdx] ?? null;
            $citizen  = self::CITIZENS[$i % count(self::CITIZENS)];
            $created  = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days +" . (8 + $i % 9) . ' hours'));
            $assignee = $assigned ? $agents[$i % count($agents)] : null;

            // Vencida = la fecha límite ya pasó; si no, se calcula con los días
            // del tipo, igual que en una radicación real.
            $due = $late
                ? date('Y-m-d H:i:s', strtotime($created . ' +' . max(1, $daysAgo - 12) . ' days'))
                : date('Y-m-d H:i:s', strtotime($created . ' +' . $types->daysFor($companyId, $type) . ' days'));

            $answeredAt = $answeredAgo === null ? null : date('Y-m-d H:i:s', strtotime("-{$answeredAgo} days +14 hours"));
            $answer     = $answeredAgo === null ? null : self::ANSWERS[$type];

            $ok = $requests->insert([
                'company_id'   => $companyId,
                'point_id'     => $pointId,
                'number'       => $prefix . '-' . str_pad((string) ($start + $i + 1), 6, '0', STR_PAD_LEFT),
                'ticket_token' => bin2hex(random_bytes(16)),
                'type'         => $type,
                'subject'      => $subject,
                'message'      => $message,
                'priority'     => $type === 'reclamo' ? 'alta' : 'media',
                'status'       => $status,
                'assignee_id'  => $assignee['id'] ?? null,
                'due_at'       => $due,
                // En anónima no hay datos del ciudadano: es la regla del componente.
                'is_anonymous'   => $anon ? 1 : 0,
                'citizen_name'   => $anon ? '' : $citizen[0],
                'citizen_doc'    => $anon ? '' : $citizen[1],
                'citizen_email'  => $anon ? '' : $citizen[2],
                'citizen_phone'  => $anon ? '' : $citizen[3],
                'citizen_city'   => $anon ? '' : $citizen[4],
                'accept_terms'   => 1,
                'consent_data'   => $anon ? 0 : 1,
                'consent_comms'  => !$anon && $comms ? 1 : 0,
                'consent_at'     => $created,
                'consent_ip'     => '190.85.' . (10 + $i) . '.' . (20 + $i * 3),
                'policy_version' => (string) ($cfg['policy_version'] ?: 'v1'),
                'ai_summary'     => $withAi ? (self::AI[$i][0] ?? null) : null,
                'ai_draft'       => $withAi ? (self::AI[$i][1] ?? null) : null,
                'answer'         => $answer,
                'answered_at'    => $answeredAt,
                'answered_by'    => $answeredAt ? ($assignee['id'] ?? $userId) : null,
                'closed_at'      => $status === 'cerrada' ? $answeredAt : null,
            ]);

            if (!$ok) {
                continue;
            }

            $id = (int) $requests->getInsertID();
            $made++;

            // `created_at` no está en `allowedFields` (lo pone el modelo), así que
            // la fecha real se coloca después: sin esto todo sería de hoy y ni las
            // métricas ni el filtro de vencidas mostrarían nada.
            $db->table('cmp_pqr_requests')->where('id', $id)
                ->update(['created_at' => $created, 'updated_at' => $answeredAt ?: $created]);

            $this->thread($events, $id, [
                'created'  => $created,
                'point'    => $pointId ? self::POINTS[$pointIdx][0] : '—',
                'status'   => $status,
                'assignee' => $assignee,
                'withAi'   => $withAi,
                'answer'   => $answer,
                'answered' => $answeredAt,
                'comms'    => !$anon && $comms,
                'anon'     => $anon,
                'userId'   => $userId,
            ]);
        }

        return ['points' => count(array_filter($pointIds)), 'requests' => $made];
    }

    /**
     * El hilo de una solicitud, en el orden en que habría ocurrido. Las notas
     * internas van privadas: son las que el ciudadano no ve en su ticket.
     */
    private function thread(EventModel $events, int $id, array $c): void
    {
        $at = static fn (string $base, string $offset) => date('Y-m-d H:i:s', strtotime($base . ' ' . $offset));

        $rows = [];

        $rows[] = ['estado', lang('Pqr.evCreated', [$c['point']]), ['status' => 'recibida'], false, null, $c['created']];

        if ($c['withAi']) {
            $rows[] = ['nota', lang('Pqr.evAiDraft'), ['ai' => true], true, null, $at($c['created'], '+2 minutes')];
        }

        // El acuse solo sale si el ciudadano se identificó y autorizó comunicaciones.
        if ($c['comms']) {
            $rows[] = ['envio', lang('Pqr.evAckOk', [lang('Pqr.ch_email'), '']), ['channel' => 'email', 'ack' => true, 'ok' => true],
                true, null, $at($c['created'], '+3 minutes')];
        }

        if ($c['assignee']) {
            $rows[] = ['asignacion', lang('Pqr.evAssigned', [$c['assignee']['name']]), ['assignee_id' => $c['assignee']['id']],
                true, $c['userId'], $at($c['created'], '+4 hours')];
            $rows[] = ['estado', lang('Pqr.evStatus', [lang('Pqr.status_en_gestion')]), ['status' => 'en_gestion'],
                false, $c['userId'], $at($c['created'], '+4 hours')];
        }

        if ($c['status'] === 'anulada') {
            $rows[] = ['nota', 'Duplicada: el mismo caso llegó por el canal telefónico y ya está en gestión allí.', [],
                true, $c['userId'], $at($c['created'], '+1 day')];
        }

        if ($c['answer']) {
            $rows[] = ['respuesta', $c['answer'], [], false, $c['userId'], $c['answered']];
            $rows[] = ['envio',
                lang($c['comms'] ? 'Pqr.evSentOk' : 'Pqr.evSentFail', [lang('Pqr.ch_email'), $c['comms'] ? '' : lang($c['anon'] ? 'Pqr.chBlockedAnonymous' : 'Pqr.chBlockedConsent')]),
                ['channel' => 'email', 'ok' => $c['comms']], true, $c['userId'], $c['answered']];
        }

        if (in_array($c['status'], ['respondida', 'cerrada', 'anulada'], true)) {
            $rows[] = ['estado', lang('Pqr.evStatus', [lang('Pqr.status_' . $c['status'])]), ['status' => $c['status']],
                false, $c['userId'], $c['answered'] ?: $at($c['created'], '+2 days')];
        }

        foreach ($rows as [$type, $body, $meta, $private, $userId, $when]) {
            $events->insert([
                'request_id' => $id,
                'user_id'    => $userId,
                'type'       => $type,
                'body'       => $body,
                'meta'       => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'is_private' => $private ? 1 : 0,
                'created_at' => $when,
            ]);
        }
    }

    /**
     * Gestores a los que repartir las solicitudes: los miembros de la empresa y,
     * si no hubiera ninguno, quien pulsó el botón.
     *
     * @return list<array{id:int,name:string}>
     */
    private function agents(int $companyId, int $userId): array
    {
        $out = [];

        foreach ((new CompanyUserModel())->getUsers($companyId) as $u) {
            $out[] = [
                'id'   => (int) $u['id'],
                'name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['username'] ?? '#' . $u['id']),
            ];
        }

        return $out ?: [['id' => $userId, 'name' => '#' . $userId]];
    }
}
