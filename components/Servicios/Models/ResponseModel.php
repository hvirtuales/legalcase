<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

/** Histórico de lo que hace el cliente con el enlace público. */
class ResponseModel extends Model
{
    public const ACTIONS = ['viewed', 'approved', 'rejected', 'changes'];

    protected $table            = 'cmp_quo_responses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['quote_id', 'action', 'message', 'ip_address', 'created_at'];

    protected $useTimestamps = false; // created_at se fija al insertar

    protected $validationRules = [
        'quote_id' => 'required|is_natural_no_zero',
        'action'   => 'required|in_list[viewed,approved,rejected,changes]',
        'message'  => 'permit_empty|max_length[2000]',
    ];

    /** Respuestas de una cotización, la más reciente primero. */
    public function forQuote(int $quoteId): array
    {
        return $this->where('quote_id', $quoteId)->orderBy('id', 'desc')->findAll();
    }
}
