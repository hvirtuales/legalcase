<?php

namespace Components\Networking\Models;

use CodeIgniter\Model;

class RequestModel extends Model
{
    public const STATUSES = ['pending', 'accepted', 'rejected', 'cancelled'];

    protected $table            = 'cmp_net_requests';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'requester_id', 'addressee_id', 'message', 'status', 'responded_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'requester_id' => 'required|is_natural_no_zero',
        'addressee_id' => 'required|is_natural_no_zero',
        'message'      => 'required|min_length[10]|max_length[1000]',
        'status'       => 'permit_empty|in_list[pending,accepted,rejected,cancelled]',
    ];

    protected $validationMessages = [
        'message' => [
            'required'   => 'Cuéntale a la persona por qué quieres conectar.',
            'min_length' => 'El motivo debe tener al menos 10 caracteres.',
            'max_length' => 'El motivo no puede superar los 1000 caracteres.',
        ],
    ];

    /** Solicitud entre dos usuarios en cualquier dirección (la más reciente). */
    public function between(int $a, int $b): ?array
    {
        return $this->groupStart()
            ->groupStart()->where('requester_id', $a)->where('addressee_id', $b)->groupEnd()
            ->orGroupStart()->where('requester_id', $b)->where('addressee_id', $a)->groupEnd()
            ->groupEnd()
            ->orderBy('id', 'desc')
            ->first();
    }
}
