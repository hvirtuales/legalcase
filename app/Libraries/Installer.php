<?php

namespace App\Libraries;

use App\Models\UserModel;
use Config\Database as DatabaseConfig;

/**
 * Lógica del Asistente de Instalación. Diseñada para funcionar ANTES de que la
 * plataforma esté instalada (sin depender de la BD ni de la sesión de auth).
 */
class Installer
{
    /** Archivo que marca la plataforma como instalada. */
    private function lockPath(): string
    {
        return WRITEPATH . 'installed.flag';
    }

    private function envPath(): string
    {
        return ROOTPATH . '.env';
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockPath());
    }

    public function lock(array $info = []): bool
    {
        return file_put_contents($this->lockPath(), json_encode(['installed_at' => date('c')] + $info, JSON_PRETTY_PRINT)) !== false;
    }

    public function unlock(): void
    {
        @unlink($this->lockPath());
    }

    // ------------------------------------------------------------------ requisitos

    /** @return array<int, array{label:string, ok:bool, required:bool, detail:string}> */
    public function requirements(): array
    {
        $envOk = is_file($this->envPath()) ? is_writable($this->envPath()) : is_writable(ROOTPATH);

        return [
            ['label' => 'PHP >= 8.1', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'required' => true, 'detail' => 'Versión: ' . PHP_VERSION],
            ['label' => 'Extensión intl', 'ok' => extension_loaded('intl'), 'required' => true, 'detail' => 'Internacionalización'],
            ['label' => 'Extensión mbstring', 'ok' => extension_loaded('mbstring'), 'required' => true, 'detail' => 'Cadenas multibyte'],
            ['label' => 'Extensión json', 'ok' => extension_loaded('json'), 'required' => true, 'detail' => 'Datos JSON'],
            ['label' => 'Extensión openssl', 'ok' => extension_loaded('openssl'), 'required' => true, 'detail' => 'Cifrado / JWT'],
            ['label' => 'Driver MySQLi', 'ok' => extension_loaded('mysqli'), 'required' => false, 'detail' => 'Para MySQL / MariaDB'],
            ['label' => 'Driver PostgreSQL', 'ok' => extension_loaded('pgsql'), 'required' => false, 'detail' => 'Para PostgreSQL'],
            ['label' => 'Driver SQL Server', 'ok' => extension_loaded('sqlsrv'), 'required' => false, 'detail' => 'Para SQL Server'],
            ['label' => 'Extensión curl', 'ok' => extension_loaded('curl'), 'required' => false, 'detail' => 'Integraciones externas'],
            ['label' => 'Extensión gd', 'ok' => extension_loaded('gd'), 'required' => false, 'detail' => 'Redimensionar imágenes'],
            ['label' => 'writable/ escribible', 'ok' => is_writable(WRITEPATH), 'required' => true, 'detail' => WRITEPATH],
            ['label' => '.env escribible', 'ok' => $envOk, 'required' => true, 'detail' => $this->envPath()],
        ];
    }

    /** ¿Se cumplen todos los requisitos obligatorios? */
    public function requirementsMet(): bool
    {
        foreach ($this->requirements() as $r) {
            if ($r['required'] && !$r['ok']) {
                return false;
            }
        }

        return true;
    }

    /** Motores de base de datos ofrecidos. */
    public function drivers(): array
    {
        return [
            'MySQLi'  => ['label' => 'MySQL / MariaDB', 'ext' => 'mysqli', 'file' => false, 'recommended' => true],
            'Postgre' => ['label' => 'PostgreSQL', 'ext' => 'pgsql', 'file' => false, 'recommended' => false],
            'SQLSRV'  => ['label' => 'SQL Server', 'ext' => 'sqlsrv', 'file' => false, 'recommended' => false],
        ];
    }

    // ------------------------------------------------------------------ base de datos

    /**
     * Prueba la conexión según el motor. Puede crear la base si no existe.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(string $driver, array $c, bool $create = false): array
    {
        try {
            if ($driver === 'SQLSRV') {
                if (!extension_loaded('sqlsrv')) {
                    return ['ok' => false, 'message' => 'La extensión sqlsrv no está instalada en este servidor.'];
                }
                $server = $c['hostname'] . (!empty($c['port']) ? ',' . $c['port'] : '');
                $conn   = @sqlsrv_connect($server, ['Database' => $c['database'], 'UID' => $c['username'], 'PWD' => $c['password']]);
                if ($conn === false) {
                    return ['ok' => false, 'message' => 'No se pudo conectar a SQL Server (revisa credenciales y que la base exista).'];
                }
                sqlsrv_close($conn);

                return ['ok' => true, 'message' => 'Conexión a SQL Server correcta.'];
            }

            if ($driver === 'Postgre') {
                if (!extension_loaded('pgsql')) {
                    return ['ok' => false, 'message' => 'La extensión pgsql no está instalada.'];
                }
                $conn = @pg_connect(sprintf('host=%s port=%s user=%s password=%s dbname=%s', $c['hostname'], $c['port'] ?: 5432, $c['username'], $c['password'], $c['database']));
                if (!$conn) {
                    return ['ok' => false, 'message' => 'No se pudo conectar a PostgreSQL (revisa credenciales y que la base exista).'];
                }
                pg_close($conn);

                return ['ok' => true, 'message' => 'Conexión a PostgreSQL correcta.'];
            }

            // MySQLi (MySQL / MariaDB)
            if (!extension_loaded('mysqli')) {
                return ['ok' => false, 'message' => 'La extensión mysqli no está instalada.'];
            }
            mysqli_report(MYSQLI_REPORT_OFF);
            $m = @new \mysqli($c['hostname'], $c['username'], $c['password'], '', (int) ($c['port'] ?: 3306));
            if ($m->connect_errno) {
                return ['ok' => false, 'message' => 'Conexión fallida: ' . $m->connect_error];
            }
            $name   = $c['database'] ?? '';
            $safe   = $m->real_escape_string($name);
            $exists = $m->query("SHOW DATABASES LIKE '{$safe}'")->num_rows > 0;
            if (!$exists) {
                if (!$create) {
                    return ['ok' => false, 'message' => "La base '{$name}' no existe. Marca «crear si no existe» o créala manualmente."];
                }
                if (!$m->query('CREATE DATABASE `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')) {
                    return ['ok' => false, 'message' => 'No se pudo crear la base: ' . $m->error];
                }
            }
            $m->close();

            return ['ok' => true, 'message' => $exists ? 'Conexión correcta y base existente.' : 'Conexión correcta, base creada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Escribe la configuración de BD (+ driver) y una encryption.key si falta. */
    public function writeDatabaseConfig(string $driver, array $c): bool
    {
        $pairs = [
            'database.default.DBDriver' => $driver,
            'database.default.hostname' => $c['hostname'] ?? 'localhost',
            'database.default.database' => $c['database'] ?? '',
            'database.default.username' => $c['username'] ?? '',
            'database.default.password' => $c['password'] ?? '',
            'database.default.port'     => $c['port'] ?: ($driver === 'Postgre' ? '5432' : ($driver === 'SQLSRV' ? '1433' : '3306')),
        ];

        return $this->setEnv($pairs);
    }

    /** Genera encryption.key si está vacía (necesaria para cifrado de secretos y JWT). */
    public function ensureEncryptionKey(): bool
    {
        if (env('encryption.key')) {
            return true;
        }

        return $this->setEnv(['encryption.key' => 'hex2bin:' . bin2hex(random_bytes(32))]);
    }

    /** Fija CI_ENVIRONMENT. */
    public function setEnvironment(string $env): bool
    {
        return $this->setEnv(['CI_ENVIRONMENT' => $env]);
    }

    // ------------------------------------------------------------------ migrar / seed

    /** @return array{ok:bool, message:string} */
    public function runMigrations(): array
    {
        try {
            \Config\Services::migrations()->latest();

            return ['ok' => true, 'message' => 'Migraciones aplicadas.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{ok:bool, message:string} */
    public function runSeeders(): array
    {
        try {
            \Config\Database::seeder()->call('DatabaseSeeder');

            return ['ok' => true, 'message' => 'Datos base cargados.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ admin / settings

    /** Crea (o actualiza) el super administrador. @return array{ok:bool, message:string} */
    public function createAdmin(array $data): array
    {
        try {
            $userModel = new UserModel();
            $db        = \Config\Database::connect();

            $existing = $userModel->where('username', $data['username'])
                ->orWhere('email', $data['email'])->first();

            $payload = [
                'username'    => $data['username'],
                'email'       => $data['email'],
                'password'    => $data['password'],
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
                'is_active'   => 1,
                'auth_method' => 'password',
            ];

            $userModel->skipValidation(true);
            if ($existing) {
                $userModel->update($existing['id'], $payload);
                $userId = (int) $existing['id'];
            } else {
                $userId = (int) $userModel->insert($payload);
            }
            $userModel->skipValidation(false);

            // Rol Super Admin
            $role = $db->table('roles')->getWhere(['name' => 'Super Admin'])->getRow();
            if ($role) {
                $ur = $db->table('user_roles');
                if (!$ur->getWhere(['user_id' => $userId, 'role_id' => $role->id])->getRow()) {
                    $ur->insert(['user_id' => $userId, 'role_id' => $role->id]);
                }
            }

            return ['ok' => true, 'message' => 'Administrador creado.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Guarda parámetros iniciales del sistema. */
    public function saveSettings(array $data): array
    {
        try {
            $model = new \App\Models\AppSettingModel();
            $model->setSetting('app_name', $data['app_name'] ?? 'Plataforma');
            $model->setSetting('app_description', $data['app_description'] ?? '');
            $model->setSetting('public_registration_enabled', !empty($data['public_registration']) ? '1' : '0', 'boolean');
            if (!empty($data['support_email'])) {
                $model->setSetting('support_email', $data['support_email']);
            }

            return ['ok' => true, 'message' => 'Parámetros guardados.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Chequeo final: BD conecta y hay admin. @return array{ok:bool, checks:array} */
    public function healthCheck(): array
    {
        $checks = [];
        try {
            $db = \Config\Database::connect();
            $db->query('SELECT 1');
            $checks[] = ['label' => 'Conexión a base de datos', 'ok' => true];

            $tablesOk = $db->tableExists('users') && $db->tableExists('roles') && $db->tableExists('permissions');
            $checks[] = ['label' => 'Esquema (tablas creadas)', 'ok' => $tablesOk];

            $admins = $db->table('users')->join('user_roles', 'user_roles.user_id = users.id')
                ->join('roles', 'roles.id = user_roles.role_id')
                ->where('roles.name', 'Super Admin')->countAllResults();
            $checks[] = ['label' => 'Administrador Super Admin', 'ok' => $admins > 0];

            $checks[] = ['label' => 'Clave de cifrado configurada', 'ok' => (bool) env('encryption.key')];
        } catch (\Throwable $e) {
            $checks[] = ['label' => 'Conexión a base de datos', 'ok' => false, 'detail' => $e->getMessage()];
        }

        $ok = true;
        foreach ($checks as $c) {
            $ok = $ok && ($c['ok'] ?? false);
        }

        return ['ok' => $ok, 'checks' => $checks];
    }

    // ------------------------------------------------------------------ helpers

    /** Actualiza (o agrega) líneas key = value en el .env. */
    private function setEnv(array $pairs): bool
    {
        $path    = $this->envPath();
        $content = is_file($path) ? file_get_contents($path) : "";

        foreach ($pairs as $key => $value) {
            // Entrecomilla valores con espacios o caracteres especiales
            if ($value !== '' && preg_match('/[\s#"\'=]/', (string) $value)) {
                $value = '"' . str_replace('"', '\"', (string) $value) . '"';
            }
            $line    = $key . ' = ' . $value;
            $pattern = '/^[ \t]*#?[ \t]*' . preg_quote($key, '/') . '[ \t]*=.*$/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $line, $content, 1);
            } else {
                $content = rtrim($content, "\n") . "\n" . $line . "\n";
            }
        }

        return file_put_contents($path, $content) !== false;
    }
}
