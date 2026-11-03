<?php

namespace App\Models;

use CodeIgniter\Model;

class AuthProviderModel extends Model
{
    protected $table = 'auth_providers';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_data',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Link a provider to a user
     */
    public function linkProvider(int $userId, string $provider, string $providerUserId, array $data = []): bool
    {
        // Check if already linked
        $existing = $this->where('user_id', $userId)
            ->where('provider', $provider)
            ->first();

        $providerData = [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_data' => json_encode($data),
            'created_by' => $userId,
        ];

        if ($existing) {
            return $this->update($existing['id'], $providerData);
        }

        return $this->insert($providerData) !== false;
    }

    /**
     * Get user by provider credentials
     */
    public function getUserByProvider(string $provider, string $providerUserId): ?array
    {
        $authProvider = $this->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if (!$authProvider) {
            return null;
        }

        $userModel = new UserModel();
        return $userModel->find($authProvider['user_id']);
    }

    /**
     * Unlink provider from user
     */
    public function unlinkProvider(int $userId, string $provider): bool
    {
        return $this->where('user_id', $userId)
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * Get all linked providers for user
     */
    public function getUserProviders(int $userId): array
    {
        return $this->where('user_id', $userId)->findAll();
    }

    /**
     * Check if provider is linked to user
     */
    public function isProviderLinked(int $userId, string $provider): bool
    {
        return $this->where('user_id', $userId)
            ->where('provider', $provider)
            ->countAllResults() > 0;
    }

    /**
     * Get provider data
     */
    public function getProviderData(int $userId, string $provider): ?array
    {
        $authProvider = $this->where('user_id', $userId)
            ->where('provider', $provider)
            ->first();

        if (!$authProvider) {
            return null;
        }

        return json_decode($authProvider['provider_data'], true);
    }

    /**
     * Update provider data
     */
    public function updateProviderData(int $userId, string $provider, array $data): bool
    {
        return $this->where('user_id', $userId)
            ->where('provider', $provider)
            ->set(['provider_data' => json_encode($data)])
            ->update();
    }
}
