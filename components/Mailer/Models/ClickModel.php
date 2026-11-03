<?php

namespace Components\Mailer\Models;

use CodeIgniter\Model;

class ClickModel extends Model
{
    protected $table         = 'cmp_mailer_clicks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'campaign_id', 'recipient_id', 'url'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $updatedField  = ''; // solo created_at
}
