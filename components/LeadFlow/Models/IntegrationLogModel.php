<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Bitácora de la integración (§31: logs de integración). Una fila por llamada al
 * proveedor, con su resultado. **Nunca guarda tokens ni el contenido de los
 * documentos**: solo un resumen legible de lo que se pidió y lo que respondió.
 */
class IntegrationLogModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_integration_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['company_id', 'provider', 'lead_id', 'action', 'status', 'http_code', 'message',
        'request_summary', 'response_summary', 'created_at'];

    public function log(int $companyId, string $provider, string $action, bool $ok, ?string $message = null,
        ?int $leadId = null, ?int $http = null, ?string $request = null, ?string $response = null): void
    {
        $this->insert([
            'company_id'       => $companyId,
            'provider'         => $provider,
            'lead_id'          => $leadId,
            'action'           => mb_substr($action, 0, 40),
            'status'           => $ok ? 'ok' : 'error',
            'http_code'        => $http,
            'message'          => $message !== null ? mb_substr($message, 0, 255) : null,
            'request_summary'  => $request !== null ? mb_substr($request, 0, 2000) : null,
            'response_summary' => $response !== null ? mb_substr($response, 0, 2000) : null,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }
}
