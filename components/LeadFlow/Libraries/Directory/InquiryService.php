<?php

namespace Components\LeadFlow\Libraries\Directory;

use Components\LeadFlow\Libraries\Captcha;
use Components\LeadFlow\Libraries\Catalog;
use Components\LeadFlow\Libraries\Dedupe;
use Components\LeadFlow\Libraries\LeadService;
use Components\LeadFlow\Models\AreaModel;
use Components\LeadFlow\Models\ProfileModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SourceModel;

/**
 * La consulta que un visitante envía desde un perfil del directorio.
 *
 * **Aquí el directorio se convierte en pipeline.** El formulario no guarda nada
 * propio: crea un lead con `LeadService::create()`, y por eso hereda de golpe las
 * reglas del componente —duplicados, etapa «Nuevo», historial, scoring,
 * automatizaciones y, al final del camino, la conversión a Docketwise—. Una vía de
 * entrada que se guardara sus datos aparte sería una segunda verdad que nadie
 * mantendría al día; es la misma razón por la que existe `SubmissionService`.
 *
 * **Un solo sitio para las defensas**, las mismas que el formulario público de la
 * firma: freno por IP con la IP hasheada, trampa oculta, velocidad humana, captcha
 * y consentimiento explícito.
 */
class InquiryService
{
    /** `utm_source` con el que se reconoce la fuente del directorio en cada firma. */
    public const SOURCE_UTM = 'directorio';

    /**
     * @param array $in first_name, last_name, email, phone, city, area_id, message,
     *                  consent, website (trampa), stamp, captcha, ip, landing
     *
     * @return array{ok:bool,message:string,id:int}
     */
    public function submit(array $profile, array $in): array
    {
        return $this->handle((int) $profile['company_id'], 'p:' . $profile['slug'], $profile, $in);
    }

    /**
     * La misma consulta, pero **dirigida a la firma** y no a una persona.
     *
     * Es la del micrositio: quien llega buscando el despacho no tiene por qué elegir
     * antes a quién escribirle. El lead nace igual —mismas defensas, mismo
     * `LeadService`, mismas automatizaciones— con dos diferencias que son el fondo del
     * asunto: **sin `profile_id`** y **sin abogado asignado**, así que entra al reparto
     * normal de la firma en vez de caer sobre alguien que el visitante no eligió.
     *
     * El freno por IP lleva su propia clave: una firma y uno de sus abogados son dos
     * destinos distintos, y compartir cupo dejaría a uno sin poder enviar por culpa
     * del otro.
     *
     * @param array $in los mismos campos que `submit()`
     *
     * @return array{ok:bool,message:string,id:int}
     */
    public function submitToFirm(array $company, array $in): array
    {
        return $this->handle((int) $company['id'], 'f:' . $company['slug'], null, $in);
    }

    /**
     * El cuerpo compartido de las dos. `$profile` a null significa «va a la firma».
     *
     * @return array{ok:bool,message:string,id:int}
     */
    private function handle(int $cid, string $key, ?array $profile, array $in): array
    {
        $ip = mb_substr((string) ($in['ip'] ?? ''), 0, 45);

        // 1. Freno por IP + destino: la IP va hasheada porque el «::1» de IPv6 lleva
        //    caracteres reservados en las claves de caché.
        if (! service('throttler')->check('lfl_dir_' . md5($ip . '|' . $key), 5, MINUTE)) {
            return $this->fail(lang('Lfl.pubTooMany'));
        }
        // 2. Trampa: un humano no llena un campo que no ve.
        if (trim((string) ($in['website'] ?? '')) !== '') {
            return $this->fail(lang('Lfl.pubCaptchaError'));
        }
        // 3. Velocidad humana y 4. captcha (si la instalación tiene GD).
        $captcha = new Captcha();
        if (! $captcha->humanSpeed((string) ($in['stamp'] ?? ''))) {
            return $this->fail(lang('Lfl.pubTooFast'));
        }
        if (extension_loaded('gd') && ! $captcha->check((string) ($in['captcha'] ?? ''))) {
            return $this->fail(lang('Lfl.pubCaptchaError'));
        }
        // 5. Consentimiento.
        if (empty($in['consent'])) {
            return $this->fail(lang('Lfl.pubConsentRequired'));
        }

        $first = trim(strip_tags((string) ($in['first_name'] ?? '')));
        $last  = trim(strip_tags((string) ($in['last_name'] ?? '')));
        if ($first === '' || $last === '') {
            return $this->fail(lang('Lfl.vNamesRequired'));
        }
        if (Dedupe::email($in['email'] ?? null) === null && Dedupe::phone($in['phone'] ?? null) === null) {
            return $this->fail(lang('Lfl.pubNeedContact'));
        }

        Catalog::ensure($cid);

        $area    = (int) ($in['area_id'] ?? 0) ? (new AreaModel())->find((int) $in['area_id']) : null;
        $service = $area ? $this->serviceFor($cid, (int) $area['id']) : null;

        $data = [
            'first_name'             => $first,
            'last_name'              => $last,
            'email'                  => (string) ($in['email'] ?? ''),
            'phone'                  => (string) ($in['phone'] ?? ''),
            'city'                   => (string) ($in['city'] ?? ''),
            'summary'                => mb_substr(trim(strip_tags((string) ($in['message'] ?? ''))), 0, 2000) ?: null,
            'service_id'             => $service ? (int) $service['id'] : null,
            // Sin perfil, el lead no cuelga de nadie: es de la firma.
            'profile_id'             => $profile ? (int) $profile['id'] : null,
            'source_id'              => $this->sourceFor($cid),
            'landing_page'           => mb_substr((string) ($in['landing'] ?? ''), 0, 255) ?: null,
            // Quien escribe desde un perfil está pidiendo orientación: es el criterio
            // «solicita consulta» del scoring, y lo sube donde tiene que estar.
            'requested_consultation' => 1,
            // Si el perfil es de un abogado, el lead nace con ese abogado asignado: es
            // a quien el visitante eligió, no a quien le toque por turno.
            'lawyer_user_id'         => $profile && $profile['user_id'] ? (int) $profile['user_id'] : null,
            'consent'                => 1,
            'consent_ip'             => $ip,
        ];

        $res = (new LeadService())->create($cid, $data, ['channel' => 'directory', 'user_id' => null]);
        if (! $res['ok']) {
            // Un duplicado no se le cuenta al visitante como error suyo: ya está en
            // manos de la firma y volver a escribir no mejora nada.
            if (! empty($res['duplicates'])) {
                $this->countLead($profile);

                return ['ok' => true, 'message' => lang('Lfl.dirInquiryDone'), 'id' => 0];
            }

            return $this->fail($res['message']);
        }

        $this->countLead($profile);

        return ['ok' => true, 'message' => lang('Lfl.dirInquiryDone'), 'id' => (int) $res['id']];
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'id' => 0];
    }

    /**
     * Cuántos prospectos ha traído este perfil: es la cifra que justifica el plan.
     *
     * Con la consulta a la firma no hay perfil al que atribuirle el lead, y no se le
     * suma a ninguno: inflar el contador del primer abogado de la lista sería
     * inventarse un mérito.
     */
    private function countLead(?array $profile): void
    {
        if (! $profile) {
            return;
        }

        (new ProfileModel())->skipValidation(true)->update((int) $profile['id'], [
            'leads_count' => (int) $profile['leads_count'] + 1,
        ]);
        (new ProfileModel())->skipValidation(false);
    }

    /**
     * La fuente «Directorio» de esta firma, creándola la primera vez.
     *
     * No se añade a `Catalog::ensure()` porque ese bloque solo siembra cuando la
     * firma no tiene ninguna fuente: las firmas que ya existían se quedarían sin
     * ella, y entonces sus leads del directorio no se podrían distinguir de los de
     * la web en los reportes.
     */
    private function sourceFor(int $companyId): ?int
    {
        $sources = new SourceModel();
        if ($found = $sources->byUtm($companyId, self::SOURCE_UTM)) {
            return (int) $found['id'];
        }

        $sources->insert([
            'company_id' => $companyId,
            'name'       => lang('Lfl.dirSourceName'),
            'kind'       => 'web',
            'utm_source' => self::SOURCE_UTM,
            'is_active'  => 1,
        ]);

        return (int) $sources->getInsertID() ?: null;
    }

    /** El servicio de la firma que cuelga de esa área, si lo tiene configurado. */
    private function serviceFor(int $companyId, int $areaId): ?array
    {
        return (new ServiceModel())->forCompany($companyId)
            ->where('area_id', $areaId)->where('is_active', 1)->orderBy('id', 'asc')->first();
    }
}
