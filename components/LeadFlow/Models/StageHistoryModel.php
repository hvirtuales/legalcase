<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Cada cambio de etapa, con cuánto tiempo pasó el lead en la anterior.
 *
 * Sin `updated_at` en la tabla: `useTimestamps = false` y `created_at` a mano
 * (CI4 exige los dos campos para las marcas automáticas).
 */
class StageHistoryModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_stage_history';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['company_id', 'lead_id', 'from_stage_id', 'to_stage_id', 'user_id', 'seconds_in_previous', 'created_at'];
}
