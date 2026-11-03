<?php

namespace Components\Copropiedad\Models;

use CodeIgniter\Model;
use Components\Copropiedad\Models\Concerns\CompanyScoped;

/**
 * Torres, bloques, etapas y manzanas.
 *
 * `KINDS` es la lista válida de `kind`, validada aquí y no con un ENUM en la BD
 * (§8: portabilidad a tres motores).
 */
class BlockModel extends Model
{
    use CompanyScoped;

    public const KINDS = ['torre', 'bloque', 'etapa', 'manzana', 'interior'];

    protected $table            = 'cmp_cop_blocks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'name', 'code', 'kind', 'floors', 'sort_order', 'notes', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[1]|max_length[80]',
        'code'       => 'permit_empty|max_length[20]',
        'kind'       => 'required|in_list[torre,bloque,etapa,manzana,interior]',
        'floors'     => 'permit_empty|is_natural',
    ];

    public function __construct()
    {
        parent::__construct();

        // Los mensajes se arman aquí y no en la declaración de la propiedad porque
        // `lang()` necesita el locale ya resuelto (contrato §7.2: nada de texto
        // visible incrustado, tampoco en las reglas).
        $this->validationMessages = [
            'name' => ['required' => lang('Cop.vBlockName')],
            'kind' => ['in_list'  => lang('Cop.vBlockKind')],
        ];
    }

    /** Las torres de la empresa, en el orden que fijó el administrador. */
    public function listFor(int $companyId, bool $onlyActive = false): array
    {
        $q = $this->forCompany($companyId);
        if ($onlyActive) {
            $q->where('is_active', 1);
        }

        return $q->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->findAll();
    }

    /** `[id => nombre]`, para los selectores. */
    public function optionsFor(int $companyId): array
    {
        $out = [];

        foreach ($this->listFor($companyId, true) as $b) {
            $out[(int) $b['id']] = $b['name'];
        }

        return $out;
    }
}
