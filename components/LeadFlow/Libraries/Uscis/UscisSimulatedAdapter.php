<?php

namespace Components\LeadFlow\Libraries\Uscis;

/**
 * USCIS simulado: recorre las pantallas sin gastar cupo ni credenciales.
 *
 * **No es un adorno de desarrollo.** El cupo real es de 1.000 consultas al día por
 * firma, y montar la pantalla pública a base de pruebas contra la API real se come un
 * día entero de servicio de esa firma. Con esto se prueba el flujo completo —incluido
 * el caso que no existe, que es el que más se olvida— sin tocar nada.
 *
 * **Y además simula lo que el sandbox NO simula.** Comprobado contra la API real: los
 * tres recibos de prueba devuelven todos lo mismo —un I-130 aprobado—, así que contra
 * el sandbox no hay forma de ver cómo queda una petición de evidencia adicional o un
 * rechazo. Aquí sí, y por eso los tres estados son distintos: una pantalla probada
 * solo con el camino feliz enseña cómo se ve un caso problemático el día que le pasa
 * a un cliente de verdad.
 *
 * Devuelve la misma forma que el adaptador HTTP, español incluido: si no, la vista
 * funcionaría en simulado y se rompería en real, que es el peor momento para enterarse.
 */
class UscisSimulatedAdapter implements CaseStatusProvider
{
    /** Los tres del sandbox, con tres desenlaces distintos. */
    public const CASES = [
        'EAC9999103403' => [
            'form'      => 'I-765',
            'status'    => 'Case Was Received',
            'status_es' => 'Caso Fue Recibido',
            'detail'    => 'We received your Form I-765, Application for Employment Authorization, and sent you a receipt notice.',
            'detail_es' => 'Recibimos su Formulario I-765, Solicitud de Autorización de Empleo, y le enviamos un aviso de recibo.',
            'submitted' => '-120 days',
            'updated'   => '-118 days',
            'hist'      => [
                ['-118 days', 'We received your Form I-765.', 'Recibimos su Formulario I-765.'],
            ],
        ],
        'LIN9999106498' => [
            'form'      => 'I-130',
            'status'    => 'Request for Additional Evidence Was Sent',
            'status_es' => 'Se Envió Solicitud de Evidencia Adicional',
            'detail'    => 'We mailed you a notice requesting additional evidence for your Form I-130, Petition for Alien Relative.',
            'detail_es' => 'Le enviamos por correo un aviso solicitando evidencia adicional para su Formulario I-130, Petición de Familiar Extranjero.',
            'submitted' => '-260 days',
            'updated'   => '-14 days',
            'hist'      => [
                ['-260 days', 'We received your Form I-130.', 'Recibimos su Formulario I-130.'],
                ['-14 days', 'We mailed a request for additional evidence.', 'Enviamos por correo una solicitud de evidencia adicional.'],
            ],
        ],
        'SRC9999102777' => [
            'form'      => 'I-485',
            'status'    => 'Case Was Approved',
            'status_es' => 'Caso Fue Aprobado',
            'detail'    => 'We approved your Form I-485, Application to Register Permanent Residence or Adjust Status, and mailed you an approval notice.',
            'detail_es' => 'Aprobamos su Formulario I-485, Solicitud para Registrar Residencia Permanente o Ajustar Estatus, y le enviamos un aviso de aprobación.',
            'submitted' => '-400 days',
            'updated'   => '-30 days',
            'hist'      => [
                ['-400 days', 'We received your Form I-485.', 'Recibimos su Formulario I-485.'],
                ['-30 days', 'We approved your Form I-485.', 'Aprobamos su Formulario I-485.'],
            ],
        ],
    ];

    public function mode(): string
    {
        return 'simulated';
    }

    public function token(): string
    {
        return 'simulated-token';
    }

    public function caseStatus(string $receipt): array
    {
        $receipt = strtoupper(trim($receipt));
        $row     = self::CASES[$receipt] ?? null;

        if ($row === null) {
            // Lo mismo que hace la API real con un recibo que no existe: no es un
            // error de conexión, es una respuesta.
            throw new UscisException(lang('Lfl.uscNotFound'));
        }

        $hist = [];
        foreach ($row['hist'] as [$cuando, $en, $es]) {
            $hist[] = ['date' => date('Y-m-d', strtotime($cuando)), 'text' => $en, 'text_es' => $es];
        }

        return [
            'receipt'      => $receipt,
            'form_type'    => $row['form'],
            'status'       => $row['status'],
            'status_es'    => $row['status_es'],
            'detail'       => $row['detail'],
            'detail_es'    => $row['detail_es'],
            'submitted_at' => date('Y-m-d', strtotime($row['submitted'])),
            'updated_at'   => date('Y-m-d', strtotime($row['updated'])),
            'history'      => $hist,
        ];
    }
}
