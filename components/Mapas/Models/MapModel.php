<?php

namespace Components\Mapas\Models;

use CodeIgniter\Model;

class MapModel extends Model
{
    protected $table          = 'cmp_map_maps';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'company_id', 'name', 'description', 'public_token', 'is_published', 'views',
        'logo', 'show_header',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[150]',
    ];
    protected $validationMessages = [
        // La clave a secas y no `lang()`: CI4 pasa los mensajes personalizados
        // por lang() al pintarlos, y llamarlo aquí instanciaría el servicio de
        // idioma antes de que el controlador fije el locale.
        'name' => ['required' => 'Map.vrName'],
    ];
}
