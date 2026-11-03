<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\SubmissionModel;

/**
 * Guardar las respuestas de un formulario, desde donde vengan: el formulario
 * público, la precalificación que llena el asesor o los datos de ejemplo.
 *
 * Existe para que el disparador «formulario completado» salga de **un solo
 * sitio**: repartido entre los dos controladores, el día que se añadiera una
 * tercera vía (una API, una importación) se quedaría sin automatizar y nadie lo
 * notaría hasta que un lead no recibiera su correo de bienvenida.
 */
class SubmissionService
{
    /**
     * @param array $result lo que devuelve `FormEngine::collect()`
     *
     * @return int id de la respuesta
     */
    public function record(array $lead, array $form, array $result, string $channel, ?int $userId = null, ?string $ip = null): int
    {
        $companyId = (int) $lead['company_id'];
        $leadId    = (int) $lead['id'];

        $model = new SubmissionModel();
        $model->insert([
            'company_id'  => $companyId,
            'lead_id'     => $leadId,
            'form_id'     => (int) $form['id'],
            'answers'     => json_encode($result['values'], JSON_UNESCAPED_UNICODE),
            'labels'      => json_encode($result['labels'], JSON_UNESCAPED_UNICODE),
            'is_complete' => ! empty($result['complete']) ? 1 : 0,
            'channel'     => $channel === 'public' ? 'public' : 'internal',
            'user_id'     => $userId,
            'ip'          => $ip !== null ? mb_substr($ip, 0, 45) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $model->getInsertID();

        Timeline::log($companyId, $leadId, 'form', lang('Lfl.tlForm', [$form['name']]), [], $userId);
        (new ScoringService())->recalculate($leadId, $companyId, 'form');

        // El §19 habla de «formulario completado»: una respuesta a medias no dispara nada.
        if (! empty($result['complete'])) {
            $fresh = (new \Components\LeadFlow\Models\LeadModel())->find($leadId);
            if ($fresh) {
                AutomationEngine::fire('form_completed', $fresh, ['form_id' => (int) $form['id'], 'submission_id' => $id]);
            }
        }

        return $id;
    }
}
