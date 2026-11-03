<?php

namespace App\Filters;

use App\Libraries\JwtService;
use App\Models\ApiTokenModel;
use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autentica peticiones de la API por JWT (Authorization: Bearer <jwt>).
 * Valida firma/expiración, comprueba que el token no esté revocado (jti en
 * api_tokens) y resuelve los permisos de los roles del token en el servicio
 * compartido `apiAuth`.
 */
class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return $this->deny('Falta el token Bearer.', 401);
        }
        $jwt = trim($m[1]);

        $payload = (new JwtService())->decode($jwt);
        if ($payload === null) {
            return $this->deny('Token inválido o expirado.', 401);
        }

        // Revocación: el registro debe seguir existiendo en api_tokens
        $tokenModel = new ApiTokenModel();
        $record     = $tokenModel->find((int) ($payload['jti'] ?? 0));
        if (!$record) {
            return $this->deny('Token revocado.', 401);
        }
        // Expiración persistida
        if (!empty($record['expires_at']) && strtotime($record['expires_at']) < time()) {
            return $this->deny('Token expirado.', 401);
        }
        $tokenModel->updateLastUsed((int) $record['id']);

        $userId  = (int) ($payload['sub'] ?? 0);
        $roleIds = array_map('intval', $payload['roles'] ?? []);

        // Permisos efectivos = unión de permisos de los roles del token
        $perms = [];
        if ($roleIds) {
            $rows = \Config\Database::connect()->table('role_permissions')
                ->select('permissions.name')
                ->join('permissions', 'permissions.id = role_permissions.permission_id')
                ->whereIn('role_permissions.role_id', $roleIds)
                ->distinct()
                ->get()->getResultArray();
            $perms = array_column($rows, 'name');
        }

        $user = (new UserModel())->find($userId) ?? [];

        // Alcance por empresa: el token puede estar ligado a una empresa.
        $companyId = isset($payload['company']) ? (int) $payload['company'] : null;
        $company   = [];
        if ($companyId) {
            $company = (new \App\Models\CompanyModel())->find($companyId) ?? [];
            if (!$company) {
                return $this->deny('La empresa del token ya no existe.', 403);
            }
            // El plan de la empresa impone límite real también a la API: vigencia
            $subModel = new \App\Models\SubscriptionModel();
            $sub      = $subModel->markExpiredIfNeeded($subModel->getActiveForCompany($companyId));
            if (!$subModel->isValid($sub)) {
                return $this->deny('La suscripción de la empresa (' . ($company['name'] ?? $companyId) . ') está vencida.', 403);
            }
        }

        service('apiAuth')->setContext($userId, $roleIds, $perms, $user, $companyId, $company);

        return null; // continúa
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function deny(string $message, int $status)
    {
        return service('response')
            ->setStatusCode($status)
            ->setJSON(['error' => true, 'message' => $message]);
    }
}
