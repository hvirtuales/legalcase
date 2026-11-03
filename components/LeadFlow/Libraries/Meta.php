<?php

namespace Components\LeadFlow\Libraries;

/**
 * Presentación centralizada: valor guardado → clave de idioma (y color del
 * distintivo, e icono cuando aplica).
 *
 * Los mismos valores se pintan en muchas pantallas (ficha, listado, Kanban,
 * agenda, reportes). Con el mapa copiado en cada vista bastaría añadir un valor
 * para que saliera con su nombre en unas y en blanco en otras.
 */
class Meta
{
    public const PRIORITIES      = ['low' => 'prLow', 'medium' => 'prMedium', 'high' => 'prHigh'];
    public const PRIORITY_COLORS = ['low' => 'badge-secondary', 'medium' => 'badge-warning', 'high' => 'badge-danger'];

    public const STATUSES      = ['open' => 'stOpen', 'won' => 'stWon', 'lost' => 'stLost', 'converted' => 'stConverted'];
    public const STATUS_COLORS = ['open' => 'badge-info', 'won' => 'badge-success', 'lost' => 'badge-secondary', 'converted' => 'badge-primary'];

    public const STAGE_KINDS = ['open' => 'kindOpen', 'won' => 'kindWon', 'converted' => 'kindConverted', 'lost' => 'kindLost'];

    /** Nombre por defecto de cada etapa de sistema (se siembra en el idioma de quien entra primero). */
    public const STAGE_CODES = [
        'new' => 'stgNew', 'contacted' => 'stgContacted', 'prequalification' => 'stgPrequal', 'qualified' => 'stgQualified',
        'consultation_scheduled' => 'stgApptScheduled', 'consultation_done' => 'stgApptDone', 'proposal' => 'stgProposal',
        'follow_up' => 'stgFollowUp', 'won' => 'stgWon', 'converted' => 'stgConverted', 'not_qualified' => 'stgNotQualified',
        'no_response' => 'stgNoResponse', 'no_show' => 'stgNoShow', 'lost' => 'stgLost', 'duplicate' => 'stgDuplicate',
    ];

    public const SOURCE_KINDS = ['web' => 'srcWeb', 'meta' => 'srcMeta', 'google' => 'srcGoogle', 'whatsapp' => 'srcWhatsapp',
        'referral' => 'srcReferral', 'other' => 'srcOther'];

    public const LOST_REASONS = ['not_eligible' => 'lrNotEligible', 'no_budget' => 'lrNoBudget', 'chose_competitor' => 'lrCompetitor',
        'no_response' => 'lrNoResponse', 'not_interested' => 'lrNotInterested', 'duplicate' => 'lrDuplicate', 'other' => 'lrOther'];

    public const ACTIVITY_TYPES = ['call' => 'actCall', 'email' => 'actEmail', 'whatsapp' => 'actWhatsapp', 'review' => 'actReview',
        'request_doc' => 'actRequestDoc', 'schedule' => 'actSchedule', 'follow_up' => 'actFollowUp', 'other' => 'actOther',
        'deadline' => 'actDeadline'];
    public const ACTIVITY_ICONS = ['call' => 'icon-telephone', 'email' => 'icon-envelope', 'whatsapp' => 'icon-whatsapp',
        'review' => 'icon-eye', 'request_doc' => 'icon-paperclip', 'schedule' => 'icon-headset', 'follow_up' => 'icon-arrow-repeat',
        'other' => 'icon-circle', 'deadline' => 'icon-clock-history'];
    public const ACTIVITY_STATUSES = ['pending' => 'asPending', 'done' => 'asDone', 'cancelled' => 'asCancelled'];

    public const APPT_MODES    = ['video' => 'amVideo', 'phone' => 'amPhone', 'in_person' => 'amInPerson'];
    public const APPT_STATUSES = ['scheduled' => 'apScheduled', 'confirmed' => 'apConfirmed', 'completed' => 'apCompleted',
        'no_show' => 'apNoShow', 'cancelled' => 'apCancelled'];
    public const APPT_COLORS = ['scheduled' => 'badge-info', 'confirmed' => 'badge-primary', 'completed' => 'badge-success',
        'no_show' => 'badge-danger', 'cancelled' => 'badge-secondary'];
    public const APPT_RESULTS = ['likely_hire' => 'arLikelyHire', 'follow_up' => 'arFollowUp', 'pending_docs' => 'arPendingDocs',
        'not_eligible' => 'arNotEligible', 'not_interested' => 'arNotInterested', 'escalate' => 'arEscalate'];

    public const CHANNELS   = ['call' => 'chCall', 'email' => 'chEmail', 'whatsapp' => 'chWhatsapp', 'sms' => 'chSms'];
    public const DIRECTIONS = ['out' => 'dirOut', 'in' => 'dirIn'];
    public const OUTCOMES   = ['answered' => 'ocAnswered', 'no_answer' => 'ocNoAnswer', 'voicemail' => 'ocVoicemail',
        'wrong_number' => 'ocWrongNumber', 'sent' => 'ocSent', 'received' => 'ocReceived', 'failed' => 'ocFailed'];

    public const MEMBER_ROLES = ['supervisor' => 'roleSupervisor', 'advisor' => 'roleAdvisor', 'lawyer' => 'roleLawyer', 'auditor' => 'roleAuditor'];

    public const FORM_STATUSES = ['draft' => 'fsDraft', 'published' => 'fsPublished', 'closed' => 'fsClosed'];
    public const FORM_COLORS   = ['draft' => 'badge-secondary', 'published' => 'badge-success', 'closed' => 'badge-warning'];

    public const CONVERSION_STATUSES = ['pending' => 'cvsPending', 'running' => 'cvsRunning', 'done' => 'cvsDone', 'failed' => 'cvsFailed'];
    public const CONVERSION_COLORS   = ['pending' => 'badge-secondary', 'running' => 'badge-info', 'done' => 'badge-success', 'failed' => 'badge-danger'];

    public const INTEGRATION_STATUSES = ['disconnected' => 'isDisconnected', 'pending' => 'isPending', 'connected' => 'isConnected', 'error' => 'isError'];
    public const INTEGRATION_COLORS   = ['disconnected' => 'badge-secondary', 'pending' => 'badge-warning', 'connected' => 'badge-success', 'error' => 'badge-danger'];

    public const TIMELINE_ICONS = [
        'created' => 'icon-plus-circle', 'updated' => 'icon-pencil', 'stage' => 'icon-arrow-repeat',
        'assigned' => 'icon-person-badge', 'priority' => 'icon-exclamation-circle', 'call' => 'icon-telephone',
        'email' => 'icon-envelope', 'whatsapp' => 'icon-whatsapp', 'sms' => 'icon-chat', 'note' => 'icon-journal-text',
        'form' => 'icon-clipboard', 'document' => 'icon-paperclip', 'appointment' => 'icon-headset',
        'activity' => 'icon-list-check', 'score' => 'icon-star', 'conversion' => 'icon-box-arrow-up-right',
        'sync' => 'icon-hdd-network', 'error' => 'icon-exclamation-circle', 'duplicate' => 'icon-people',
        'automation' => 'icon-robot',
        // El expediente escribe en la línea de tiempo DEL LEAD: el historial del caso
        // es la continuación del comercial, no una segunda mitad aparte. Un tipo que
        // no esté en este mapa no da error, simplemente se pinta con `icon-circle`.
        'matter' => 'icon-folder', 'fee' => 'icon-cash-stack', 'payment' => 'icon-wallet',
    ];

    /** Etapas procesales de sistema: código => clave de idioma. */
    public const MATTER_STAGE_CODES = [
        'intake' => 'mstgIntake', 'contract' => 'mstgContract', 'documents' => 'mstgDocuments',
        'preparation' => 'mstgPreparation', 'filed' => 'mstgFiled', 'evidence' => 'mstgEvidence',
        'biometrics' => 'mstgBiometrics', 'interview' => 'mstgInterview', 'decision' => 'mstgDecision',
        'closed' => 'mstgClosed', 'withdrawn' => 'mstgWithdrawn',
    ];

    /** El `kind` de la etapa procesal, para rotularlo donde haga falta. */
    public const MATTER_STAGE_KINDS = ['open' => 'mkOpen', 'filed' => 'mkFiled', 'waiting' => 'mkWaiting',
        'closed' => 'mkClosed', 'cancelled' => 'mkCancelled'];

    public const MATTER_STATUSES = ['active' => 'msActive', 'on_hold' => 'msOnHold', 'closed' => 'msClosed',
        'cancelled' => 'msCancelled'];
    public const MATTER_STATUS_COLORS = ['active' => 'badge-success', 'on_hold' => 'badge-warning',
        'closed' => 'badge-secondary', 'cancelled' => 'badge-light'];

    public const CLOSE_REASONS = ['won' => 'crWon', 'denied' => 'crDenied', 'withdrawn' => 'crWithdrawn',
        'transferred' => 'crTransferred', 'other' => 'crOther'];

    /** Color de la etapa → clase del distintivo. */
    public const STAGE_BADGES = ['primary' => 'badge-primary', 'info' => 'badge-info', 'success' => 'badge-success',
        'warning' => 'badge-warning', 'danger' => 'badge-danger', 'secondary' => 'badge-secondary', 'light' => 'badge-light'];

    public static function label(array $map, ?string $value): string
    {
        if ($value === null || ! isset($map[$value])) {
            return (string) ($value ?? '—');
        }

        return lang('Lfl.' . $map[$value]);
    }

    public static function color(array $map, ?string $value, string $default = 'badge-light'): string
    {
        return $map[$value ?? ''] ?? $default;
    }

    /** @return array<string,string> valor => etiqueta traducida */
    public static function options(array $map): array
    {
        $out = [];
        foreach ($map as $v => $k) {
            $out[$v] = lang('Lfl.' . $k);
        }

        return $out;
    }

    /** Distintivo de etapa ya compuesto (nombre escapado). */
    public static function stageBadge(?array $stage): string
    {
        if (! $stage) {
            return '<span class="badge badge-light">—</span>';
        }

        return '<span class="badge ' . (self::STAGE_BADGES[$stage['color'] ?? ''] ?? 'badge-light') . '">' . esc($stage['name']) . '</span>';
    }

    /** Distintivo de score + prioridad. */
    public static function scoreBadge(int $score, ?string $priority): string
    {
        return '<span class="badge ' . self::color(self::PRIORITY_COLORS, $priority) . '" title="' . esc(self::label(self::PRIORITIES, $priority), 'attr') . '">'
            . (int) $score . '</span>';
    }

    public static function money($value, string $currency): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $currency . ' ' . number_format((float) $value, 2, '.', ',');
    }

    public static function date(?string $datetime, bool $time = true): string
    {
        return $datetime ? date($time ? 'd/m/Y H:i' : 'd/m/Y', strtotime($datetime)) : '—';
    }

    /** Duración legible («3 d», «5 h», «12 min»). */
    public static function since(?string $datetime): string
    {
        if (! $datetime) {
            return '—';
        }
        $s = max(0, time() - strtotime($datetime));
        if ($s >= 86400) {
            return lang('Lfl.durDays', [intdiv($s, 86400)]);
        }
        if ($s >= 3600) {
            return lang('Lfl.durHours', [intdiv($s, 3600)]);
        }

        return lang('Lfl.durMinutes', [max(1, intdiv($s, 60))]);
    }

    /** ISO 3166-1 alfa-2. Los nombres los pone intl en el idioma activo. */
    private const COUNTRY_CODES = 'AD AE AF AG AI AL AM AO AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';

    private static array $countries = [];

    /** @return array<string,string> código => nombre, ordenado por nombre */
    public static function countries(): array
    {
        $locale = service('request')->getLocale() ?: 'es';
        if (isset(self::$countries[$locale])) {
            return self::$countries[$locale];
        }

        $out = [];
        foreach (explode(' ', self::COUNTRY_CODES) as $code) {
            $name       = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-' . $code, $locale) : $code;
            $out[$code] = $name !== '' ? $name : $code;
        }
        asort($out, SORT_LOCALE_STRING);

        return self::$countries[$locale] = $out;
    }

    public static function isCountry(?string $code): bool
    {
        return $code !== null && strlen($code) === 2 && str_contains(' ' . self::COUNTRY_CODES . ' ', ' ' . strtoupper($code) . ' ');
    }

    public static function countryName(?string $code): string
    {
        if (! self::isCountry($code)) {
            return '—';
        }

        return self::countries()[strtoupper($code)] ?? strtoupper($code);
    }
}
