<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/** Historial del score, con el desglose por criterio de cada cálculo. */
class ScoreHistoryModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_score_history';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['company_id', 'lead_id', 'score', 'previous', 'breakdown', 'reason', 'created_at'];
}
