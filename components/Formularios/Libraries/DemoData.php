<?php

namespace Components\Formularios\Libraries;

use Components\Formularios\Models\FormModel;
use Components\Formularios\Models\ResponseModel;

/**
 * Datos de ejemplo para ver el componente funcionando sin teclear nada.
 *
 * Crea DOS formularios (uno en ficha y otro a pantalla completa, para que se
 * vean las dos presentaciones) y 14 respuestas repartidas en el último mes, con
 * variedad suficiente para que el informe tenga contenido: calificaciones de 1
 * a 5, NPS con promotores y detractores, casillas múltiples y texto abierto.
 *
 * Solo actúa si la empresa NO tiene formularios (`isEmpty()`, comprobado en el
 * servicio y no en la vista): nunca pisa datos reales.
 */
class DemoData
{
    public function isEmpty(int $companyId): bool
    {
        return (new FormModel())->where('company_id', $companyId)->countAllResults() === 0;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function seed(int $companyId, ?int $userId): array
    {
        if (!$this->isEmpty($companyId)) {
            return ['ok' => false, 'message' => lang('Frm.demoNotEmpty')];
        }

        $guard = new PlanGuard();
        if (($reason = $guard->blockedReason($companyId)) !== null) {
            return ['ok' => false, 'message' => $reason];
        }

        $svc  = new FormService();
        $defs = TemplateLibrary::definitions();

        // 1. Encuesta de satisfacción, a pantalla completa: es el caso donde esa
        //    presentación luce, una pregunta grande por pantalla.
        $csat = $svc->create($companyId, $userId, 'Encuesta de satisfacción 2026', 'fullscreen', $defs[0]['schema']);
        $this->publish($csat, [
            'display'        => 'fullscreen',
            'flow'           => 'wizard',
            'description'    => 'Su opinión nos ayuda a mejorar. No le tomará más de dos minutos.',
            'submitLabel'    => 'Enviar evaluación',
            'successTitle'   => 'Gracias por su tiempo',
            'successMessage' => 'Sus respuestas quedaron registradas y las revisará el equipo de calidad.',
        ]);

        // 2. Mesa de ayuda, en ficha centrada: un formulario de trabajo diario.
        $help = $svc->create($companyId, $userId, 'Mesa de ayuda interna', 'card', $defs[3]['schema']);
        $this->publish($help, [
            'display'     => 'card',
            'flow'        => 'single',
            'description' => 'Reporte una falla o solicite un acceso.',
            'submitLabel' => 'Radicar solicitud',
        ]);

        $this->fillCsat($csat, $companyId);
        $this->fillHelp($help, $companyId);

        return ['ok' => true, 'message' => lang('Frm.demoDone')];
    }

    /** Publica el formulario con sus ajustes ya puestos. */
    private function publish(int $formId, array $settings): void
    {
        $forms = new FormModel();
        $svc   = new FormService();
        $form  = $forms->find($formId);

        $forms->update($formId, ['settings' => $svc->sanitizeSettings($settings, $form['settings'] ?? [])]);
        $svc->publish($forms->find($formId));
    }

    /** Diez respuestas a la encuesta, con reparto realista de NPS. */
    private function fillCsat(int $formId, int $companyId): void
    {
        $orgs = [
            'Nexus Industrial', 'BioTech Labs', 'Financiera del Norte', 'Cafetera del Valle',
            'Transportes Andina', 'Clínica San Rafael', 'Editorial Meridiano',
            'Constructora Sierra', 'Alimentos del Sur', 'Seguros Continental',
        ];
        $servicios = ['consultoria', 'software', 'soporte', 'otro'];
        $notas     = [
            'Excelente acompañamiento del equipo técnico y cumplimiento de los tiempos pactados.',
            'La migración fue limpia y no tuvimos interrupciones de servicio.',
            'Buen servicio, aunque la respuesta inicial tardó más de lo esperado.',
            'Nos resolvieron un problema que llevaba meses abierto.',
            'Todo correcto. Repetiremos el próximo año.',
        ];

        foreach ($orgs as $i => $org) {
            $rate = [5, 4, 5, 3, 5, 4, 2, 5, 4, 5][$i];
            $nps  = [10, 9, 10, 7, 9, 8, 4, 10, 9, 10][$i];
            $wants = $i % 3 === 0;

            $values = [
                'f_csatorg'  => $org,
                'f_csatmail' => 'contacto' . ($i + 1) . '@' . strtolower(str_replace(' ', '', $org)) . '.com',
                'f_csatphon' => '31' . str_pad((string) (1000000 + $i * 4321), 8, '0'),
                'f_csatserv' => $servicios[$i % 4],
                'f_csatrate' => (string) $rate,
                'f_csatnps'  => (string) $nps,
                'f_csatagn'  => $wants ? '1' : '0',
                'f_csatcom'  => $notas[$i % 5],
            ];
            if ($wants) {
                $values['f_csatwhen'] = 'Mañanas, entre 8 y 11';
            }

            $this->store($formId, $companyId, $values, $i, [
                'f_csatorg' => 'Nombre de la organización', 'f_csatmail' => 'Correo de contacto',
                'f_csatphon' => 'Teléfono', 'f_csatserv' => 'Servicio evaluado',
                'f_csatrate' => 'Nivel de satisfacción general', 'f_csatnps' => 'Probabilidad de recomendarnos',
                'f_csatagn' => 'Deseo que un asesor me contacte', 'f_csatwhen' => 'Mejor horario para contactarle',
                'f_csatcom' => 'Comentarios y sugerencias',
            ]);
        }
    }

    /** Cuatro tickets de soporte, con severidades distintas. */
    private function fillHelp(int $formId, int $companyId): void
    {
        $casos = [
            ['Laura Medina', 'admin', 'critica', 'No carga el módulo de facturación desde esta mañana.'],
            ['Andrés Rojas', 'ventas', 'alta', 'El cliente no recibe los correos de confirmación.'],
            ['Paula Cifuentes', 'oper', 'media', 'La impresora de la bodega imprime en blanco.'],
            ['Diego Salas', 'ti', 'baja', 'Solicito acceso al repositorio de documentación.'],
        ];

        foreach ($casos as $i => [$nombre, $area, $sev, $desc]) {
            $this->store($formId, $companyId, [
                'f_spwho'  => $nombre,
                'f_spmail' => strtolower(str_replace(' ', '.', $nombre)) . '@empresa.com',
                'f_sparea' => $area,
                'f_spsev'  => $sev,
                'f_spdesc' => $desc,
                'f_spwhen' => sprintf('%02d:%02d', 8 + $i, 15 * $i),
            ], $i, [
                'f_spwho' => 'Nombre', 'f_spmail' => 'Correo', 'f_sparea' => 'Área',
                'f_spsev' => 'Severidad', 'f_spdesc' => 'Descripción del incidente',
                'f_spwhen' => 'Hora aproximada',
            ]);
        }
    }

    /**
     * Guarda una respuesta de ejemplo con su índice.
     *
     * La fecha real se pone con un UPDATE posterior porque `created_at` no está
     * en `allowedFields` (lo gobierna `useTimestamps`): sin eso todas serían de
     * hoy y ni las métricas ni el orden del listado enseñarían nada. Es el mismo
     * apaño que usa el generador de PQRSF.
     */
    private function store(int $formId, int $companyId, array $values, int $index, array $labels): void
    {
        $responses = new ResponseModel();
        $forms     = new FormModel();
        $form      = $forms->find($formId);

        $responses->insert([
            'form_id'          => $formId,
            'company_id'       => $companyId,
            'token'            => bin2hex(random_bytes(24)),
            'reference'        => $responses->nextReference($formId),
            'form_version'     => (int) ($form['version'] ?? 1),
            'status'           => 'complete',
            'data'             => $values,
            'labels'           => $labels,
            'ip'               => '127.0.0.1',
            'device'           => ['desktop', 'mobile', 'tablet'][$index % 3],
            'duration_seconds' => 45 + $index * 17,
            'is_read'          => $index > 3 ? 1 : 0,
        ]);

        $responseId = (int) $responses->getInsertID();

        $schema = (new FormService())->liveSchema($form);
        $rows   = (new ResponseService())->buildIndex($schema, $values, $responseId, $formId, $companyId);
        (new \Components\Formularios\Models\AnswerModel())->replaceFor($responseId, $rows);

        $when = date('Y-m-d H:i:s', strtotime('-' . (2 + $index * 3) . ' days +' . (9 + $index) . ' hours'));
        $responses->db->table('cmp_frm_responses')->where('id', $responseId)->update([
            'created_at' => $when,
            'updated_at' => $when,
        ]);
        $responses->db->table('cmp_frm_answers')->where('response_id', $responseId)->update(['created_at' => $when]);

        // Las visitas también se simulan: sin ellas la tasa de finalización no
        // se puede calcular y saldría vacía en el informe.
        $forms->db->table('cmp_frm_forms')->where('id', $formId)
            ->set('views', 'views + ' . (1 + ($index % 3)), false)->update();
    }
}
