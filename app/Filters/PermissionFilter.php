<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UserModel;

class PermissionFilter implements FilterInterface
{
    /**
     * Check if user has required permission
     *
     * @param RequestInterface $request
     * @param array|null       $arguments - Array of required permissions
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if user is logged in
        if (!session()->get('logged_in')) {
            return redirect()->to('/login')->with('error', lang('Auth.mustBeLoggedIn'));
        }

        $userId = session()->get('user_id');

        if (!$userId) {
            return redirect()->to('/login');
        }

        // If no specific permissions required, just check authentication
        if (empty($arguments)) {
            return;
        }

        $userModel = new UserModel();

        // Check each required permission
        foreach ($arguments as $permission) {
            if (!$userModel->hasPermission($userId, $permission)) {
                // Log unauthorized access attempt
                $auditModel = new \App\Models\AuditLogModel();
                $auditModel->insert([
                    'user_id' => $userId,
                    'event' => 'unauthorized_access',
                    'auditable_type' => 'Permission',
                    'auditable_id' => null,
                    'new_values' => json_encode(['required_permission' => $permission]),
                    'url' => $request->getUri()->getPath(),
                    'ip_address' => $request->getIPAddress(),
                    'user_agent' => $request->getHeaderLine('User-Agent'),
                ]);

                return redirect()->to('/dashboard')->with('error', lang('Auth.insufficientPermissions'));
            }
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
