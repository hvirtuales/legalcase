<?php

namespace Components\Copropiedad\Controllers;

use Components\Copropiedad\Libraries\RegistryService;
use Components\Copropiedad\Models\FamilyModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\UnitModel;

/**
 * Grupos familiares.
 *
 * La pertenencia se guarda en la persona (`cmp_cop_people.family_id`), así que
 * «añadir un miembro» es escribir en la persona, no en la familia. Se hace desde
 * aquí porque es donde el usuario lo busca.
 */
class Families extends BaseCrud
{
    protected string $route   = 'copropiedad/familias';
    protected string $viewDir = 'families';
    protected string $langKey = 'families';

    public function __construct()
    {
        $this->model = new FamilyModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return view('Components\Copropiedad\Views\families\index', ['families' => null]);
        }

        return view('Components\Copropiedad\Views\families\index', [
            'families' => $this->model->listFor($companyId),
            'counts'   => $this->model->memberCounts($companyId),
        ]);
    }

    public function show($id = null)
    {
        $companyId = $this->companyId();
        $family    = $companyId ? $this->model->owned((int) $id, $companyId) : null;

        if (! $family) {
            return redirect()->to($this->route)->with('error', lang('Cop.errFamilyNotFound'));
        }

        $people = new PersonModel();

        return view('Components\Copropiedad\Views\families\show', [
            'family'  => $family,
            'unit'    => $family['unit_id'] ? (new UnitModel())->owned((int) $family['unit_id'], $companyId) : null,
            'members' => $people->membersOf((int) $id, $companyId),
            // Solo se ofrecen los que no están ya en OTRO grupo: una persona
            // pertenece a uno, y moverla en silencio la sacaría del suyo sin avisar.
            'free'    => $people->forCompany($companyId)->where('family_id', null)
                ->orderBy('last_name', 'asc')->findAll(),
        ]);
    }

    protected function fields(int $companyId): array
    {
        return [
            'company_id'     => $companyId,
            'unit_id'        => $this->int('unit_id'),
            'name'           => (string) $this->str('name', 120),
            'head_person_id' => $this->int('head_person_id'),
            'notes'          => $this->str('notes'),
        ];
    }

    protected function extraCheck(array $data, ?array $existing): ?string
    {
        $companyId = (int) $data['company_id'];

        if ($data['unit_id'] && ! (new UnitModel())->owned($data['unit_id'], $companyId)) {
            return lang('Cop.errUnitNotFound');
        }

        // La cabeza tiene que ser una persona de esta empresa. (Que además sea
        // miembro se comprueba al asignarla desde la ficha, donde ya se sabe.)
        if ($data['head_person_id'] && ! (new PersonModel())->owned($data['head_person_id'], $companyId)) {
            return lang('Cop.errPersonNotFound');
        }

        return null;
    }

    protected function formData(int $companyId, ?array $row = null): array
    {
        return [
            'units'  => (new UnitModel())->optionsFor($companyId, ['apartamento', 'casa', 'local', 'oficina']),
            'people' => $row ? (new PersonModel())->optionsFor($companyId) : [],
        ];
    }

    /** Mete a una persona en el grupo. */
    public function addMember($id = null)
    {
        $companyId = $this->companyId();
        $family    = $companyId ? $this->model->owned((int) $id, $companyId) : null;

        if (! $family) {
            return redirect()->to($this->route)->with('error', lang('Cop.errFamilyNotFound'));
        }

        $people   = new PersonModel();
        $personId = (int) $this->request->getPost('person_id');
        $person   = $people->owned($personId, $companyId);

        if (! $person) {
            return redirect()->back()->with('error', lang('Cop.errPersonNotFound'));
        }

        $role = (string) $this->request->getPost('family_role');
        $people->update($personId, [
            'family_id'   => (int) $family['id'],
            'family_role' => in_array($role, PersonModel::FAMILY_ROLES, true) ? $role : 'otro',
        ]);

        // Si entra como jefe y el grupo no tiene cabeza, se le nombra: es lo que el
        // usuario espera, y evita el paso extra de volver a editar la familia.
        if ($role === 'jefe' && empty($family['head_person_id'])) {
            $this->model->update((int) $family['id'], ['head_person_id' => $personId]);
        }

        return redirect()->to($this->route . '/ficha/' . (int) $family['id'])
            ->with('success', lang('Cop.okMemberAdded'));
    }

    /** Saca a una persona del grupo **sin darla de baja**. */
    public function removeMember($id = null)
    {
        $companyId = $this->companyId();
        $family    = $companyId ? $this->model->owned((int) $id, $companyId) : null;

        if (! $family) {
            return redirect()->to($this->route)->with('error', lang('Cop.errFamilyNotFound'));
        }

        $people   = new PersonModel();
        $personId = (int) $this->request->getPost('person_id');
        $person   = $people->owned($personId, $companyId);

        // Y que sea miembro DE ESTE grupo: con solo comprobar la empresa, un POST
        // preparado a mano sacaría a alguien de la familia de al lado.
        if (! $person || (int) $person['family_id'] !== (int) $family['id']) {
            return redirect()->back()->with('error', lang('Cop.errPersonNotFound'));
        }

        $people->update($personId, ['family_id' => null, 'family_role' => null]);

        if ((int) $family['head_person_id'] === $personId) {
            $this->model->update((int) $family['id'], ['head_person_id' => null]);
        }

        return redirect()->to($this->route . '/ficha/' . (int) $family['id'])
            ->with('success', lang('Cop.okMemberRemoved'));
    }

    /** Borrar el grupo no borra a nadie: los deja sin agrupar. */
    public function delete($id = null)
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNoCompany'));
        }

        $r = (new RegistryService())->deleteFamily((int) $id, $companyId);

        return redirect()->to($this->route)->with($r['ok'] ? 'success' : 'error', $r['message']);
    }
}
