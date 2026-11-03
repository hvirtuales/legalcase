<?php

namespace App\Controllers\Api;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Base de los controladores REST. Respuestas JSON (ResponseTrait) y helpers de
 * autorización basados en el contexto del token (servicio apiAuth, poblado por
 * ApiAuthFilter).
 */
abstract class BaseApiController extends Controller
{
    use ResponseTrait;

    protected $helpers = [];

    /** @var \App\Libraries\ApiAuth */
    protected $apiAuth;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->apiAuth = service('apiAuth');
    }

    protected function can(string $permission): bool
    {
        return $this->apiAuth->can($permission);
    }

    /** Empresa a la que está acotado el token (null si es global). */
    protected function companyId(): ?int
    {
        return $this->apiAuth->companyId();
    }

    /** Devuelve una respuesta 403 si falta el permiso; null si está permitido. */
    protected function guard(string $permission)
    {
        if (!$this->can($permission)) {
            return $this->failForbidden('Permiso requerido: ' . $permission);
        }

        return null;
    }

    /** Body JSON (o form) de la petición como array. No revienta con JSON inválido. */
    protected function body(): array
    {
        try {
            $json = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            $json = null;
        }
        if (is_array($json) && $json !== []) {
            return $json;
        }

        return (array) $this->request->getPost();
    }
}
