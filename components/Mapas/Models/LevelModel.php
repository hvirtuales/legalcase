<?php

namespace Components\Mapas\Models;

use CodeIgniter\Model;

class LevelModel extends Model
{
    protected $table          = 'cmp_map_levels';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'company_id', 'map_id', 'name', 'image', 'img_width', 'img_height', 'sort_order', 'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'map_id'     => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[1]|max_length[100]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Map.vrLevel'],
    ];

    /** Los niveles de un mapa, en su orden. */
    public function forMap(int $mapId, bool $onlyActive = false): array
    {
        $q = $this->where('map_id', $mapId);
        if ($onlyActive) {
            $q->where('is_active', 1);
        }

        return $q->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->findAll();
    }

    /** El siguiente hueco en el orden, para que un nivel nuevo caiga al final. */
    public function nextOrder(int $mapId): int
    {
        $row = $this->selectMax('sort_order')->where('map_id', $mapId)->first();

        return ((int) ($row['sort_order'] ?? 0)) + 1;
    }
}
