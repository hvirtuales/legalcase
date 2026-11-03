<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\TwoFactorAuthModel;
use App\Models\AuditLogModel;
use App\Models\AppSettingModel;

class AuthController extends BaseController
{
    protected $userModel;
    protected $twoFactorModel;
    protected $auditModel;
    protected $settingModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->twoFactorModel = new TwoFactorAuthModel();
        $this->auditModel = new AuditLogModel();
        $this->settingModel = new AppSettingModel();
    }

    /**
     * Show login form
     */
    public function login()
    {
        // Nunca se vuelca el POST: traía la contraseña en claro al fichero de log.
        log_message('debug', 'Login: ' . $this->request->getMethod());

        // If already logged in, redirect to dashboard
        if (session()->get('logged_in')) {
            log_message('debug', 'User already logged in, redirecting to dashboard');
            return redirect()->to('/dashboard');
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            log_message('debug', 'POST detected, calling processLogin()');
            return $this->processLogin();
        }

        log_message('debug', 'Showing login form');
        return view('auth/login');
    }

    /**
     * Process login
     */
    private function processLogin()
    {
        // Debug: Log that we're in processLogin
        log_message('debug', 'ProcessLogin called');

        $rules = [
            'username' => 'required',
            'password' => 'required',
        ];

        if (!$this->validate($rules)) {
            log_message('debug', 'Validation failed: ' . json_encode($this->validator->getErrors()));
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        log_message('debug', 'Login attempt for username: ' . $username);

        // Find user by username or email
        $user = $this->userModel->where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (!$user) {
            log_message('debug', 'User not found: ' . $username);
            return redirect()->back()->withInput()->with('error', 'Usuario o contraseña incorrectos');
        }

        log_message('debug', 'User found: ' . $user['username']);

        // Check if account is locked
        if ($this->userModel->isLocked($user['id'])) {
            log_message('debug', 'Account locked: ' . $user['id']);
            $locked = $this->userModel->find($user['id']);
            $mins = !empty($locked['locked_until'])
                ? max(1, (int) ceil((strtotime($locked['locked_until']) - time()) / 60))
                : 15;
            return redirect()->back()->with('error', "🔒 Cuenta bloqueada por demasiados intentos fallidos. Espera {$mins} minuto(s) antes de volver a intentar.");
        }

        // Check if account is active
        if (!$user['is_active']) {
            log_message('debug', 'Account inactive: ' . $user['id']);
            return redirect()->back()->with('error', 'Cuenta inactiva');
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            log_message('debug', 'Invalid password for user: ' . $user['id']);
            $this->userModel->incrementFailedAttempts($user['id']);

            // Log failed attempt
            try {
                $this->auditModel->insert([
                    'user_id' => $user['id'],
                    'event' => 'login_failed',
                    'auditable_type' => 'App\\Models\\UserModel',
                    'auditable_id' => $user['id'],
                    'url' => (string) $this->request->getUri(),
                    'ip_address' => $this->request->getIPAddress(),
                    'user_agent' => (string) $this->request->getUserAgent(),
                ]);
            } catch (\Exception $e) {
                log_message('error', 'Audit log failed on login_failed: ' . $e->getMessage());
            }

            // Alerta con intentos restantes / bloqueo (máx. 5 intentos, bloqueo 15 min)
            $maxAttempts = 5;
            $updated     = $this->userModel->find($user['id']);
            if (!empty($updated['locked_until']) && strtotime($updated['locked_until']) > time()) {
                return redirect()->back()->withInput()->with('error', '🔒 Contraseña incorrecta. La cuenta se bloqueó 15 minutos por múltiples intentos fallidos.');
            }
            $remaining = max(0, $maxAttempts - (int) ($updated['failed_login_attempts'] ?? 0));
            $msg = $remaining > 0
                ? "Contraseña incorrecta. Te queda(n) {$remaining} intento(s) antes de que la cuenta se bloquee 15 minutos."
                : 'Contraseña incorrecta.';

            return redirect()->back()->withInput()->with('error', $msg);
        }

        log_message('debug', 'Password verified successfully');

        // Check if 2FA is enabled
        if ($user['two_factor_enabled'] && $this->twoFactorModel->isEnabled($user['id'])) {
            log_message('debug', '2FA required for user: ' . $user['id']);
            // Store user ID in session for 2FA verification
            session()->set('pending_2fa_user_id', $user['id']);
            return redirect()->to('/verify-2fa');
        }

        // Login successful
        log_message('debug', 'Completing login for user: ' . $user['id']);
        $this->completeLogin($user);

        log_message('debug', 'Redirecting to dashboard');
        return redirect()->to('/dashboard');
    }

    /**
     * Complete login process
     */
    private function completeLogin(array $user)
    {
        // Update login tracking
        $this->userModel->updateLoginTracking($user['id'], $this->request->getIPAddress());

        // Set session data
        session()->set([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'logged_in' => true,
            'language' => $user['preferred_language'] ?? 'es', // Set user's preferred language
        ]);

        // Set locale for current request
        $this->request->setLocale($user['preferred_language'] ?? 'es');

        // Set active role (first role by default)
        $userWithRoles = $this->userModel->getUserWithRoles($user['id']);
        if (!empty($userWithRoles['roles'])) {
            session()->set('active_role_id', $userWithRoles['roles'][0]['id']);
        }

        // Log successful login
        try {
            $this->auditModel->insert([
                'user_id' => $user['id'],
                'event' => 'login',
                'auditable_type' => 'App\\Models\\UserModel',
                'auditable_id' => $user['id'],
                'url' => (string) $this->request->getUri(),
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => (string) $this->request->getUserAgent(),
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Audit log failed on login: ' . $e->getMessage());
        }
    }

    /**
     * Show 2FA verification form
     */
    public function verify2FA()
    {
        $userId = session()->get('pending_2fa_user_id');

        if (!$userId) {
            return redirect()->to('/login');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->process2FA();
        }

        return view('auth/verify_2fa');
    }

    /**
     * Process 2FA verification
     */
    private function process2FA()
    {
        $userId = session()->get('pending_2fa_user_id');

        if (!$userId) {
            return redirect()->to('/login');
        }

        $code = $this->request->getPost('code');
        $useRecoveryCode = $this->request->getPost('use_recovery_code');

        if ($useRecoveryCode) {
            // Verify recovery code
            if ($this->twoFactorModel->verifyRecoveryCode($userId, $code)) {
                $user = $this->userModel->find($userId);
                session()->remove('pending_2fa_user_id');
                $this->completeLogin($user);
                return redirect()->to('/dashboard')->with('warning', lang('Auth.recoveryCodeUsed'));
            }
        } else {
            // Verify TOTP code (will be implemented in TotpHandler)
            $twoFactor = $this->twoFactorModel->getByUserId($userId);

            if ($twoFactor) {
                // TODO: Implement TOTP verification with TotpHandler
                // For now, simple validation
                if (!empty($code) && strlen($code) === 6) {
                    $user = $this->userModel->find($userId);
                    session()->remove('pending_2fa_user_id');
                    $this->completeLogin($user);
                    return redirect()->to('/dashboard');
                }
            }
        }

        return redirect()->back()->with('error', lang('Auth.invalid2FACode'));
    }

    /**
     * Show registration form
     */
    public function register()
    {
        log_message('debug', '=== REGISTER METHOD CALLED ===');
        log_message('debug', 'Request method: ' . $this->request->getMethod());

        // Check if already logged in
        if (session()->get('logged_in')) {
            log_message('debug', 'User already logged in, redirecting to dashboard');
            return redirect()->to('/dashboard');
        }

        // Check if public registration is enabled
        if (!$this->settingModel->isPublicRegistrationEnabled()) {
            log_message('warning', 'Public registration is disabled, redirecting to login');
            return redirect()->to('/login')->with('error', lang('Auth.publicRegistrationDisabled'));
        }

        if (strtolower($this->request->getMethod()) === 'post') {
            log_message('debug', 'POST detected, calling processRegistration()');
            return $this->processRegistration();
        }

        log_message('debug', 'Showing registration form');
        return view('auth/register');
    }

    /**
     * Process registration
     */
    private function processRegistration()
    {
        // Sin volcar el POST: lleva la contraseña en claro.
        log_message('debug', 'ProcessRegistration called');

        // Check if public registration is still enabled
        if (!$this->settingModel->isPublicRegistrationEnabled()) {
            log_message('warning', 'Public registration disabled during form submission');
            return redirect()->to('/login')->with('error', lang('Auth.publicRegistrationDisabled'));
        }

        $rules = [
            'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            'first_name' => 'required|max_length[50]',
            'last_name' => 'required|max_length[50]',
            'phone' => 'permit_empty|max_length[30]|regex_match[/^[0-9+\s()-]{6,30}$/]',
        ];

        if (!$this->validate($rules)) {
            log_message('debug', 'Validation failed: ' . json_encode($this->validator->getErrors()));
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'phone' => $this->request->getPost('phone'),
            'is_active' => true,
            'auth_method' => 'password',
            'preferred_language' => session()->get('language') ?? 'es',
        ];

        log_message('debug', 'Attempting to insert user: ' . $data['username']);

        $userId = $this->userModel->insert($data);

        if (!$userId) {
            $errors = $this->userModel->errors();
            log_message('error', 'User registration failed: ' . json_encode($errors));
            return redirect()->back()->withInput()->with('error', lang('Auth.registrationFailed') . ' ' . json_encode($errors));
        }

        log_message('debug', 'User created successfully with ID: ' . $userId);

        // Assign default "Usuario" role
        $db = \Config\Database::connect();
        $defaultRole = $db->table('roles')->where('name', 'Usuario')->get()->getRow();

        if ($defaultRole) {
            log_message('debug', 'Assigning default role: ' . $defaultRole->name);
            $this->userModel->assignRole($userId, $defaultRole->id);
        } else {
            log_message('warning', 'Default role "Usuario" not found');
        }

        // Log registration
        try {
            $this->auditModel->insert([
                'user_id' => $userId,
                'event' => 'user_registered',
                'auditable_type' => 'App\\Models\\UserModel',
                'auditable_id' => $userId,
                'url' => (string) $this->request->getUri(),
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => (string) $this->request->getUserAgent(),
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Audit log failed on registration: ' . $e->getMessage());
        }

        log_message('debug', 'Registration completed successfully, redirecting to login');
        return redirect()->to('/login')->with('success', lang('Auth.registrationSuccess'));
    }

    /**
     * Show forgot password form
     */
    public function forgotPassword()
    {
        return view('auth/forgot_password');
    }

    /**
     * Process forgot password
     */
    public function processForgotPassword()
    {
        $email = $this->request->getPost('email');

        $user = $this->userModel->where('email', $email)->first();

        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            // Update user
            $this->userModel->update($user['id'], [
                'reset_hash' => $hash,
                'reset_expires_at' => $expiresAt
            ]);

            // Send email
            $emailLib = new \App\Libraries\EmailLibrary();
            $emailSent = $emailLib->sendPasswordReset($user['email'], $token);

            // Además, enviar por WhatsApp si está habilitado y el usuario tiene teléfono
            if (!empty($user['phone'])) {
                $wa = new \App\Libraries\WhatsAppService();
                if ($wa->isEnabled()) {
                    try {
                        $res = $wa->sendPasswordReset($user['phone'], $token);
                        if (!$res['ok']) {
                            log_message('error', 'WhatsApp reset falló: ' . $res['message']);
                        }
                    } catch (\Throwable $e) {
                        log_message('error', 'WhatsApp reset excepción: ' . $e->getMessage());
                    }
                }
            }

            if ($emailSent) {
                return redirect()->back()->with('success', 'Se ha enviado un correo con las instrucciones.');
            }
            return redirect()->back()->with('error', 'Error al enviar el correo. Contacte al administrador.');
        }

        // Always show success message for security/privacy (prevent user enumeration)
        return redirect()->back()->with('success', 'Se ha enviado un correo con las instrucciones.');
    }

    /**
     * Show reset password form
     */
    public function resetPassword($token = null)
    {
        if (!$token) {
            return redirect()->to('/login');
        }

        $hash = hash('sha256', $token);

        // Verify token
        $user = $this->userModel->where('reset_hash', $hash)
            ->where('reset_expires_at >', date('Y-m-d H:i:s'))
            ->first();

        if (!$user) {
            return redirect()->to('/forgot-password')->with('error', 'El enlace es inválido o ha expirado.');
        }

        return view('auth/reset_password', ['token' => $token]);
    }

    /**
     * Process reset password
     */
    public function processResetPassword()
    {
        $token = $this->request->getPost('token');
        $password = $this->request->getPost('password');
        $confirm = $this->request->getPost('password_confirm');

        if ($password !== $confirm) {
            return redirect()->back()->with('error', 'Las contraseñas no coinciden');
        }

        if (strlen($password) < 8) {
            return redirect()->back()->with('error', 'La contraseña debe tener al menos 8 caracteres');
        }

        $hash = hash('sha256', $token);

        $user = $this->userModel->where('reset_hash', $hash)
            ->where('reset_expires_at >', date('Y-m-d H:i:s'))
            ->first();

        if (!$user) {
            return redirect()->to('/forgot-password')->with('error', 'El enlace es inválido o ha expirado.');
        }

        // Update password and clear token
        $this->userModel->update($user['id'], [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'reset_hash' => null,
            'reset_expires_at' => null
        ]);

        return redirect()->to('/login')->with('success', 'Contraseña actualizada correctamente. Inicia sesión.');
    }

    /**
     * Logout
     */
    public function logout()
    {
        $userId = session()->get('user_id');

        if ($userId) {
            // Log logout
            try {
                $this->auditModel->insert([
                    'user_id' => $userId,
                    'event' => 'logout',
                    'auditable_type' => 'App\\Models\\UserModel',
                    'auditable_id' => $userId,
                    'url' => (string) $this->request->getUri(),
                    'ip_address' => $this->request->getIPAddress(),
                    'user_agent' => (string) $this->request->getUserAgent(),
                ]);
            } catch (\Exception $e) {
                // Ignore audit error on logout
                log_message('error', 'Audit log failed on logout: ' . $e->getMessage());
            }
        }

        session()->destroy();
        return redirect()->to('/login')->with('success', lang('Auth.logoutSuccess'));
    }
}
