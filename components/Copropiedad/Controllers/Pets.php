<?php

namespace Components\Copropiedad\Controllers;

use App\Libraries\DataTable;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\PetModel;
use Components\Copropiedad\Models\UnitModel;

/**
 * Mascotas.
 */
class Pets extends BaseCrud
{
    protected string $route   = 'copropiedad/mascotas';
    protected string $viewDir = 'pets';
    protected string $langKey = 'pets';

    public function __construct()
    {
        $this->model = new PetModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return view('Components\Copropiedad\Views\pets\index', ['dt' => null]);
        }

        $species   = (string) ($this->request->getGet('especie') ?? '');
        $dangerous = $this->request->getGet('especial') !== null;

        $dt = (new DataTable(new PetModel(), $this->request))
            ->searchable(['cmp_cop_pets.name', 'cmp_cop_pets.breed', 'cmp_cop_pets.chip'])
            ->sortable(['cmp_cop_pets.name', 'cmp_cop_pets.species', 'cmp_cop_pets.vaccine_expires', 'cmp_cop_units.code'])
            ->defaultSort('cmp_cop_pets.name', 'asc')
            ->query(function ($b) use ($companyId, $species, $dangerous) {
                $b->select('cmp_cop_pets.*, cmp_cop_units.code AS unit_code, cmp_cop_blocks.name AS block_name,'
                        . ' cmp_cop_people.first_name, cmp_cop_people.last_name')
                    ->join('cmp_cop_units', 'cmp_cop_units.id = cmp_cop_pets.unit_id', 'left')
                    ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
                    ->join('cmp_cop_people', 'cmp_cop_people.id = cmp_cop_pets.person_id', 'left')
                    ->where('cmp_cop_pets.company_id', $companyId);

                if (in_array($species, PetModel::SPECIES, true)) {
                    $b->where('cmp_cop_pets.species', $species);
                }
                if ($dangerous) {
                    $b->where('cmp_cop_pets.is_dangerous', 1);
                }
            })
            ->paginate(20);

        return view('Components\Copropiedad\Views\pets\index', [
            'dt'        => $dt,
            'species'   => $species,
            'dangerous' => $dangerous,
        ]);
    }

    protected function fields(int $companyId): array
    {
        $data = [
            'company_id'      => $companyId,
            'unit_id'         => (int) $this->request->getPost('unit_id'),
            'person_id'       => $this->int('person_id'),
            'name'            => (string) $this->str('name', 60),
            'species'         => $this->pick('species', PetModel::SPECIES, 'perro'),
            'breed'           => $this->str('breed', 60),
            'color'           => $this->str('color', 40),
            'size'            => $this->str('size') ? $this->pick('size', PetModel::SIZES, 'mediano') : null,
            'birthdate'       => $this->date('birthdate'),
            'chip'            => $this->str('chip', 40),
            'vaccine_date'    => $this->date('vaccine_date'),
            'vaccine_expires' => $this->date('vaccine_expires'),
            'is_dangerous'    => $this->flag('is_dangerous'),
            'notes'           => $this->str('notes'),
            'is_active'       => $this->flag('is_active'),
        ];

        if ($foto = $this->uploadPhoto('photo', 'copropiedad/mascotas', 'pet')) {
            $data['photo'] = $foto;
        }

        return $data;
    }

    protected function extraCheck(array $data, ?array $existing): ?string
    {
        $companyId = (int) $data['company_id'];

        if (! (new UnitModel())->owned((int) $data['unit_id'], $companyId)) {
            return lang('Cop.errUnitNotFound');
        }
        if ($data['person_id'] && ! (new PersonModel())->owned($data['person_id'], $companyId)) {
            return lang('Cop.errPersonNotFound');
        }

        return null;
    }

    protected function formData(int $companyId, ?array $row = null): array
    {
        return [
            'units'  => (new UnitModel())->optionsFor($companyId),
            'people' => (new PersonModel())->optionsFor($companyId),
        ];
    }
}
