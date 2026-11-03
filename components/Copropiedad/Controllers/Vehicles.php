<?php

namespace Components\Copropiedad\Controllers;

use App\Libraries\DataTable;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\UnitModel;
use Components\Copropiedad\Models\VehicleModel;

/**
 * Vehículos.
 *
 * El listado global existe para la portería: buscar una placa y saber a qué puerta
 * pertenece. Por eso la búsqueda **normaliza** lo que se teclea igual que se
 * normalizó al guardar: escribir «abc 123» tiene que encontrar «ABC123».
 */
class Vehicles extends BaseCrud
{
    protected string $route   = 'copropiedad/vehiculos';
    protected string $viewDir = 'vehicles';
    protected string $langKey = 'vehicles';

    public function __construct()
    {
        $this->model = new VehicleModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return view('Components\Copropiedad\Views\vehicles\index', ['dt' => null]);
        }

        $kind  = (string) ($this->request->getGet('tipo') ?? '');
        $texto = trim((string) $this->request->getGet('q'));

        /*
         * La búsqueda se arma **entera aquí**, con `searchable([])`, y no se le deja
         * a `DataTable`. Dos motivos, y el primero es de seguridad:
         *
         *  1. `DataTable` añade su grupo de búsqueda DESPUÉS de este callback. Un
         *     `orLike()` puesto aquí se encadenaría al `where` de la empresa como
         *     `company_id = X OR plate LIKE …`, y eso enseña los vehículos de los
         *     demás conjuntos. El filtro de empresa nunca puede quedar dentro de un OR.
         *  2. La placa se guardó normalizada (mayúsculas y sin separadores), así que
         *     buscarla exige normalizar también lo tecleado: «abc-123» tiene que
         *     encontrar «ABC123». `DataTable` busca el texto crudo en todas las
         *     columnas por igual, y no puede hacer eso solo con una.
         */
        $placa = VehicleModel::normalizePlate($texto);

        $dt = (new DataTable(new VehicleModel(), $this->request))
            ->searchable([])
            ->sortable(['cmp_cop_vehicles.plate', 'cmp_cop_vehicles.kind', 'cmp_cop_vehicles.brand', 'cmp_cop_units.code'])
            ->defaultSort('cmp_cop_vehicles.plate', 'asc')
            ->query(function ($b) use ($companyId, $kind, $texto, $placa) {
                $b->select('cmp_cop_vehicles.*, cmp_cop_units.code AS unit_code, cmp_cop_blocks.name AS block_name,'
                        . ' cmp_cop_people.first_name, cmp_cop_people.last_name, park.code AS parking_code')
                    ->join('cmp_cop_units', 'cmp_cop_units.id = cmp_cop_vehicles.unit_id', 'left')
                    ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
                    ->join('cmp_cop_people', 'cmp_cop_people.id = cmp_cop_vehicles.person_id', 'left')
                    ->join('cmp_cop_units park', 'park.id = cmp_cop_vehicles.parking_unit_id', 'left')
                    ->where('cmp_cop_vehicles.company_id', $companyId);

                if (in_array($kind, VehicleModel::KINDS, true)) {
                    $b->where('cmp_cop_vehicles.kind', $kind);
                }

                // El OR va agrupado, así que se cuelga como UN término de la
                // conjunción y no puede escaparse del filtro de empresa.
                if ($texto !== '') {
                    $b->groupStart()
                        ->like('cmp_cop_vehicles.brand', $texto)
                        ->orLike('cmp_cop_vehicles.model', $texto)
                        ->orLike('cmp_cop_vehicles.sticker', $texto);

                    if ($placa !== null) {
                        $b->orLike('cmp_cop_vehicles.plate', $placa);
                    }
                    $b->groupEnd();
                }
            })
            ->paginate(20);

        return view('Components\Copropiedad\Views\vehicles\index', ['dt' => $dt, 'kind' => $kind]);
    }

    protected function fields(int $companyId): array
    {
        return [
            'company_id'      => $companyId,
            'unit_id'         => (int) $this->request->getPost('unit_id'),
            'person_id'       => $this->int('person_id'),
            'parking_unit_id' => $this->int('parking_unit_id'),
            'kind'            => $this->pick('kind', VehicleModel::KINDS, 'carro'),
            'plate'           => VehicleModel::normalizePlate($this->str('plate', 15)),
            'brand'           => $this->str('brand', 40),
            'model'           => $this->str('model', 40),
            'color'           => $this->str('color', 30),
            'year'            => $this->int('year'),
            'sticker'         => $this->str('sticker', 30),
            'notes'           => $this->str('notes'),
            'is_active'       => $this->flag('is_active'),
        ];
    }

    protected function extraCheck(array $data, ?array $existing): ?string
    {
        $companyId = (int) $data['company_id'];
        $units     = new UnitModel();

        if (! $units->owned((int) $data['unit_id'], $companyId)) {
            return lang('Cop.errUnitNotFound');
        }
        if ($data['person_id'] && ! (new PersonModel())->owned($data['person_id'], $companyId)) {
            return lang('Cop.errPersonNotFound');
        }

        if ($data['parking_unit_id']) {
            $park = $units->owned($data['parking_unit_id'], $companyId);
            if (! $park) {
                return lang('Cop.errUnitNotFound');
            }
            // Asignar un carro a un apartamento como «puesto» no significa nada.
            if (! in_array($park['kind'], UnitModel::ATTACHABLE_KINDS, true)) {
                return lang('Cop.errNotAParking');
            }
        }

        if ($this->model->plateTaken($companyId, $data['plate'], $existing['id'] ?? null)) {
            return lang('Cop.errPlateTaken', [$data['plate']]);
        }

        return null;
    }

    protected function formData(int $companyId, ?array $row = null): array
    {
        return [
            'units'    => (new UnitModel())->optionsFor($companyId),
            'parkings' => (new UnitModel())->optionsFor($companyId, UnitModel::ATTACHABLE_KINDS),
            'people'   => (new PersonModel())->optionsFor($companyId),
        ];
    }
}
