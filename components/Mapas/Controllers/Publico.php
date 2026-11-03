<?php

namespace Components\Mapas\Controllers;

use App\Models\CompanyModel;
use CodeIgniter\Controller;
use Components\Mapas\Libraries\MapService;
use Components\Mapas\Libraries\PlanGuard;
use Components\Mapas\Models\MapModel;

/**
 * El mapa que ve el visitante. Sin sesión: se entra con el token de la URL.
 *
 * Un 404 **no distingue** entre «no existe», «está sin publicar» y «el plan de
 * la empresa ya no lo incluye». Distinguirlos convertiría la URL en una sonda
 * de lo que tiene contratado cada empresa.
 */
class Publico extends Controller
{
    /**
     * El idioma. Este controlador NO extiende `BaseController` —un visitante no
     * tiene sesión ni permisos—, así que nadie se lo resuelve; sin esto la
     * página saldría siempre en el idioma por defecto aunque el visitante ya
     * hubiera elegido otro en la plataforma.
     */
    private function locale(): void
    {
        $lang = session()->get('language');
        if ($lang) {
            $this->request->setLocale((string) $lang);
        }
    }

    /** El mapa publicado cuyo plan sigue incluyendo el servicio, o null. */
    private function visible(string $token): ?array
    {
        $map = (new MapService())->byToken($token);
        if (!$map) {
            return null;
        }

        return (new PlanGuard())->serviceEnabled((int) $map['company_id']) ? $map : null;
    }

    public function show($token = null)
    {
        $this->locale();
        $map = $this->visible((string) $token);
        if (!$map) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // El contador se sube aquí y no en `payload()`: la página se abre una
        // vez y los datos se piden en cada cambio de nivel, así que contarlo
        // allí multiplicaría las visitas por el número de plantas.
        (new MapModel())->set('views', 'views + 1', false)->where('id', $map['id'])->update();

        return view('Components\Mapas\Views\public\map', [
            'map'     => $map,
            'company' => (new CompanyModel())->find((int) $map['company_id']) ?? [],
            // `onlyActive`: lo que está desactivado no sale en la calle.
            'payload' => (new MapService())->payload($map, true),
        ]);
    }

    /** El mismo árbol, por si el visor lo quiere refrescar sin recargar. */
    public function payload($token = null)
    {
        $this->locale();
        $map = $this->visible((string) $token);
        if (!$map) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false]);
        }

        return $this->response->setJSON([
            'ok'   => true,
            'data' => (new MapService())->payload($map, true),
        ]);
    }
}
