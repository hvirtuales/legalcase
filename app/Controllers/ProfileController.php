<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\TwoFactorAuthModel;

class ProfileController extends BaseController
{
    protected $userModel;
    protected $twoFactorModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->twoFactorModel = new TwoFactorAuthModel();
    }

    /**
     * Show user profile
     */
    public function index()
    {
        $userId = session()->get('user_id');
        $user = $this->userModel->find($userId);

        if (!$user) {
            return redirect()->to('/login');
        }

        // Get roles
        $userWithRoles = $this->userModel->getUserWithRoles($userId);

        // Get 2FA status
        $is2FAEnabled = $this->twoFactorModel->isEnabled($userId);

        $data = [
            'user' => $userWithRoles,
            'is2FAEnabled' => $is2FAEnabled,
            'title' => lang('App.myProfile'),
        ];

        return view('profile/index', $data);
    }

    /**
     * Update profile
     */
    public function update()
    {
        // Sin volcar el POST: lleva la contraseña en claro.
        log_message('debug', '=== PROFILE UPDATE METHOD CALLED ===');

        $userId = session()->get('user_id');
        $user = $this->userModel->find($userId);

        if (!$user) {
            return redirect()->to('/login');
        }

        $rules = [
            'first_name' => 'required|max_length[50]',
            'last_name' => 'required|max_length[50]',
            'email' => 'required|valid_email|is_unique[users.email,id,' . $userId . ']',
            'phone' => 'permit_empty|max_length[30]|regex_match[/^[0-9+\s()-]{6,30}$/]',
            'theme_color' => 'permit_empty|regex_match[/^#[0-9A-F]{6}$/i]',
        ];

        // Check if password change is requested
        $password = $this->request->getPost('password');
        if (!empty($password)) {
            log_message('debug', 'Password change requested');
            // Require current password for security
            $rules['current_password'] = 'required';
            $rules['password'] = 'min_length[8]';
            $rules['password_confirm'] = 'matches[password]';
        }

        if (!$this->validate($rules)) {
            log_message('debug', 'Validation failed: ' . json_encode($this->validator->getErrors()));
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // If changing password, verify current password first
        if (!empty($password)) {
            $currentPassword = $this->request->getPost('current_password');

            if (!password_verify($currentPassword, $user['password'])) {
                log_message('warning', 'Current password verification failed for user: ' . $userId);
                return redirect()->back()->withInput()
                    ->with('error', 'La contraseña actual es incorrecta');
            }

            log_message('debug', 'Current password verified successfully');
        }

        $data = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
            'preferred_language' => $this->request->getPost('preferred_language'),
            'theme_color' => $this->request->getPost('theme_color') ?: null,
        ];

        // Update password if provided
        if (!empty($password)) {
            $data['password'] = $password;
            log_message('debug', 'Password will be updated');
        }

        // Disable model validation since we already validated in the controller
        // This prevents the model's is_unique rule from incorrectly flagging the user's own email
        $this->userModel->skipValidation(true);

        if (!$this->userModel->update($userId, $data)) {
            $errors = $this->userModel->errors();
            log_message('error', 'Profile update failed: ' . json_encode($errors));
            return redirect()->back()->withInput()->with('error', lang('App.updateFailed') . ' ' . json_encode($errors));
        }

        // Re-enable validation for future operations
        $this->userModel->skipValidation(false);

        log_message('debug', 'Profile updated successfully');

        // Update session data if name/email changed
        session()->set([
            'username' => $user['username'], // username doesn't change
            'email' => $data['email'],
        ]);

        // Update language in session if changed
        if ($data['preferred_language'] !== $user['preferred_language']) {
            session()->set('language', $data['preferred_language']);
        }

        return redirect()->to('/profile')->with('success', lang('App.updateSuccess'));
    }
}
