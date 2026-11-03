<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\BelongsToUnit;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Contactos de emergencia.
 *
 * Las dos referencias (`unit_id`, `person_id`) son opcionales por separado, pero
 * **al menos una tiene que venir**: eso no lo puede decir una regla de campo, así que
 * lo comprueba `atLeastOneOwner()` y lo hace valer el controlador.
 */
class ContactModel extends Model
{
    use BelongsToUnit;
    use CompanyScoped;

    protected $table            = 'cmp_cop_contacts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'unit_id', 'person_id', 'name', 'relationship',
        'phone', 'alt_phone', 'email', 'is_primary', 'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[100]',
        'phone'      => 'required|min_length[5]|max_length[30]',
        'email'      => 'permit_empty|valid_email|max_length[120]',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->validationMessages = [
            'name'  => ['required' => lang('Cop.vContactName')],
            'phone' => ['required' => lang('Cop.vContactPhone')],
        ];
    }

    /** Un contacto de emergencia que no cubre a nadie no sirve para nada. */
    public static function atLeastOneOwner(array $data): bool
    {
        return ! empty($data['unit_id']) || ! empty($data['person_id']);
    }

    /** Los de una persona (los suyos propios y los de su inmueble). */
    public function forPerson(int $personId, int $companyId): array
    {
        return $this->forCompany($companyId)
            ->where('person_id', $personId)
            ->orderBy('is_primary', 'desc')
            ->findAll();
    }
}
