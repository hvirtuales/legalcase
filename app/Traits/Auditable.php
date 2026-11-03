<?php

namespace App\Traits;

use App\Models\AuditLogModel;

trait Auditable
{
    /**
     * Hook: beforeInsert
     * Establece created_by con el usuario actual
     */
    protected function setCreatedBy(array $data): array
    {
        if (isset($this->allowedFields) && in_array('created_by', $this->allowedFields)) {
            $userId = $this->getCurrentUserId();
            // Solo establecer created_by si hay un usuario autenticado
            // Esto permite el registro público sin usuario autenticado
            if ($userId !== null) {
                $data['data']['created_by'] = $userId;
            }
        }
        return $data;
    }

    /**
     * Hook: beforeUpdate
     * Establece updated_by con el usuario actual
     */
    protected function setUpdatedBy(array $data): array
    {
        if (isset($this->allowedFields) && in_array('updated_by', $this->allowedFields)) {
            $userId = $this->getCurrentUserId();
            // Solo establecer updated_by si hay un usuario autenticado
            if ($userId !== null) {
                $data['data']['updated_by'] = $userId;
            }
        }
        return $data;
    }

    /**
     * Hook: beforeDelete
     * Establece deleted_by con el usuario actual (para soft deletes)
     */
    protected function setDeletedBy(array $data): array
    {
        if (isset($this->allowedFields) && in_array('deleted_by', $this->allowedFields) && $this->useSoftDeletes) {
            $userId = $this->getCurrentUserId();
            // Solo establecer deleted_by si hay un usuario autenticado
            if ($userId !== null) {
                $data['data']['deleted_by'] = $userId;
            }
        }
        return $data;
    }

    /**
     * Hook: afterInsert
     * Registra evento 'created' en audit_logs
     */
    protected function auditInsert(array $data)
    {
        $this->logAuditEvent('created', $data['id'], null, $data['data']);
        return $data;
    }

    /**
     * Hook: afterUpdate
     * Registra evento 'updated' en audit_logs con valores antiguos y nuevos
     */
    protected function auditUpdate(array $data)
    {
        if (!empty($data['id'])) {
            $ids = is_array($data['id']) ? $data['id'] : [$data['id']];

            foreach ($ids as $id) {
                // Obtener valores antiguos
                $oldValues = $this->find($id);
                $this->logAuditEvent('updated', $id, $oldValues, $data['data']);
            }
        }
        return $data;
    }

    /**
     * Hook: afterDelete
     * Registra evento 'deleted' en audit_logs
     */
    protected function auditDelete(array $data)
    {
        if (!empty($data['id'])) {
            $ids = is_array($data['id']) ? $data['id'] : [$data['id']];

            foreach ($ids as $id) {
                $oldValues = $data['data'] ?? null;
                $this->logAuditEvent('deleted', $id, $oldValues, null);
            }
        }
        return $data;
    }

    /**
     * Obtiene el ID del usuario autenticado actual
     */
    protected function getCurrentUserId(): ?int
    {
        $session = session();
        return $session->get('user_id') ?? null;
    }

    /**
     * Registra un evento en la tabla audit_logs
     */
    protected function logAuditEvent(string $event, $auditableId, ?array $oldValues, ?array $newValues): void
    {
        try {
            $auditModel = new AuditLogModel();

            // Filtrar atributos sensibles
            $oldValues = $this->filterAuditableAttributes($oldValues);
            $newValues = $this->filterAuditableAttributes($newValues);

            $request = service('request');

            $auditModel->insert([
                'user_id' => $this->getCurrentUserId(),
                'event' => $event,
                'auditable_type' => get_class($this),
                'auditable_id' => $auditableId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'url' => $request->getUri()->getPath(),
                'ip_address' => $request->getIPAddress(),
                'user_agent' => $request->getUserAgent()->getAgentString(),
            ]);
        } catch (\Exception $e) {
            // Log error pero no detener la operación
            log_message('error', 'Audit logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Filtra atributos sensibles que no deben ser auditados
     */
    protected function filterAuditableAttributes(?array $data): ?array
    {
        if (!$data) {
            return null;
        }

        $excludedFields = [
            'password',
            'password_hash',
            'token',
            'secret',
            'recovery_codes',
            'remember_token',
        ];

        return array_diff_key($data, array_flip($excludedFields));
    }
}
