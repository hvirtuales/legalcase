<?php

namespace Components\Formularios\Libraries;

use Components\Formularios\Models\AnswerModel;
use Components\Formularios\Models\FormModel;
use Components\Formularios\Models\ResponseModel;

/**
 * Guardar una respuesta, con todo lo que eso arrastra.
 *
 * Todo ocurre dentro de UNA transacción: la numeración `FRM-000001`, la fila de
 * la respuesta, los archivos y el índice de `cmp_frm_answers`. Si algo falla no
 * queda ni un consecutivo quemado ni un archivo huérfano.
 *
 * Los avisos (correo a la empresa, acuse a quien responde) se mandan DESPUES de
 * cerrar la transacción y sin poder deshacerla: un canal caído no puede tirar
 * abajo una respuesta que la persona ya dio por enviada. Es la misma regla de
 * PQRSF al aprobar.
 */
class ResponseService
{
    private ResponseModel $responses;
    private AnswerModel $answers;
    private FormModel $forms;

    public function __construct()
    {
        $this->responses = new ResponseModel();
        $this->answers   = new AnswerModel();
        $this->forms     = new FormModel();
    }

    /**
     * @param array $result lo que devuelve `Validator::validate()`
     * @param array $meta   ip, user_agent, device, duration, user_id
     *
     * @return array{ok:bool,message:string,id:?int,token:?string,reference:?string}
     */
    public function submit(array $form, array $schema, array $result, array $meta): array
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $formId    = (int) $form['id'];
            $companyId = (int) $form['company_id'];

            $this->responses->insert([
                'form_id'          => $formId,
                'company_id'       => $companyId,
                'token'            => bin2hex(random_bytes(24)),
                'reference'        => $this->responses->nextReference($formId),
                'form_version'     => (int) ($form['version'] ?? 0),
                'status'           => 'complete',
                'data'             => $result['values'],
                'labels'           => (new FormService())->labelsOf($schema),
                'user_id'          => $meta['user_id'] ?? null,
                'ip'               => mb_substr((string) ($meta['ip'] ?? ''), 0, 45),
                'user_agent'       => mb_substr((string) ($meta['user_agent'] ?? ''), 0, 255),
                'device'           => $this->device((string) ($meta['user_agent'] ?? '')),
                'duration_seconds' => max(0, (int) ($meta['duration'] ?? 0)),
                'is_read'          => 0,
            ]);

            $responseId = (int) $this->responses->getInsertID();
            if ($responseId <= 0) {
                throw new \RuntimeException('No se pudo registrar la respuesta.');
            }

            $files = new AttachmentService();
            foreach ($result['files'] as $fieldId => $file) {
                $files->store($file, $responseId, $formId, $companyId, (string) $fieldId);
            }
            foreach ($result['signatures'] as $fieldId => $dataUri) {
                $files->storeSignature($dataUri, $responseId, $formId, $companyId, (string) $fieldId);
            }

            $this->answers->replaceFor($responseId, $this->buildIndex($schema, $result['values'], $responseId, $formId, $companyId));

            if ($db->transStatus() === false) {
                throw new \RuntimeException('La transacción no pudo completarse.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Formularios: fallo al guardar la respuesta: ' . $e->getMessage());

            return ['ok' => false, 'message' => lang('Frm.pubSaveError'), 'id' => null, 'token' => null, 'reference' => null];
        }

        $row = $this->responses->find($responseId);
        $this->notify($form, $schema, $row ?? []);

        return [
            'ok'        => true,
            'message'   => lang('Frm.pubSaved'),
            'id'        => $responseId,
            'token'     => (string) ($row['token'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
        ];
    }

    /**
     * Convierte los valores en filas del índice.
     *
     * Una casilla múltiple produce UNA fila por opción marcada: así el conteo
     * por opción es un `GROUP BY` y no un recorrido en PHP.
     */
    public function buildIndex(array $schema, array $values, int $responseId, int $formId, int $companyId): array
    {
        $rows = [];
        $now  = date('Y-m-d H:i:s');

        foreach ($schema['fields'] ?? [] as $field) {
            $id   = (string) $field['id'];
            $type = (string) $field['type'];

            if (FieldRegistry::isStructural($type) || !array_key_exists($id, $values)) {
                continue;
            }

            $base = [
                'response_id' => $responseId,
                'form_id'     => $formId,
                'company_id'  => $companyId,
                'field_id'    => $id,
                'field_type'  => $type,
                'created_at'  => $now,
            ];

            $value = $values[$id];

            if (is_array($value)) {
                foreach ($value as $v) {
                    $rows[] = $base + [
                        'option_value' => mb_substr((string) $v, 0, 190),
                        'value_text'   => (string) $v,
                        'value_num'    => null,
                        'value_date'   => null,
                    ];
                }

                continue;
            }

            $rows[] = $base + [
                'option_value' => FieldRegistry::hasOptions($type) || $type === 'switch'
                    ? mb_substr((string) $value, 0, 190) : null,
                'value_text'   => (string) $value,
                'value_num'    => is_numeric($value) ? (float) $value : null,
                'value_date'   => $this->asDate($type, (string) $value),
            ];
        }

        return $rows;
    }

    private function asDate(string $type, string $value): ?string
    {
        if ($type !== 'date' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value . ' 00:00:00';
    }

    /** Dispositivo aproximado, deducido en el servidor y no preguntado al navegador. */
    private function device(string $agent): string
    {
        $a = mb_strtolower($agent);
        if (str_contains($a, 'ipad') || str_contains($a, 'tablet')) {
            return 'tablet';
        }
        if (str_contains($a, 'mobi') || str_contains($a, 'android') || str_contains($a, 'iphone')) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Avisos. Nunca deshacen nada: si un canal falla, se anota y se sigue.
     *
     * Se usan los canales de la EMPRESA del núcleo (`CompanyMailer`), que ya cae
     * al correo de la plataforma si la empresa no tiene SMTP propio.
     */
    private function notify(array $form, array $schema, array $response): void
    {
        $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
        $labels   = is_array($response['labels'] ?? null) ? $response['labels'] : [];
        $values   = is_array($response['data'] ?? null) ? $response['data'] : [];

        $resumen = '';
        foreach ($labels as $fieldId => $label) {
            if (!array_key_exists($fieldId, $values)) {
                continue;
            }
            $v = $values[$fieldId];
            $resumen .= '<p><strong>' . esc((string) $label) . ':</strong> '
                . esc(is_array($v) ? implode(', ', $v) : (string) $v) . '</p>';
        }

        // 1. A quien gestiona el formulario.
        $to = (string) ($settings['notifyEmail'] ?? '');
        if ($to !== '') {
            $this->send(
                (int) $form['company_id'],
                $to,
                lang('Frm.mailNewTitle', [$form['title']]),
                '<h2>' . esc(lang('Frm.mailNewTitle', [$form['title']])) . '</h2>'
                . '<p>' . esc(lang('Frm.mailRef', [$response['reference'] ?? ''])) . '</p>' . $resumen
            );
        }

        // 2. Acuse a quien respondió, si el formulario declara de qué campo
        //    sacar su correo. Nunca se adivina: mandar un correo a una
        //    dirección que la persona escribió para otra cosa es pasarse.
        $ackId = (string) ($settings['ackFieldId'] ?? '');
        $email = $ackId !== '' ? (string) ($values[$ackId] ?? '') : '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->send(
                (int) $form['company_id'],
                $email,
                lang('Frm.mailAckTitle', [$form['title']]),
                '<h2>' . esc(lang('Frm.mailAckTitle', [$form['title']])) . '</h2>'
                . '<p>' . esc(lang('Frm.mailAckBody', [$response['reference'] ?? ''])) . '</p>'
            );
        }
    }

    private function send(int $companyId, string $to, string $subject, string $html): void
    {
        try {
            if (class_exists('App\Libraries\CompanyMailer')) {
                (new \App\Libraries\CompanyMailer())->send($companyId, $to, $subject, $html);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Formularios: no se pudo enviar el aviso: ' . $e->getMessage());
        }
    }

    /** Baja de una respuesta con lo que el soft-delete no arrastra. */
    public function deleteResponse(int $responseId): void
    {
        (new AttachmentService())->deleteForResponse($responseId);
        $this->answers->where('response_id', $responseId)->delete();
        $this->responses->delete($responseId);
    }
}
