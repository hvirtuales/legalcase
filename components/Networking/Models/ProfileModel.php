<?php

namespace Components\Networking\Models;

use CodeIgniter\Model;

class ProfileModel extends Model
{
    protected $table            = 'cmp_net_profiles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    // La identidad (nombre, correo, teléfono) vive en `users` y la empresa en
    // `companies`: aquí solo lo propio de la red.
    protected $allowedFields = [
        'user_id', 'company_id', 'share_profile', 'position', 'branch',
        'bio', 'interests', 'show_email', 'show_phone',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'user_id'   => 'required|is_natural_no_zero',
        'position'  => 'permit_empty|max_length[120]',
        'branch'    => 'permit_empty|max_length[120]',
        'interests' => 'permit_empty|max_length[255]',
    ];

    /** Perfil de un usuario (o null si nunca lo configuró). */
    public function forUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }
}
