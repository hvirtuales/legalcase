<?php

namespace App\Controllers;

use App\Libraries\Installer;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Asistente de Instalación y Configuración Inicial (wizard paso a paso).
 * No depende de la BD ni de la sesión de auth: funciona en un servidor recién
 * clonado. El acceso lo gobierna InstallFilter (según writable/installed.flag).
 */
class InstallController extends Controller
{
    protected $helpers = ['form', 'url'];
    private Installer $installer;

    /** Pasos del asistente en orden. */
    private array $steps = [
        'requirements' => 'Requisitos',
        'database'     => 'Base de datos',
        'migrate'      => 'Migración y datos',
        'admin'        => 'Administrador',
        'settings'     => 'Configuración',
        'finish'       => 'Finalizar',
    ];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->installer = new Installer();
    }

    public function index()
    {
        return redirect()->to('/install/requirements');
    }

    // ------------------------------------------------------------------ paso 1
    public function requirements()
    {
        if ($this->request->getMethod() === 'POST') {
            if (!$this->installer->requirementsMet()) {
                return redirect()->back()->with('error', 'Aún faltan requisitos obligatorios.');
            }
            session()->set('install_ok_requirements', true);

            return redirect()->to('/install/database');
        }

        return $this->view('requirements', [
            'requirements' => $this->installer->requirements(),
            'met'          => $this->installer->requirementsMet(),
        ]);
    }

    // ------------------------------------------------------------------ paso 2
    public function database()
    {
        if ($this->request->getMethod() === 'POST') {
            $driver = $this->request->getPost('driver') ?: 'MySQLi';
            $cfg    = [
                'hostname' => trim((string) $this->request->getPost('hostname')),
                'port'     => trim((string) $this->request->getPost('port')),
                'database' => trim((string) $this->request->getPost('database')),
                'username' => trim((string) $this->request->getPost('username')),
                'password' => (string) $this->request->getPost('password'),
            ];
            $create = (bool) $this->request->getPost('create_db');

            $test = $this->installer->testConnection($driver, $cfg, $create);
            if (!$test['ok']) {
                return redirect()->back()->withInput()->with('error', $test['message']);
            }

            if (!$this->installer->writeDatabaseConfig($driver, $cfg) || !$this->installer->ensureEncryptionKey()) {
                return redirect()->back()->withInput()->with('error', 'No se pudo escribir el archivo .env (¿es escribible?).');
            }

            session()->set('install_db', ['driver' => $driver] + $cfg);

            return redirect()->to('/install/migrate')->with('success', $test['message'] . ' Configuración guardada.');
        }

        return $this->view('database', [
            'drivers' => $this->installer->drivers(),
            'db'      => session('install_db') ?? [],
        ]);
    }

    // ------------------------------------------------------------------ paso 3
    public function migrate()
    {
        if ($this->request->getMethod() === 'POST') {
            $mig = $this->installer->runMigrations();
            if (!$mig['ok']) {
                return redirect()->back()->with('error', 'Migraciones: ' . $mig['message']);
            }
            $seed = $this->installer->runSeeders();
            if (!$seed['ok']) {
                return redirect()->back()->with('error', 'Datos base: ' . $seed['message']);
            }
            session()->set('install_ok_migrate', true);

            return redirect()->to('/install/admin')->with('success', 'Esquema creado y datos base cargados.');
        }

        // Verifica que la BD conecte con la configuración recién escrita
        $dbOk = false;
        $dbErr = '';
        try {
            \Config\Database::connect()->query('SELECT 1');
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbErr = $e->getMessage();
        }

        return $this->view('migrate', ['dbOk' => $dbOk, 'dbErr' => $dbErr]);
    }

    // ------------------------------------------------------------------ paso 4
    public function admin()
    {
        if (!$this->tablesReady()) {
            return redirect()->to('/install/migrate')->with('error', 'Primero ejecuta las migraciones.');
        }

        if ($this->request->getMethod() === 'POST') {
            $rules = [
                'first_name'       => 'required|max_length[50]',
                'last_name'        => 'required|max_length[50]',
                'username'         => 'required|min_length[3]|max_length[50]',
                'email'            => 'required|valid_email',
                'password'         => 'required|min_length[8]',
                'password_confirm' => 'required|matches[password]',
            ];
            if (!$this->validate($rules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            $res = $this->installer->createAdmin([
                'first_name' => $this->request->getPost('first_name'),
                'last_name'  => $this->request->getPost('last_name'),
                'username'   => $this->request->getPost('username'),
                'email'      => $this->request->getPost('email'),
                'password'   => $this->request->getPost('password'),
            ]);
            if (!$res['ok']) {
                return redirect()->back()->withInput()->with('error', $res['message']);
            }
            session()->set('install_ok_admin', true);

            return redirect()->to('/install/settings')->with('success', 'Administrador creado.');
        }

        return $this->view('admin', []);
    }

    // ------------------------------------------------------------------ paso 5
    public function settings()
    {
        if (!$this->tablesReady()) {
            return redirect()->to('/install/migrate');
        }

        if ($this->request->getMethod() === 'POST') {
            $this->installer->saveSettings([
                'app_name'            => trim((string) $this->request->getPost('app_name')),
                'app_description'     => trim((string) $this->request->getPost('app_description')),
                'support_email'       => trim((string) $this->request->getPost('support_email')),
                'public_registration' => $this->request->getPost('public_registration'),
            ]);

            return redirect()->to('/install/finish')->with('success', 'Parámetros guardados.');
        }

        return $this->view('settings', []);
    }

    // ------------------------------------------------------------------ paso 6
    public function finish()
    {
        if ($this->request->getMethod() === 'POST') {
            $this->installer->ensureEncryptionKey();
            $this->installer->lock(['db' => session('install_db')['driver'] ?? null]);
            session()->remove(['install_db', 'install_ok_requirements', 'install_ok_migrate', 'install_ok_admin']);

            return redirect()->to('/login')->with('success', '¡Plataforma instalada! Inicia sesión con tu cuenta de administrador.');
        }

        return $this->view('finish', ['health' => $this->installer->healthCheck()]);
    }

    // ------------------------------------------------------------------ helpers
    private function tablesReady(): bool
    {
        try {
            return \Config\Database::connect()->tableExists('users');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function view(string $step, array $data)
    {
        return view('install/' . $step, array_merge($data, [
            'steps'   => $this->steps,
            'current' => $step,
        ]));
    }
}
