<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiTokenModel extends Model
{
    protected $table = 'api_tokens';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'company_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Create a new API token
     */
    public function createToken(int $userId, string $name, array $abilities = [], ?int $expiresInDays = null): array
    {
        // Generate JWT token (will be handled by JwtHandler library)
        $tokenString = bin2hex(random_bytes(32)); // Placeholder, JWT will replace this

        $expiresAt = null;
        if ($expiresInDays) {
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInDays * 86400));
        }

        $data = [
            'user_id' => $userId,
            'name' => $name,
            'token' => $tokenString,
            'abilities' => json_encode($abilities),
            'expires_at' => $expiresAt,
            'created_by' => $userId,
        ];

        $id = $this->insert($data);

        return [
            'id' => $id,
            'token' => $tokenString,
            'name' => $name,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Validate a token
     */
    public function validateToken(string $token): ?array
    {
        $apiToken = $this->where('token', $token)->first();

        if (!$apiToken) {
            return null;
        }

        // Check if expired
        if ($apiToken['expires_at'] && strtotime($apiToken['expires_at']) < time()) {
            return null;
        }

        return $apiToken;
    }

    /**
     * Revoke a token
     */
    public function revokeToken(int $tokenId): bool
    {
        return $this->delete($tokenId);
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(int $tokenId): bool
    {
        return $this->update($tokenId, [
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get user's tokens
     */
    public function getUserTokens(int $userId): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get active tokens count for user
     */
    public function getActiveTokensCount(int $userId): int
    {
        return $this->where('user_id', $userId)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orWhere('expires_at', null)
            ->countAllResults();
    }

    /**
     * Clean expired tokens
     */
    public function cleanExpired(): int
    {
        return $this->where('expires_at <', date('Y-m-d H:i:s'))
            ->delete();
    }
}
