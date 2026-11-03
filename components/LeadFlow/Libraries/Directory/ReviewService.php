<?php

namespace Components\LeadFlow\Libraries\Directory;

use Components\LeadFlow\Libraries\Captcha;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Libraries\Timeline;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\ReviewModel;

/**
 * Opiniones verificadas: las reglas de la parte regulada del producto.
 *
 * Todo lo que decide quién puede opinar, sobre qué y cuándo se publica vive aquí, porque
 * es lo que hace que el sistema se pueda defender ante una regla de publicidad legal:
 *
 *  1. **Solo opina un cliente real.** La invitación se genera desde un lead **convertido**
 *     —el que llegó a ser cliente en Docketwise— y una sola vez por caso (UNIQUE en
 *     `lead_id`). No hay formulario abierto, así que no hay reseñas de nadie que no fue
 *     atendido, ni de la competencia.
 *  2. **Se valora la atención, no el resultado.** Las tres notas son claridad, respuesta
 *     y trato. Preguntar por el resultado del caso convertiría la media en una promesa
 *     de resultados, que es justo lo que las reglas prohíben insinuar.
 *  3. **Nace pendiente y la publica la PLATAFORMA**, no la firma. Es el punto donde se
 *     frena un texto que afirme resultados, mencione a un tercero o identifique un caso.
 *  4. **La firma no puede editarla ni borrarla: solo responder, una vez.** Si pudiera
 *     quitar las malas, la media sería engañosa — y entonces el problema legal lo
 *     tendría la plataforma.
 *  5. **Nada de incentivos.** No hay ningún camino en el código para premiar una opinión;
 *     pagar por reseñas está expresamente prohibido en varias jurisdicciones.
 *
 * Y un interruptor: `plans.lfl_reviews_enabled`, **apagado por defecto**. Donde los
 * testimonios estén restringidos, las opiniones simplemente no existen.
 */
class ReviewService
{
    public function __construct()
    {
        Taxonomy::ensure();
    }

    private static function ok(string $message, int $id = 0, array $extra = []): array
    {
        return ['ok' => true, 'message' => $message, 'id' => $id] + $extra;
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0];
    }

    /** ¿Esta firma tiene las opiniones habilitadas por su plan? */
    public function enabledFor(int $companyId): bool
    {
        return (new PlanGuard())->reviewsEnabled($companyId);
    }

    // ── 1. La firma invita al cliente ────────────────────────────────────────

    /**
     * Crea (o recupera) la invitación de un lead convertido y devuelve su enlace.
     *
     * **Solo leads convertidos.** Un lead ganado todavía no es cliente: lo es cuando el
     * caso existe en Docketwise, y pedir opinión antes es pedirla por un servicio que no
     * ha empezado.
     *
     * No envía nada: devuelve el enlace para que la firma lo pase por donde ya habla con
     * su cliente. Mandar correos en su nombre es una decisión suya, no del sistema.
     */
    public function invite(array $lead): array
    {
        $cid = (int) $lead['company_id'];

        if (! $this->enabledFor($cid)) {
            return self::fail(lang('Lfl.planNoReviews'));
        }
        if (($lead['status'] ?? '') !== 'converted') {
            return self::fail(lang('Lfl.revNeedConverted'));
        }

        $reviews = new ReviewModel();
        if ($existing = $reviews->ofLead((int) $lead['id'])) {
            // Ya existe: se devuelve el mismo enlace en vez de crear otro. El UNIQUE lo
            // impediría igual, pero así la firma puede volver a copiarlo.
            return self::ok(lang('Lfl.revInviteReady'), (int) $existing['id'], [
                'url'    => $this->url($existing['token']),
                'status' => $existing['status'],
            ]);
        }

        $ok = $reviews->insert([
            'company_id' => $cid,
            'profile_id' => $this->profileFor($lead),
            'lead_id'    => (int) $lead['id'],
            'token'      => bin2hex(random_bytes(32)),
            'status'     => 'pending',
        ]);
        if (! $ok) {
            return self::fail(implode(' ', $reviews->errors() ?: [lang('Lfl.errInvalid')]));
        }

        $id  = (int) $reviews->getInsertID();
        $row = $reviews->find($id);
        Timeline::log($cid, (int) $lead['id'], 'note', lang('Lfl.tlReviewInvited'));

        return self::ok(lang('Lfl.revInviteCreated'), $id, ['url' => $this->url($row['token']), 'status' => 'pending']);
    }

    /**
     * A qué perfil se le acredita la opinión.
     *
     * `leads.profile_id` solo se rellena cuando el prospecto entró **por el perfil
     * público de un abogado**. Un lead creado a mano, importado o llegado por el
     * formulario de la firma no lo tiene, aunque luego lo atienda alguien con perfil
     * publicado: así, la opinión se quedaba sin acreditar y la tarjeta y el perfil de
     * quien hizo el trabajo seguían sin calificación.
     *
     * Por eso, si no hay `profile_id`, se cae al perfil **publicado** del abogado
     * asignado. Si ese abogado no tiene perfil o está en borrador, la opinión queda
     * solo de la firma: acreditarla a un perfil que nadie puede ver no sirve de nada.
     */
    private function profileFor(array $lead): ?int
    {
        if (! empty($lead['profile_id'])) {
            return (int) $lead['profile_id'];
        }
        if (empty($lead['lawyer_user_id'])) {
            return null;
        }

        $perfil = (new \Components\LeadFlow\Models\ProfileModel())
            ->where('company_id', (int) $lead['company_id'])
            ->where('user_id', (int) $lead['lawyer_user_id'])
            ->where('status', 'published')
            ->first();

        return $perfil ? (int) $perfil['id'] : null;
    }

    public function url(string $token): string
    {
        return base_url('opinion/' . rawurlencode($token));
    }

    /**
     * La invitación que corresponde a un token, si todavía se puede llenar.
     *
     * Devuelve `null` también cuando ya está contestada: el 404 no distingue entre «no
     * existe», «ya opinaste» y «el plan no lo incluye», por el mismo criterio que el
     * resto de los enlaces públicos del componente.
     */
    public function pendingByToken(string $token): ?array
    {
        $row = (new ReviewModel())->byToken($token);
        if (! $row || $row['score'] !== null) {
            return null;
        }

        return $this->enabledFor((int) $row['company_id']) ? $row : null;
    }

    // ── 2. El cliente opina ──────────────────────────────────────────────────

    /**
     * Guarda la opinión. Queda **pendiente de revisión**, nunca publicada.
     *
     * @param array $in score_clarity, score_response, score_care, comment, author_mode,
     *                  consent, website, stamp, captcha, ip
     */
    public function submit(array $invite, array $in): array
    {
        $ip = mb_substr((string) ($in['ip'] ?? ''), 0, 45);

        if (! service('throttler')->check('lfl_rev_' . md5($ip . '|' . $invite['token']), 5, MINUTE)) {
            return self::fail(lang('Lfl.pubTooMany'));
        }
        if (trim((string) ($in['website'] ?? '')) !== '') {
            return self::fail(lang('Lfl.pubCaptchaError'));
        }
        $captcha = new Captcha();
        if (! $captcha->humanSpeed((string) ($in['stamp'] ?? ''))) {
            return self::fail(lang('Lfl.pubTooFast'));
        }
        if (extension_loaded('gd') && ! $captcha->check((string) ($in['captcha'] ?? ''))) {
            return self::fail(lang('Lfl.pubCaptchaError'));
        }
        if (empty($in['consent'])) {
            return self::fail(lang('Lfl.pubConsentRequired'));
        }

        // Las tres notas, 1 a 5. Las tres obligatorias: una media sacada de una sola
        // dimensión no dice lo mismo y no sería comparable entre perfiles.
        $scores = [];
        foreach (array_keys(ReviewModel::SCORES) as $field) {
            $v = (int) ($in[$field] ?? 0);
            if ($v < 1 || $v > 5) {
                return self::fail(lang('Lfl.revVScores'));
            }
            $scores[$field] = $v;
        }

        // Cómo firma: lo elige quien opina, y «Cliente verificado» es una opción de
        // verdad — en un asunto legal hay gente que no quiere su nombre publicado.
        $lead = (new LeadModel())->find((int) $invite['lead_id']);
        $mode = in_array($in['author_mode'] ?? '', ['full', 'first', 'anon'], true) ? $in['author_mode'] : 'first';
        $author = match ($mode) {
            'full'  => trim((string) ($lead['first_name'] ?? '') . ' ' . (string) ($lead['last_name'] ?? '')),
            'first' => trim((string) ($lead['first_name'] ?? '')),
            default => null,
        };

        $reviews = new ReviewModel();
        $ok      = $reviews->update((int) $invite['id'], $scores + [
            'score'       => round(array_sum($scores) / count($scores), 2),
            'comment'     => mb_substr(trim(strip_tags((string) ($in['comment'] ?? ''))), 0, 2000) ?: null,
            'author_name' => $author ?: null,
            'status'      => 'pending',
            'consent_ip'  => $ip ?: null,
        ]);
        if (! $ok) {
            return self::fail(implode(' ', $reviews->errors() ?: [lang('Lfl.errInvalid')]));
        }

        Timeline::log((int) $invite['company_id'], (int) $invite['lead_id'], 'note', lang('Lfl.tlReviewReceived'));

        return self::ok(lang('Lfl.revThanks'), (int) $invite['id']);
    }

    // ── 3. La plataforma modera ──────────────────────────────────────────────

    /**
     * Publica o rechaza una opinión. **Lo hace la plataforma** (lo comprueba el
     * controlador), y queda registrado quién y cuándo.
     *
     * Solo se publica lo que ya está contestado: una invitación sin notas no es una
     * opinión.
     */
    public function moderate(array $review, bool $approve, ?int $userId, string $note = ''): array
    {
        if ($review['score'] === null) {
            return self::fail(lang('Lfl.revNotAnswered'));
        }

        (new ReviewModel())->update((int) $review['id'], [
            'status'       => $approve ? 'published' : 'rejected',
            'moderated_at' => date('Y-m-d H:i:s'),
            'moderated_by' => $userId,
            'reject_note'  => $approve ? null : (mb_substr(trim($note), 0, 255) ?: null),
        ]);

        // Publicar o rechazar es lo ÚNICO que mete o saca una opinión del promedio,
        // así que es el único sitio donde hay que rehacer la copia desnormalizada.
        (new ReviewModel())->recalcProfile($review['profile_id'] ?? null);

        return self::ok(lang($approve ? 'Lfl.revPublished' : 'Lfl.revRejected'), (int) $review['id']);
    }

    // ── 4. La firma responde ─────────────────────────────────────────────────

    /**
     * La respuesta pública de la firma. **Una sola, y no borra ni cambia la opinión.**
     *
     * Solo sobre opiniones publicadas: responder a una que la plataforma todavía no ha
     * revisado sería contestar a algo que quizá no llegue a existir.
     */
    public function reply(array $review, string $text): array
    {
        if ($review['status'] !== 'published') {
            return self::fail(lang('Lfl.revNotPublished'));
        }
        if (! empty($review['reply'])) {
            return self::fail(lang('Lfl.revAlreadyReplied'));
        }

        $clean = mb_substr(trim(strip_tags($text)), 0, 1500);
        if ($clean === '') {
            return self::fail(lang('Lfl.revVReply'));
        }

        (new ReviewModel())->update((int) $review['id'], [
            'reply'      => $clean,
            'replied_at' => date('Y-m-d H:i:s'),
        ]);

        return self::ok(lang('Lfl.revReplied'), (int) $review['id']);
    }

    /** Los leads convertidos de la firma a los que todavía no se les pidió opinión. */
    public function invitableLeads(int $companyId, int $limit = 50): array
    {
        $asked = array_map('intval', array_column(
            (new ReviewModel())->forCompany($companyId)->select('lead_id')->findAll(),
            'lead_id'
        ));

        $b = (new LeadModel())->forCompany($companyId)->where('status', 'converted')
            ->orderBy('converted_at', 'desc');
        if ($asked !== []) {
            $b->whereNotIn('id', $asked);
        }

        return $b->findAll($limit);
    }
}
