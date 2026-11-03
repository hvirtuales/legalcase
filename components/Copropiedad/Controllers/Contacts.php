<?php

namespace Components\Copropiedad\Controllers;

use App\Libraries\DataTable;
use Components\Copropiedad\Models\ContactModel;
use Components\Copropiedad\Models\PersonModel;
use Components\Copropiedad\Models\UnitModel;

/**
 * Contactos de emergencia.
 *
 * No tiene entrada en el menú: se llega desde la ficha del inmueble o de la persona,
 * que es donde tiene sentido consultarlos. La ruta existe igual para poder editarlos
 * y para el listado completo, que la administración imprime.
 */
class Contacts extends BaseCrud
{
    protected string $route   = 'copropiedad/contactos';
    protected string $viewDir = 'contacts';
    protected string $langKey = 'contacts';

    public function __construct()
    {
        $this->model = new ContactModel();
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return view('Components\Copropiedad\Views\contacts\index', ['dt' => null]);
        }

        $dt = (new DataTable(new ContactModel(), $this->request))
            ->searchable([
                'cmp_cop_contacts.name', 'cmp_cop_contacts.phone',
                'cmp_cop_contacts.alt_phone', 'cmp_cop_contacts.relationship',
            ])
            ->sortable(['cmp_cop_contacts.name', 'cmp_cop_contacts.relationship', 'cmp_cop_units.code'])
            ->defaultSort('cmp_cop_contacts.name', 'asc')
            ->query(function ($b) use ($companyId) {
                $b->select('cmp_cop_contacts.*, cmp_cop_units.code AS unit_code, cmp_cop_blocks.name AS block_name,'
                        . ' cmp_cop_people.first_name, cmp_cop_people.last_name')
                    ->join('cmp_cop_units', 'cmp_cop_units.id = cmp_cop_contacts.unit_id', 'left')
                    ->join('cmp_cop_blocks', 'cmp_cop_blocks.id = cmp_cop_units.block_id', 'left')
                    ->join('cmp_cop_people', 'cmp_cop_people.id = cmp_cop_contacts.person_id', 'left')
                    ->where('cmp_cop_contacts.company_id', $companyId);
            })
            ->paginate(20);

        return view('Components\Copropiedad\Views\contacts\index', ['dt' => $dt]);
    }

    protected function fields(int $companyId): array
    {
        return [
            'company_id'   => $companyId,
            'unit_id'      => $this->int('unit_id'),
            'person_id'    => $this->int('person_id'),
            'name'         => (string) $this->str('name', 100),
            'relationship' => $this->str('relationship', 40),
            'phone'        => (string) $this->str('phone', 30),
            'alt_phone'    => $this->str('alt_phone', 30),
            'email'        => $this->str('email', 120),
            'is_primary'   => $this->flag('is_primary'),
            'notes'        => $this->str('notes'),
        ];
    }

    protected function extraCheck(array $data, ?array $existing): ?string
    {
        $companyId = (int) $data['company_id'];

        // Un contacto de emergencia que no cubre a nadie no sirve a las 3 de la
        // mañana, que es cuando se usa.
        if (! ContactModel::atLeastOneOwner($data)) {
            return lang('Cop.errContactOwner');
        }
        if ($data['unit_id'] && ! (new UnitModel())->owned($data['unit_id'], $companyId)) {
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
