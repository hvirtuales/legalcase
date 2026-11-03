<?php

namespace Components\LeadFlow\Libraries\Directory;

use Components\LeadFlow\Libraries\Captcha;
use Components\LeadFlow\Libraries\Catalog;
use Components\LeadFlow\Libraries\Dedupe;
use Components\LeadFlow\Libraries\LeadService;
use Components\LeadFlow\Libraries\PlanGuard;
use Components\LeadFlow\Models\AreaModel;
use Components\LeadFlow\Models\JurisdictionModel;
use Components\LeadFlow\Models\OpportunityClaimModel;
use Components\LeadFlow\Models\OpportunityModel;
use Components\LeadFlow\Models\ProfileModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;

/**
 * Oportunidades abiertas: de «no sé a quién acudir» a un lead de una firma concreta.
 *
 * Es la otra vía de captación, y la que de verdad «conecta oportunidades»: quien no sabe
 * a quién escribir describe su caso una vez, y las firmas del área lo ven y lo reclaman.
 *
 * **Tres reglas que no se negocian, y las tres viven aquí y no en la pantalla:**
 *
 *  1. **El contacto no viaja hasta el reclamo.** La bandeja enseña el caso —área,
 *     jurisdicción, idioma, ciudad y resumen—, nunca a la persona. Así una firma no
 *     puede recoger contactos sin comprometerse a atenderlos.
 *  2. **El tope de reclamos se hace valer con el UNIQUE y con una relectura dentro de la
 *     transacción**, no con el contador que se leyó al pintar la pantalla: dos firmas
 *     reclamando a la vez pasan las dos la comprobación previa. Lo que se le promete al
 *     prospecto —«hasta tres despachos»— está escrito en su propia fila.
 *  3. **El cupo del plan se cuenta por mes** y se comprueba antes de tocar nada, porque
 *     es lo que se vende: la membresía premium es, literalmente, poder reclamar más.
 *
 * Y una consecuencia de diseño: el lead que nace del reclamo entra por `LeadService`,
 * así que hereda duplicados, scoring, timeline, automatizaciones y la conversión a
 * Docketwise, igual que el que entra por un perfil.
 */
class OpportunityService
{
    /** `utm_source` con el que se reconoce esta fuente en cada firma. */
    public const SOURCE_UTM = 'oportunidad';

    public function __construct()
    {
        Taxonomy::ensure();
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0];
    }

    // ── 1. El prospecto describe su caso ─────────────────────────────────────

    /**
     * Alta pública de una oportunidad.
     *
     * @param array $in area_id, jurisdiction_id, language, city, summary, first_name,
     *                  last_name, email, phone, consent, website, stamp, captcha, ip
     */
    public function submit(array $in): array
    {
        $ip = mb_substr((string) ($in['ip'] ?? ''), 0, 45);

        // Las mismas defensas que la consulta a un perfil, en el mismo orden.
        if (! service('throttler')->check('lfl_opp_' . md5($ip), 3, MINUTE)) {
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

        $first = trim(strip_tags((string) ($in['first_name'] ?? '')));
        $last  = trim(strip_tags((string) ($in['last_name'] ?? '')));
        if ($first === '' || $last === '') {
            return self::fail(lang('Lfl.vNamesRequired'));
        }
        if (Dedupe::email($in['email'] ?? null) === null && Dedupe::phone($in['phone'] ?? null) === null) {
            return self::fail(lang('Lfl.pubNeedContact'));
        }

        // El área es obligatoria: es lo único que decide **quién va a ver** el caso. Sin
        // ella la oportunidad no llegaría a ninguna bandeja.
        $area = (new AreaModel())->find((int) ($in['area_id'] ?? 0));
        if (! $area || (int) $area['is_active'] !== 1) {
            return self::fail(lang('Lfl.oppVArea'));
        }
        $jur = (int) ($in['jurisdiction_id'] ?? 0)
            ? (new JurisdictionModel())->find((int) $in['jurisdiction_id'])
            : null;

        $model = new OpportunityModel();
        $now   = date('Y-m-d H:i:s');

        $ok = $model->insert([
            'token'           => bin2hex(random_bytes(32)),
            'area_id'         => (int) $area['id'],
            'jurisdiction_id' => $jur ? (int) $jur['id'] : null,
            'language'        => mb_substr(trim(strip_tags((string) ($in['language'] ?? ''))), 0, 20) ?: null,
            'city'            => mb_substr(trim(strip_tags((string) ($in['city'] ?? ''))), 0, 80) ?: null,
            'summary'         => mb_substr(trim(strip_tags((string) ($in['summary'] ?? ''))), 0, 2000) ?: null,
            'first_name'      => mb_substr($first, 0, 80),
            'last_name'       => mb_substr($last, 0, 80),
            'email'           => mb_substr(trim((string) ($in['email'] ?? '')), 0, 150) ?: null,
            'email_norm'      => Dedupe::email($in['email'] ?? null),
            'phone'           => mb_substr(trim((string) ($in['phone'] ?? '')), 0, 30) ?: null,
            'phone_norm'      => Dedupe::phone($in['phone'] ?? null),
            'consent_at'      => $now,
            'consent_ip'      => $ip ?: null,
            'status'          => 'open',
            'claims_count'    => 0,
            'max_claims'      => OpportunityModel::MAX_CLAIMS,
            'expires_at'      => date('Y-m-d H:i:s', strtotime('+' . OpportunityModel::DAYS_OPEN . ' day')),
        ]);

        if (! $ok) {
            return self::fail(implode(' ', $model->errors() ?: [lang('Lfl.errInvalid')]));
        }

        $id = (int) $model->getInsertID();
        log_message('info', 'LeadFlow: oportunidad ' . $id . ' en el área ' . $area['code']);

        return ['ok' => true, 'message' => lang('Lfl.oppDone'), 'id' => $id];
    }

    // ── 2. La firma mira su bandeja ──────────────────────────────────────────

    /**
     * Las oportunidades que esta firma puede reclamar.
     *
     * **Solo las de las áreas en las que tiene gente publicada**: una firma de familia no
     * tiene por qué ver casos de inmigración, y mostrárselo todo convertiría la bandeja en
     * ruido. Quien quiera ver más, publica más áreas.
     *
     * Sin datos de contacto (`forBoard()`), sin las que ya reclamó y sin las agotadas.
     *
     * @return array<int,array>
     */
    public function board(int $companyId, int $limit = 50): array
    {
        $areaIds = $this->areasOf($companyId);
        if ($areaIds === []) {
            return [];
        }

        $mine = (new OpportunityClaimModel())->idsOf($companyId);

        $b = (new OpportunityModel())->forBoard()
            ->whereIn('cmp_lfl_opportunities.area_id', $areaIds)
            ->whereIn('cmp_lfl_opportunities.status', ['open', 'claimed'])
            ->where('cmp_lfl_opportunities.claims_count < cmp_lfl_opportunities.max_claims', null, false)
            ->groupStart()
                ->where('cmp_lfl_opportunities.expires_at', null)
                ->orWhere('cmp_lfl_opportunities.expires_at >', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy('cmp_lfl_opportunities.created_at', 'desc');

        if ($mine !== []) {
            $b->whereNotIn('cmp_lfl_opportunities.id', $mine);
        }

        return $this->decorate($b->findAll($limit));
    }

    /** Las áreas en las que la firma tiene perfiles publicados. */
    private function areasOf(int $companyId): array
    {
        return array_map('intval', array_column(db_connect()->table('cmp_lfl_profile_areas pa')
            ->select('pa.area_id')
            ->join('cmp_lfl_profiles p', 'p.id = pa.profile_id')
            ->where('p.company_id', $companyId)->where('p.status', 'published')->where('p.deleted_at', null)
            ->groupBy('pa.area_id')->get()->getResultArray(), 'area_id'));
    }

    /** Nombres de área y jurisdicción, que es lo que se lee en la bandeja. */
    private function decorate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $areas = (new AreaModel())->options();
        $jurs  = (new JurisdictionModel())->options();

        foreach ($rows as &$r) {
            $r['area_name'] = $areas[(int) $r['area_id']] ?? '—';
            $r['jur_name']  = $r['jurisdiction_id'] ? ($jurs[(int) $r['jurisdiction_id']] ?? null) : null;
            $r['left']      = max(0, (int) $r['max_claims'] - (int) $r['claims_count']);
        }

        return $rows;
    }

    // ── 3. La firma reclama, y nace el lead ──────────────────────────────────

    /**
     * Reclama una oportunidad para esta firma y crea el lead con los datos completos.
     *
     * El orden importa: **primero el reclamo y después el lead**. Si fuera al revés, un
     * choque en el UNIQUE dejaría el lead creado sin reclamo que lo justifique, y la
     * firma tendría el contacto sin haber ocupado una de las plazas.
     */
    public function claim(int $opportunityId, int $companyId, ?int $userId): array
    {
        $model = new OpportunityModel();
        $row   = $model->find($opportunityId);
        if (! $row) {
            return self::fail(lang('Lfl.errNotFound'));
        }

        // Solo de las áreas que publica, igual que la bandeja: el id llega por el POST.
        if (! in_array((int) $row['area_id'], $this->areasOf($companyId), true)) {
            return self::fail(lang('Lfl.oppNotYours'));
        }
        if (! OpportunityModel::isOpen($row)) {
            return self::fail(lang('Lfl.oppClosed'));
        }

        $claims = new OpportunityClaimModel();
        if ($claims->already($opportunityId, $companyId)) {
            return self::fail(lang('Lfl.oppAlready'));
        }

        $plan = new PlanGuard();
        if ($block = $plan->opportunityBlockReason($companyId)) {
            return self::fail($block);
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->transBegin();

        try {
            // El reclamo primero: si otra firma se llevó la última plaza mientras se
            // pintaba la pantalla, aquí se ve.
            $fresh = $model->find($opportunityId);
            if (! OpportunityModel::isOpen($fresh)) {
                throw new \RuntimeException(lang('Lfl.oppClosed'));
            }

            // Sin el modelo: un insert que choca con el UNIQUE tiene que lanzar para que
            // la transacción se deshaga, y el modelo devolvería false sin avisar.
            $db->table('cmp_lfl_opportunity_claims')->insert([
                'opportunity_id' => $opportunityId,
                'company_id'     => $companyId,
                'user_id'        => $userId,
                'created_at'     => $now,
            ]);
            $claimId = (int) $db->insertID();

            $count  = (int) $fresh['claims_count'] + 1;
            $status = $count >= (int) $fresh['max_claims'] ? 'closed' : 'claimed';
            $model->skipValidation(true)->update($opportunityId, ['claims_count' => $count, 'status' => $status]);
            $model->skipValidation(false);

            Catalog::ensure($companyId);
            $area    = (new AreaModel())->find((int) $fresh['area_id']);
            $service = $area ? (new ServiceModel())->forCompany($companyId)
                ->where('area_id', (int) $area['id'])->where('is_active', 1)->first() : null;

            $res = (new LeadService())->create($companyId, [
                'first_name'             => $fresh['first_name'],
                'last_name'              => $fresh['last_name'],
                'email'                  => (string) ($fresh['email'] ?? ''),
                'phone'                  => (string) ($fresh['phone'] ?? ''),
                'city'                   => (string) ($fresh['city'] ?? ''),
                'summary'                => $fresh['summary'],
                'service_id'             => $service ? (int) $service['id'] : null,
                'source_id'              => $this->sourceFor($companyId),
                'requested_consultation' => 1,
                'consent'                => 1,
                'consent_ip'             => $fresh['consent_ip'],
            ], ['channel' => 'opportunity', 'user_id' => $userId, 'force' => true]);

            if (! $res['ok']) {
                throw new \RuntimeException($res['message']);
            }

            $db->table('cmp_lfl_opportunity_claims')->where('id', $claimId)
                ->update(['lead_id' => (int) $res['id']]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return self::fail($e->getMessage() ?: lang('Lfl.errInvalid'));
        }

        return ['ok' => true, 'message' => lang('Lfl.oppClaimed'), 'id' => (int) $res['id']];
    }

    /**
     * La fuente «Oportunidad» de esta firma, creándola la primera vez.
     *
     * Aparte de la del directorio: un prospecto que eligió al abogado y uno que llegó por
     * una oportunidad reclamada no son lo mismo, y en los reportes hay que poder
     * distinguirlos — es justo lo que dice si el cupo de oportunidades merece la pena.
     */
    private function sourceFor(int $companyId): ?int
    {
        $sources = new SourceModel();
        if ($found = $sources->byUtm($companyId, self::SOURCE_UTM)) {
            return (int) $found['id'];
        }

        $sources->insert([
            'company_id' => $companyId,
            'name'       => lang('Lfl.oppSourceName'),
            'kind'       => 'web',
            'utm_source' => self::SOURCE_UTM,
            'is_active'  => 1,
        ]);

        return (int) $sources->getInsertID() ?: null;
    }

    /** Cierra las que nadie reclamó a tiempo. La llama `leadflow:tick`. */
    public function expire(): int
    {
        $model = new OpportunityModel();
        $old   = $model->whereIn('status', ['open', 'claimed'])
            ->where('expires_at IS NOT NULL', null, false)
            ->where('expires_at <', date('Y-m-d H:i:s'))->findAll(500);

        foreach ($old as $row) {
            $model->skipValidation(true)->update((int) $row['id'], ['status' => 'expired']);
        }
        $model->skipValidation(false);

        return count($old);
    }

    /** Lo que ve el prospecto al volver con su token: el estado, sin datos de nadie. */
    public function statusByToken(string $token): ?array
    {
        $row = (new OpportunityModel())->byToken($token);
        if (! $row) {
            return null;
        }

        return [
            'status'       => $row['status'],
            'claims_count' => (int) $row['claims_count'],
            'max_claims'   => (int) $row['max_claims'],
            'area_name'    => (new AreaModel())->options()[(int) $row['area_id']] ?? '—',
            'created_at'   => $row['created_at'],
            'expires_at'   => $row['expires_at'],
        ];
    }

    /** Las áreas que la firma publica, para decirle por qué su bandeja está vacía. */
    public function publishedAreas(int $companyId): int
    {
        return count($this->areasOf($companyId));
    }

    /** @return array<int,array> perfiles publicados, para el aviso del estado vacío */
    public function publishedProfiles(int $companyId): array
    {
        return (new ProfileModel())->forCompany($companyId)->where('status', 'published')->findAll();
    }
}
