<?php

namespace Components\Mailer\Models;

use CodeIgniter\Model;

class RecipientModel extends Model
{
    protected $table         = 'cmp_mailer_recipients';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'campaign_id', 'contact_id', 'email', 'name', 'entity',
        'status', 'error', 'sent_at',
        'opened_at', 'open_count', 'clicked_at', 'click_count', 'track_token',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $updatedField  = ''; // solo created_at
}
