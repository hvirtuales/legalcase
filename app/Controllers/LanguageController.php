<?php

namespace App\Controllers;

class LanguageController extends BaseController
{
    /**
     * Switch language
     */
    public function switch(string $locale)
    {
        // Validate locale
        $availableLanguages = ['es', 'en'];

        if (!in_array($locale, $availableLanguages)) {
            return redirect()->back();
        }

        // Set in session
        session()->set('language', $locale);

        // Update user preference if logged in
        $userId = session()->get('user_id');
        if ($userId) {
            $userModel = new \App\Models\UserModel();
            $userModel->update($userId, ['preferred_language' => $locale]);

            // Log language change
            $auditModel = new \App\Models\AuditLogModel();
            $auditModel->insert([
                'user_id' => $userId,
                'event' => 'language_changed',
                'auditable_type' => 'App\\Models\\UserModel',
                'auditable_id' => $userId,
                'new_values' => json_encode(['language' => $locale]),
                'url' => $this->request->getUri()->getPath(),
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => $this->request->getUserAgent()->getAgentString(),
            ]);
        }

        // Redirect back
        return redirect()->back();
    }
}
