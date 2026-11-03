<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;

/**
 * Jurisdicciones donde un profesional está autorizado a ejercer. **Global**, por la
 * misma razón que las áreas: es un filtro del buscador.
 *
 * La lista inicial apunta al foco del §9 del insumo —inmigración para
 * hispanohablantes en Estados Unidos—, así que trae la jurisdicción federal de
 * inmigración y los estados con más población hispanohablante, más Colombia para el
 * mercado local. Se amplía con filas nuevas, no con código.
 */
class JurisdictionModel extends Model
{
    /** code => [nombre, país, orden]. El nombre es un topónimo: no se traduce. */
    public const SYSTEM = [
        'US-IMM' => ['Inmigración (federal, EE. UU.)', 'US', 10],
        'US-FL'  => ['Florida', 'US', 20],
        'US-TX'  => ['Texas', 'US', 30],
        'US-CA'  => ['California', 'US', 40],
        'US-NY'  => ['Nueva York', 'US', 50],
        'US-NJ'  => ['Nueva Jersey', 'US', 60],
        'US-IL'  => ['Illinois', 'US', 70],
        'US-GA'  => ['Georgia', 'US', 80],
        'US-AZ'  => ['Arizona', 'US', 90],
        'US-NV'  => ['Nevada', 'US', 100],
        'US-NC'  => ['Carolina del Norte', 'US', 110],
        'CO-NAL' => ['Colombia (nacional)', 'CO', 120],
    ];

    protected $table         = 'cmp_lfl_jurisdictions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'name', 'country', 'sort', 'is_active'];

    protected $validationRules = [
        'code' => 'required|max_length[20]',
        'name' => 'required|max_length[120]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vName'],
    ];

    /** @return array<int,array> */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('sort', 'asc')->orderBy('name', 'asc')->findAll();
    }

    /** @return array<int,string> id => nombre */
    public function options(): array
    {
        return array_column($this->active(), 'name', 'id');
    }

    public function byCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }
}
