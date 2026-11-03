<?php

namespace Components\LeadFlow\Libraries\Uscis;

/**
 * Lo que la plataforma necesita de USCIS, y nada más.
 *
 * **Interfaz propia y no `IntegrationAdapter`.** Ese contrato está modelado para
 * Docketwise: OAuth de usuario, contactos, expedientes, notas y documentos. USCIS es
 * `client_credentials` y **una sola operación de lectura**. Encajarlo allí dejaría diez
 * métodos lanzando «no soportado», y una interfaz en la que la mitad de los métodos no
 * se pueden llamar deja de decir nada sobre lo que un adaptador sabe hacer.
 *
 * Dos implementaciones, como en Docketwise: la simulada —para recorrer las pantallas
 * sin gastar cupo— y la HTTP.
 */
interface CaseStatusProvider
{
    /** `simulated` o `live`, para que la pantalla diga contra qué está hablando. */
    public function mode(): string;

    /**
     * Comprueba que las credenciales sirven, sin consultar ningún caso.
     *
     * Devuelve el token si lo consigue. Lanza `UscisException` con un mensaje legible
     * si no: esa distinción es la que permite decirle a la firma «tu secret está mal»
     * en vez de «algo falló».
     */
    public function token(): string;

    /**
     * El estado de un caso por su número de recibo.
     *
     * USCIS devuelve el estado en inglés Y en español, y los dos se conservan: el
     * público de este producto es hispanohablante, y traducir nosotros un texto
     * legal sería inventar matices que nadie dijo.
     *
     * @return array{receipt:string,form_type:?string,status:?string,status_es:?string,detail:?string,detail_es:?string,submitted_at:?string,updated_at:?string,history:array}
     *
     * @throws UscisException cuando la consulta no se puede completar
     */
    public function caseStatus(string $receipt): array;
}
