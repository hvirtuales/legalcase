<?php

namespace App\Models;

use CodeIgniter\Model;

class MagicLinkModel extends Model
{
    protected $table = 'magic_links';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'email',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = null;

    /**
     * Generate a new magic link
     */
    public function generateLink(string $email, ?int $userId = null): array
    {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));

        // Set expiration (15 minutes from now)
        $expiresAt = date('Y-m-d H:i:s', time() + 900);

        $data = [
            'user_id' => $userId,
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];

        $this->insert($data);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Validate a magic link token
     */
    public function validateToken(string $token): ?array
    {
        $link = $this->where('token', $token)
            ->where('used_at', null)
            ->first();

        if (!$link) {
            return null;
        }

        // Check if expired
        if (strtotime($link['expires_at']) < time()) {
            return null;
        }

        return $link;
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(string $token): bool
    {
        return $this->where('token', $token)
            ->set(['used_at' => date('Y-m-d H:i:s')])
            ->update();
    }

    /**
     * Clean expired tokens
     */
    public function cleanExpired(): int
    {
        return $this->where('expires_at <', date('Y-m-d H:i:s'))
            ->delete();
    }

    /**
     * Get active link for email
     */
    public function getActiveLink(string $email): ?array
    {
        return $this->where('email', $email)
            ->where('used_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orderBy('created_at', 'DESC')
            ->first();
    }
}
