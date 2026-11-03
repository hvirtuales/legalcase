<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'user_agent',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = ''; // Disable updated_at

    /**
     * Get logs by user with filters
     */
    public function getByUser(int $userId, array $filters = []): array
    {
        $builder = $this->where('user_id', $userId);

        return $this->applyFilters($builder, $filters)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get logs by entity
     */
    public function getByEntity(string $type, int $id): array
    {
        return $this->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get logs by date range
     */
    public function getByDateRange(string $startDate, string $endDate, array $filters = []): array
    {
        $builder = $this->where('created_at >=', $startDate)
            ->where('created_at <=', $endDate);

        return $this->applyFilters($builder, $filters)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Apply filters to query builder
     */
    private function applyFilters($builder, array $filters)
    {
        if (!empty($filters['event'])) {
            $builder->where('event', $filters['event']);
        }

        if (!empty($filters['auditable_type'])) {
            $builder->where('auditable_type', $filters['auditable_type']);
        }

        if (!empty($filters['user_id'])) {
            $builder->where('user_id', $filters['user_id']);
        }

        return $builder;
    }

    /**
     * Get statistics of events
     */
    public function getStatistics(array $filters = []): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table($this->table);

        if (!empty($filters['start_date'])) {
            $builder->where('created_at >=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('created_at <=', $filters['end_date']);
        }

        // Events by type
        $eventStats = $builder->select('event, COUNT(*) as count')
            ->groupBy('event')
            ->get()
            ->getResultArray();

        // Events by user (top 10)
        $userStats = $builder->select('user_id, users.username, COUNT(*) as count')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->groupBy('user_id')
            ->orderBy('count', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        // Events by entity type
        $entityStats = $builder->select('auditable_type, COUNT(*) as count')
            ->groupBy('auditable_type')
            ->get()
            ->getResultArray();

        return [
            'by_event' => $eventStats,
            'by_user' => $userStats,
            'by_entity' => $entityStats,
        ];
    }

    /**
     * Export logs to CSV
     */
    public function exportToCsv(array $filters = []): string
    {
        $logs = $this->applyFilters($this->builder(), $filters)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $csv = "ID,Usuario,Evento,Tipo,ID Entidad,URL,IP,Fecha\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s\n",
                $log['id'],
                $log['user_id'] ?? 'N/A',
                $log['event'],
                $log['auditable_type'],
                $log['auditable_id'] ?? 'N/A',
                $log['url'] ?? '',
                $log['ip_address'] ?? '',
                $log['created_at']
            );
        }

        return $csv;
    }

    /**
     * Get audit log with user information
     */
    public function getWithUser(int $id): ?array
    {
        $db = \Config\Database::connect();

        return $db->table($this->table)
            ->select('audit_logs.*, users.username, users.email')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->where('audit_logs.id', $id)
            ->get()
            ->getRowArray();
    }

    /**
     * Get paginated logs with filters
     */
    public function getPaginated(int $perPage = 20, array $filters = []): array
    {
        $builder = $this->applyFilters($this->builder(), $filters);

        $db = \Config\Database::connect();
        $builderWithUser = $db->table($this->table)
            ->select('audit_logs.*, users.username')
            ->join('users', 'users.id = audit_logs.user_id', 'left');

        // Apply same filters
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $builderWithUser->where("audit_logs.$key", $value);
            }
        }

        return [
            'data' => $builderWithUser->orderBy('audit_logs.created_at', 'DESC')
                ->limit($perPage)
                ->get()
                ->getResultArray(),
            'total' => $builder->countAllResults(false),
        ];
    }
}
