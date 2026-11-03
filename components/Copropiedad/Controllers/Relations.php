<?php

namespace Components\Copropiedad\Controllers;

use App\Controllers\BaseController;
use Components\Copropiedad\Libraries\RegistryService;

/**
 * Relación persona ↔ inmueble: abrir, cerrar, reabrir y (para los errores de dedo)
 * eliminar.
 *
 * Solo POST: son todas mutaciones. La lógica —las dos comprobaciones de pertenencia,
 * el duplicado, el recálculo del estado de ocupación— está en `RegistryService`, no
 * aquí: este controlador solo traduce petición ↔ respuesta.
 *
 * **Cerrar no es eliminar.** Cerrar deja la fila como historia; eliminar la borra
 * porque nunca debió existir. Son dos rutas, dos permisos (`edit` y `delete`) y dos
 * botones distintos, porque confundirlos borra la memoria del inmueble.
 */
class Relations extends BaseController
{
    private RegistryService $registry;

    public function __construct()
    {
        $this->registry = new RegistryService();
    }

    private function companyId(): ?int
    {
        $id = session()->get('active_company_id');

        return $id ? (int) $id : null;
    }

    /** A dónde volver: la ficha del inmueble o de la persona, según de dónde venga. */
    private function back(int $unitId)
    {
        $to = (string) $this->request->getPost('return_to');

        if ($to !== '' && preg_match('~^copropiedad/(inmuebles|personas)/ficha/\d+$~', $to)) {
            return redirect()->to(base_url($to));
        }

        return redirect()->to(base_url('copropiedad/inmuebles/ficha/' . $unitId));
    }

    public function store()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to('copropiedad/inmuebles')->with('error', lang('Cop.errNoCompany'));
        }

        $r = $this->registry->openRelation([
            'unit_id'    => (int) $this->request->getPost('unit_id'),
            'person_id'  => (int) $this->request->getPost('person_id'),
            'role'       => (string) $this->request->getPost('role'),
            'share'      => (string) $this->request->getPost('share'),
            'is_main'    => $this->request->getPost('is_main'),
            'start_date' => (string) $this->request->getPost('start_date'),
            'notes'      => (string) $this->request->getPost('notes'),
        ], $companyId);

        return $this->back((int) $this->request->getPost('unit_id'))
            ->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    public function close($id = null)
    {
        return $this->act('closeRelation', (int) $id);
    }

    public function reopen($id = null)
    {
        return $this->act('reopenRelation', (int) $id);
    }

    public function delete($id = null)
    {
        return $this->act('deleteRelation', (int) $id);
    }

    /** Las tres acciones son la misma forma: resolver, llamar al servicio, volver. */
    private function act(string $method, int $id)
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to('copropiedad/inmuebles')->with('error', lang('Cop.errNoCompany'));
        }

        // Se lee la fila ANTES de tocarla para saber a qué inmueble volver: después
        // de cerrarla o borrarla, el dato podría no estar donde se espera.
        $row    = (new \Components\Copropiedad\Models\RelationModel())->owned($id, $companyId);
        $unitId = $row ? (int) $row['unit_id'] : 0;

        $r = $method === 'closeRelation'
            ? $this->registry->closeRelation($id, $companyId, (string) $this->request->getPost('end_date') ?: null)
            : $this->registry->{$method}($id, $companyId);

        return $this->back($unitId)->with($r['ok'] ? 'success' : 'error', $r['message']);
    }
}
