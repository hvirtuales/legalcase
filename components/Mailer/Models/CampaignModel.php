<?php

namespace Components\Mailer\Models;

use CodeIgniter\Model;

class CampaignModel extends Model
{
    protected $table          = 'cmp_mailer_campaigns';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'company_id', 'list_id', 'template_id', 'name', 'subject', 'from_name',
        'from_email', 'reply_to', 'body_html', 'design_json', 'status', 'total',
        'sent_count', 'failed_count', 'bounced_count', 'started_at', 'finished_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'list_id'    => 'required|is_natural_no_zero',
        'name'       => 'required|min_length[2]|max_length[150]',
        'subject'    => 'required|max_length[255]',
    ];
    protected $validationMessages = [
        'name'    => ['required' => 'Mailer.vrCampaign'],
        'subject' => ['required' => 'Mailer.vrSubject'],
        'list_id' => ['required' => 'Mailer.vrPickList'],
    ];
}
