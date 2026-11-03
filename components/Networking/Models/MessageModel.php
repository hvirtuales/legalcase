<?php

namespace Components\Networking\Models;

use CodeIgniter\Model;

class MessageModel extends Model
{
    protected $table            = 'cmp_net_messages';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['request_id', 'sender_id', 'body', 'read_at', 'created_at'];

    // Los mensajes no se editan: la tabla no tiene `updated_at`, así que se apagan
    // los timestamps automáticos (CI4 exige ambos campos) y `created_at` se fija
    // al insertar en NetworkService::postMessage().
    protected $useTimestamps = false;

    protected $validationRules = [
        'request_id' => 'required|is_natural_no_zero',
        'sender_id'  => 'required|is_natural_no_zero',
        'body'       => 'required|min_length[1]|max_length[2000]',
    ];

    protected $validationMessages = [
        'body' => [
            'required'   => 'Escribe un mensaje.',
            'max_length' => 'El mensaje no puede superar los 2000 caracteres.',
        ],
    ];

    /** Mensajes de una conexión, en orden cronológico. */
    public function thread(int $requestId): array
    {
        return $this->where('request_id', $requestId)->orderBy('id', 'asc')->findAll();
    }
}
