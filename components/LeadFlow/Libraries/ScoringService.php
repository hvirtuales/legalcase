<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\AppointmentModel;
use Components\LeadFlow\Models\DocumentModel;
use Components\LeadFlow\Models\LeadModel;
use Components\LeadFlow\Models\ScoreHistoryModel;
use Components\LeadFlow\Models\ScoringRuleModel;
use Components\LeadFlow\Models\ServiceModel;
use Components\LeadFlow\Models\SettingsModel;
use Components\LeadFlow\Models\SourceModel;
use Components\LeadFlow\Models\SubmissionModel;

/**
 * Lead scoring por reglas (§15).
 *
 * Los criterios son CÓDIGO (cada uno sabe cómo se evalúa); los puntos son de la
 * firma. El score se RECALCULA entero desde los hechos del lead cada vez, en vez
 * de sumar y restar sobre el anterior: así nunca puede quedar descuadrado por un
 * evento que se procesó dos veces o que se deshizo.
 *
 * Bandas: 0-39 baja, 40-69 media, 70-100 alta. La prioridad sigue al score salvo
 * que alguien la haya fijado a mano (`priority_manual`).
 */
class ScoringService
{
    /** Criterio => puntos por defecto (los del §15). */
    public const CRITERIA = [
        'priority_service'       => 20,
        'form_complete'          => 10,
        'documents'              => 10,
        'requested_consultation' => 15,
        'responded'              => 10,
        'consultation_scheduled' => 15,
        'referral'               => 10,
        'no_response'            => -20,
    ];

    public const CRITERIA_LABELS = [
        'priority_service' => 'scPriorityService', 'form_complete' => 'scFormComplete', 'documents' => 'scDocuments',
        'requested_consultation' => 'scRequestedConsult', 'responded' => 'scResponded',
        'consultation_scheduled' => 'scConsultScheduled', 'referral' => 'scReferral', 'no_response' => 'scNoResponse',
    ];

    public static function band(int $score): string
    {
        return $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');
    }

    /** @return array<string,bool> criterio => se cumple */
    public function evaluate(array $lead): array
    {
        $cid = (int) $lead['company_id'];
        $lid = (int) $lead['id'];

        $service = ! empty($lead['service_id']) ? (new ServiceModel())->owned((int) $lead['service_id'], $cid) : null;
        $source  = ! empty($lead['source_id']) ? (new SourceModel())->owned((int) $lead['source_id'], $cid) : null;

        $days = (int) ((new SettingsModel())->forCompany($cid)['no_response_days'] ?? 7);
        $days = $days > 0 ? $days : 7;

        // «Sin respuesta»: le escribimos y no contestó en N días. Si nunca le
        // escribimos no se penaliza al prospecto por un retraso nuestro.
        $noResponse = ! empty($lead['last_contact_at'])
            && (empty($lead['last_inbound_at']) || $lead['last_inbound_at'] < $lead['last_contact_at'])
            && strtotime($lead['last_contact_at']) <= time() - $days * 86400;

        return [
            'priority_service'       => $service && (int) $service['is_priority'] === 1,
            'form_complete'          => (new SubmissionModel())->forCompany($cid)->where('lead_id', $lid)->where('is_complete', 1)->countAllResults() > 0,
            'documents'              => (new DocumentModel())->forCompany($cid)->where('lead_id', $lid)->countAllResults() > 0,
            'requested_consultation' => (int) ($lead['requested_consultation'] ?? 0) === 1,
            'responded'              => ! empty($lead['last_inbound_at']),
            'consultation_scheduled' => (new AppointmentModel())->forCompany($cid)->where('lead_id', $lid)
                ->whereIn('status', AppointmentModel::ACTIVE)->countAllResults() > 0,
            'referral'               => $source && $source['kind'] === 'referral',
            'no_response'            => $noResponse,
        ];
    }

    /**
     * Recalcula y guarda. Devuelve el score nuevo.
     */
    public function recalculate(int $leadId, int $companyId, string $reason = 'recalc'): int
    {
        $leads = new LeadModel();
        $lead  = $leads->owned($leadId, $companyId);
        if (! $lead) {
            return 0;
        }

        $rules     = (new ScoringRuleModel())->mapFor($companyId);
        $matched   = $this->evaluate($lead);
        $score     = 0;
        $breakdown = [];

        foreach ($matched as $criterion => $hit) {
            $rule = $rules[$criterion] ?? ['points' => self::CRITERIA[$criterion], 'is_active' => 1];
            if (! $hit || (int) $rule['is_active'] !== 1) {
                continue;
            }
            $score += (int) $rule['points'];
            $breakdown[$criterion] = (int) $rule['points'];
        }
        $score = max(0, min(100, $score));

        $data = ['score' => $score];
        if ((int) $lead['priority_manual'] !== 1) {
            $data['priority'] = self::band($score);
        }

        $changed = $score !== (int) $lead['score'] || (($data['priority'] ?? $lead['priority']) !== $lead['priority']);
        if ($changed) {
            $leads->skipValidation(true)->update($leadId, $data);
            $leads->skipValidation(false);

            (new ScoreHistoryModel())->insert([
                'company_id' => $companyId,
                'lead_id'    => $leadId,
                'score'      => $score,
                'previous'   => (int) $lead['score'],
                'breakdown'  => json_encode($breakdown),
                'reason'     => mb_substr($reason, 0, 60),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($score !== (int) $lead['score']) {
                Timeline::log($companyId, $leadId, 'score', lang('Lfl.tlScore', [(int) $lead['score'], $score]), $breakdown);

                // §19: «score alcanzado». Se dispara al SUBIR y la condición decide el
                // umbral (`score` mayor que 69); bajar no es alcanzar nada.
                if ($score > (int) $lead['score']) {
                    $fresh = $leads->find($leadId);
                    if ($fresh) {
                        AutomationEngine::fire('score_reached', $fresh, ['from' => (int) $lead['score'], 'to' => $score]);
                    }
                }
            }
        }

        return $score;
    }

    /** Desglose actual para mostrar en la ficha: criterio => [cumple, puntos]. */
    public function breakdown(array $lead): array
    {
        $rules = (new ScoringRuleModel())->mapFor((int) $lead['company_id']);
        $out   = [];
        foreach ($this->evaluate($lead) as $criterion => $hit) {
            $rule            = $rules[$criterion] ?? ['points' => self::CRITERIA[$criterion], 'is_active' => 1];
            $out[$criterion] = ['hit' => $hit, 'points' => (int) $rule['points'], 'active' => (int) $rule['is_active'] === 1];
        }

        return $out;
    }
}
