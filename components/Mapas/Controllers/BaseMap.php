<?php

namespace Components\Mapas\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Model;
use Components\Mapas\Libraries\MapService;

/**
 * Lo que comparten los controladores internos del componente.
 *
 * Los cinco repetían las mismas cuatro cosas: leer la empresa activa, resolver
 * el `MapService`, componer un error JSON y comprobar que la fila que llega por
 * el POST es de esta empresa. Repetido cinco veces, el día que una de ellas
 * cambia —y `fail()` ya cambió una vez, para devolver el token rotado— hay que
 * acordarse de cinco sitios, y el que se olvida no da error: responde distinto.
 *
 * **Aquí vive la garantía del token.** `ok()` y `fail()` son las dos únicas
 * puertas de salida JSON del componente y las dos incluyen `csrf_hash()`. El
 * token rota en cada POST; una respuesta que no devuelva el nuevo hace que el
 * siguiente guardado dé 403, y el editor lo enmascara reintentando —así que el
 * fallo se paga en un viaje de más y no se ve nunca—.
 *
 * `Publico` NO cuelga de aquí a propósito: extiende `Controller` porque un
 * visitante no tiene sesión, ni empresa activa, ni token que rotar.
 */
abstract class BaseMap extends BaseController
{
    protected MapService $svc;

    public function __construct()
    {
        $this->svc = new MapService();
    }

    /** La empresa activa, o null. Todo el aislamiento del componente parte de aquí. */
    protected function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /**
     * Una fila **de la empresa activa**, o null.
     *
     * Tener el permiso no autoriza a tocar la fila de otra empresa, y el id
     * llega de un POST: la comprobación de pertenencia es aparte de la de
     * permiso, y va en el servidor.
     */
    protected function mine(Model $model, $id, int $companyId): ?array
    {
        $row = $model->find((int) $id);

        return ($row && (int) $row['company_id'] === $companyId) ? $row : null;
    }

    /** Respuesta JSON correcta, con el token nuevo. */
    protected function ok(array $extra = [])
    {
        return $this->response->setJSON(['ok' => true] + $extra + ['csrf' => csrf_hash()]);
    }

    /** Respuesta JSON de error, con el token nuevo. */
    protected function fail(string $msg, int $code = 400)
    {
        return $this->response->setStatusCode($code)
            ->setJSON(['ok' => false, 'error' => $msg, 'csrf' => csrf_hash()]);
    }
}
