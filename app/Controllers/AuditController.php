<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;

class AuditController extends BaseController
{
    protected $auditModel;
    protected $userModel;

    public function __construct()
    {
        $this->auditModel = new AuditLogModel();
        $this->userModel = new UserModel();
    }

    /**
     * List audit logs
     */
    public function index()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'audit.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        // Get filters from request
        $filters = [
            'event' => $this->request->getGet('event'),
            'auditable_type' => $this->request->getGet('auditable_type'),
            'user_id' => $this->request->getGet('user_id'),
            'start_date' => $this->request->getGet('start_date'),
            'end_date' => $this->request->getGet('end_date'),
        ];

        // Remove empty filters
        $filters = array_filter($filters);

        $perPage = 20;
        $result = $this->auditModel->getPaginated($perPage, $filters);

        $data = [
            'logs' => $result['data'],
            'total' => $result['total'],
            'filters' => $filters,
            'title' => lang('App.auditLogs'),
        ];

        return view('audit/index', $data);
    }

    /**
     * View audit log detail
     */
    public function view($id)
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'audit.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        $log = $this->auditModel->getWithUser($id);

        if (!$log) {
            return redirect()->to('/audit')->with('error', lang('App.notFound'));
        }

        // Decode JSON fields
        if (!empty($log['old_values'])) {
            $log['old_values'] = json_decode($log['old_values'], true);
        }
        if (!empty($log['new_values'])) {
            $log['new_values'] = json_decode($log['new_values'], true);
        }

        $data = [
            'log' => $log,
            'title' => lang('App.auditLogDetail'),
        ];

        return view('audit/view', $data);
    }

    /**
     * Show statistics
     */
    public function statistics()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'audit.view')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        // Get date range from request
        $filters = [
            'start_date' => $this->request->getGet('start_date') ?? date('Y-m-d', strtotime('-30 days')),
            'end_date' => $this->request->getGet('end_date') ?? date('Y-m-d'),
        ];

        $stats = $this->auditModel->getStatistics($filters);

        $data = [
            'stats' => $stats,
            'filters' => $filters,
            'title' => lang('App.auditStatistics'),
        ];

        return view('audit/statistics', $data);
    }

    /**
     * Export audit logs to CSV
     */
    public function export()
    {
        // Check permission
        if (!$this->userModel->hasPermission(session()->get('user_id'), 'audit.export')) {
            return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
        }

        // Get filters from request
        $filters = [
            'event' => $this->request->getGet('event'),
            'auditable_type' => $this->request->getGet('auditable_type'),
            'user_id' => $this->request->getGet('user_id'),
            'start_date' => $this->request->getGet('start_date'),
            'end_date' => $this->request->getGet('end_date'),
        ];

        // Remove empty filters
        $filters = array_filter($filters);

        $csv = $this->auditModel->exportToCsv($filters);

        // Log export action
        $this->auditModel->insert([
            'user_id' => session()->get('user_id'),
            'event' => 'audit_exported',
            'auditable_type' => 'App\\Models\\AuditLogModel',
            'auditable_id' => null,
            'new_values' => json_encode($filters),
            'url' => $this->request->getUri()->getPath(),
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => $this->request->getUserAgent()->getAgentString(),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d_His') . '.csv"')
            ->setBody($csv);
    }
}
