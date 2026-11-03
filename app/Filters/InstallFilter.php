<?php

namespace App\Filters;

use App\Libraries\Installer;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Redirige al Asistente de Instalación mientras la plataforma no esté instalada,
 * y bloquea el acceso al asistente una vez instalada.
 */
class InstallFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('url');
        $path = trim(uri_string(), '/');

        // Dejar pasar recursos estáticos siempre
        if (preg_match('#^(css/|js/|vendor/|fonts/|uploads/|assets/|theme\.css|favicon)#', $path)) {
            return;
        }

        $installed = (new Installer())->isInstalled();
        $isInstall = $path === 'install' || strpos($path, 'install/') === 0;

        if (!$installed && !$isInstall) {
            return redirect()->to('/install');
        }
        if ($installed && $isInstall) {
            return redirect()->to('/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
