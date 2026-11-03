<?php

namespace Components\LegalCase\Models;

/** Cola de salida de avisos (outbox) con clave de idempotencia única. */
class DeliveryModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_deliveries';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'account_id', 'channel', 'kind', 'ref_type', 'ref_id', 'idem_key', 'recipient', 'status', 'attempts', 'last_error', 'sent_at'];

    public const STATUSES = ['pending', 'sent', 'failed', 'skipped'];
    public const MAX_ATTEMPTS = 5;
}
