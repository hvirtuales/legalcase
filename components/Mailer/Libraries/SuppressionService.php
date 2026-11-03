<?php

namespace Components\Mailer\Libraries;

use Components\Mailer\Models\ContactModel;
use Components\Mailer\Models\SuppressionModel;

/**
 * Lista de supresión por empresa (control de rebotes/quejas/bajas). Ningún envío
 * puede ir a un correo suprimido. Al suprimir se marca también el contacto en las
 * listas para reflejar el estado (bounced/complained/unsubscribed).
 */
class SuppressionService
{
    private SuppressionModel $model;

    public function __construct()
    {
        $this->model = new SuppressionModel();
    }

    public function isSuppressed(int $companyId, string $email): bool
    {
        return $this->model->where('company_id', $companyId)
            ->where('email', strtolower(trim($email)))
            ->countAllResults() > 0;
    }

    /**
     * Agrega un correo a la supresión (idempotente) y marca el contacto.
     * $reason: bounce|complaint|unsubscribe|manual.
     */
    public function suppress(int $companyId, string $email, string $reason = 'manual', ?string $notes = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        if (!$this->isSuppressed($companyId, $email)) {
            $this->model->insert([
                'company_id' => $companyId,
                'email'      => $email,
                'reason'     => in_array($reason, ['bounce', 'complaint', 'unsubscribe', 'manual'], true) ? $reason : 'manual',
                'notes'      => $notes ? mb_substr($notes, 0, 255) : null,
            ]);
        }
        $this->markContacts($companyId, $email, $reason);

        return true;
    }

    /** Quita un correo de la supresión (reactivar). */
    public function unsuppress(int $companyId, string $email): void
    {
        $this->model->where('company_id', $companyId)->where('email', strtolower(trim($email)))->delete();
    }

    /** Estados de supresión → estado del contacto. */
    private function markContacts(int $companyId, string $email, string $reason): void
    {
        $status = match ($reason) {
            'bounce'    => 'bounced',
            'complaint' => 'complained',
            default     => 'unsubscribed',
        };
        (new ContactModel())
            ->where('company_id', $companyId)
            ->where('email', $email)
            ->set(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')])
            ->update();
    }

    /** Conjunto de correos suprimidos de la empresa (para filtrar en bloque). */
    public function emailSet(int $companyId): array
    {
        $rows = $this->model->select('email')->where('company_id', $companyId)->findAll();

        return array_fill_keys(array_map(static fn ($r) => $r['email'], $rows), true);
    }
}
