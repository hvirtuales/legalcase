<?php

namespace Components\Mailer\Models;

use CodeIgniter\Model;

class ContactModel extends Model
{
    protected $table          = 'cmp_mailer_contacts';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'company_id', 'list_id', 'email', 'name', 'entity', 'status', 'meta',
        'unsubscribe_token', 'subscribed_at',
        // De dónde salió la dirección: es la prueba del consentimiento que
        // pide la política de SES, y sin estos campos en la lista blanca el
        // modelo los descarta en silencio al insertar.
        'consent_source', 'consent_at', 'consent_ip', 'consent_note',
        'soft_bounces', 'confirm_token', 'confirmed_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'list_id'    => 'required|is_natural_no_zero',
        'email'      => 'required|valid_email|max_length[190]',
    ];
    protected $validationMessages = [
        'email' => ['valid_email' => 'Mailer.vrEmailValid', 'required' => 'Mailer.vrEmail'],
    ];

    /** Estados que aún reciben envíos. */
    public const SENDABLE = 'subscribed';
}
