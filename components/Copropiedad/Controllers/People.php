<?php

namespace Components\Copropiedad\Controllers;

use App\Libraries\DataTable;
use Components\Copropiedad\Libraries\RegistryService;
use Components\Copropiedad\Models\ContactModel;
use Components\Copropiedad\Models\FamilyModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\PetModel;
use Components\Copropiedad\Models\RelationModel;
use Components\Copropiedad\Models\VehicleModel;

/**
 * Personas del censo.
 *
 * El **papel** (propietario, residente, arrendatario, ocupante) no se edita aquí:
 * se abre y se cierra desde la ficha del inmueble, porque un papel es una relación
 * con un inmueble concreto y con unas fechas, no un atributo de la persona.
 */
class People extends BaseCrud
{
    protected string $route   = 'copropiedad/personas';
    protected string $viewDir = 'people';
    protected string $langKey = 'people';

    public function __construct()
    {
        $this->model = new PersonModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return view('Components\Copropiedad\Views\people\index', ['dt' => null]);
        }

        $role = (string) ($this->request->getGet('papel') ?? '');

        $dt = (new DataTable(new PersonModel(), $this->request))
            ->searchable([
                'cmp_cop_people.first_name', 'cmp_cop_people.last_name',
                'cmp_cop_people.doc_number', 'cmp_cop_people.email', 'cmp_cop_people.phone',
            ])
            ->sortable([
                'cmp_cop_people.last_name', 'cmp_cop_people.first_name',
                'cmp_cop_people.doc_number', 'cmp_cop_people.email',
            ])
            ->defaultSort('cmp_cop_people.last_name', 'asc')
            ->query(function ($b) use ($companyId, $role) {
                $b->select('cmp_cop_people.*, cmp_cop_families.name AS family_name')
                    ->join('cmp_cop_families', 'cmp_cop_families.id = cmp_cop_people.family_id', 'left')
                    ->where('cmp_cop_people.company_id', $companyId);

                // Filtrar por papel es filtrar por «tiene una relación vigente con
                // ese papel»: una subconsulta, porque el papel no está en la persona.
                if (in_array($role, RelationModel::ROLES, true)) {
                    $ids = array_column(
                        (new RelationModel())->builder()
                            ->select('person_id')->distinct()
                            ->where('company_id', $companyId)
                            ->where('role', $role)
                            ->where('is_current', 1)
                            ->where('deleted_at', null)
                            ->get()->getResultArray(),
                        'person_id'
                    );
                    // Sin resultados hay que forzar el vacío: un `whereIn([])` en
                    // algunos motores no filtra nada y devolvería el censo entero.
                    $b->whereIn('cmp_cop_people.id', $ids ?: [0]);
                }
            })
            ->paginate(20);

        return view('Components\Copropiedad\Views\people\index', [
            'dt'      => $dt,
            'role'    => $role,
            'roleMap' => $this->currentRoleMap($companyId, array_column($dt['rows'], 'id')),
        ]);
    }

    /**
     * Qué papeles tiene hoy cada persona de la página, en **una** consulta.
     *
     * Preguntarlo por fila eran veinte consultas por pantalla; y sin el dato, el
     * listado no puede decir si alguien es propietario o arrendatario, que es lo
     * primero que se busca.
     */
    private function currentRoleMap(int $companyId, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $rows = (new RelationModel())->builder()
            ->select('person_id, role')
            ->where('company_id', $companyId)
            ->whereIn('person_id', $ids)
            ->where('is_current', 1)
            ->where('deleted_at', null)
            ->get()->getResultArray();

        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r['person_id']][$r['role']] = true;
        }

        return array_map('array_keys', $out);
    }

    /** La ficha de la persona: dónde vive o ha vivido, y qué tiene registrado. */
    public function show($id = null)
    {
        $companyId = $this->companyId();
        $person    = $companyId ? $this->model->owned((int) $id, $companyId) : null;

        if (! $person) {
            return redirect()->to($this->route)->with('error', lang('Cop.errPersonNotFound'));
        }

        return view('Components\Copropiedad\Views\people\show', [
            'person'    => $person,
            'family'    => $person['family_id'] ? (new FamilyModel())->owned((int) $person['family_id'], $companyId) : null,
            'relations' => (new RelationModel())->forPerson((int) $id, $companyId),
            // Para asignarle un papel desde aquí: es el mismo formulario de la ficha
            // del inmueble, leído por el otro extremo.
            'units'     => (new \Components\Copropiedad\Models\UnitModel())->optionsFor($companyId),
            'pets'      => (new PetModel())->forCompany($companyId)->where('person_id', $id)->findAll(),
            'vehicles'  => (new VehicleModel())->forCompany($companyId)->where('person_id', $id)->findAll(),
            'contacts'  => (new ContactModel())->forPerson((int) $id, $companyId),
            'documents' => (new \Components\Copropiedad\Models\DocumentModel())->forOwner('person', (int) $id, $companyId),
        ]);
    }

    /** Autocompletado del formulario de relaciones (JSON). */
    public function search()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return $this->response->setJSON(['ok' => false, 'people' => []]);
        }

        $rows = $this->model->search($companyId, (string) $this->request->getGet('q'));

        return $this->response->setJSON([
            'ok'     => true,
            'people' => array_map(static fn ($p) => [
                'id'    => (int) $p['id'],
                'name'  => PersonModel::fullName($p),
                'doc'   => $p['doc_type'] . ' ' . $p['doc_number'],
                'phone' => (string) ($p['phone'] ?? ''),
            ], $rows),
        ]);
    }

    protected function fields(int $companyId): array
    {
        $data = [
            'company_id'  => $companyId,
            'family_id'   => $this->int('family_id'),
            'family_role' => $this->pick('family_role', PersonModel::FAMILY_ROLES, 'otro'),
            'doc_type'    => $this->pick('doc_type', PersonModel::DOC_TYPES, 'CC'),
            'doc_number'  => (string) $this->str('doc_number', 30),
            'first_name'  => (string) $this->str('first_name', 60),
            'last_name'   => (string) $this->str('last_name', 60),
            'email'       => $this->str('email', 120),
            'phone'       => $this->str('phone', 30),
            'whatsapp'    => $this->str('whatsapp', 30),
            'birthdate'   => $this->date('birthdate'),
            'occupation'  => $this->str('occupation', 80),
            'notes'       => $this->str('notes'),
            'is_active'   => $this->flag('is_active'),
        ];

        // Sin grupo familiar, el parentesco no significa nada.
        if ($data['family_id'] === null) {
            $data['family_role'] = null;
        }

        if ($foto = $this->uploadPhoto('photo', 'copropiedad/personas', 'per')) {
            $data['photo'] = $foto;
        }

        return $data;
    }

    protected function extraCheck(array $data, ?array $existing): ?string
    {
        if ($this->model->docTaken((int) $data['company_id'], $data['doc_number'], $existing['id'] ?? null)) {
            return lang('Cop.errPersonDocTaken', [$data['doc_number']]);
        }

        if ($data['family_id'] && ! (new FamilyModel())->owned($data['family_id'], (int) $data['company_id'])) {
            return lang('Cop.errFamilyNotFound');
        }

        return null;
    }

    protected function formData(int $companyId, ?array $row = null): array
    {
        return ['families' => (new FamilyModel())->optionsFor($companyId)];
    }

    /**
     * Recién creada, la persona va a **su ficha**, no al listado.
     *
     * Una persona sin inmueble no es nadie en el censo, y el papel se asigna en la
     * ficha: devolver al listado obligaba a buscarla otra vez para terminar. Al
     * editar no aplica —ahí sí se vuelve a la lista, que es de donde se venía—.
     */
    protected function afterCreateRoute(int $id): ?string
    {
        return $this->route . '/ficha/' . $id;
    }

    /** La baja cierra sus relaciones (no las borra) y suelta sus referencias. */
    public function delete($id = null)
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to($this->route)->with('error', lang('Cop.errNoCompany'));
        }

        $r = (new RegistryService())->deletePerson((int) $id, $companyId);

        return redirect()->to($this->route)->with($r['ok'] ? 'success' : 'error', $r['message']);
    }
}
