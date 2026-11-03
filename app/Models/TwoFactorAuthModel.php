<?php

namespace App\Models;

use CodeIgniter\Model;

class TwoFactorAuthModel extends Model
{
    protected $table = 'two_factor_auth';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'secret',
        'recovery_codes',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get 2FA settings for user
     */
    public function getByUserId(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }

    /**
     * Enable 2FA for user
     */
    public function enable(int $userId, string $secret, array $recoveryCodes): bool
    {
        $existing = $this->getByUserId($userId);

        $data = [
            'user_id' => $userId,
            'secret' => $secret,
            'recovery_codes' => json_encode($recoveryCodes),
            'enabled' => true,
            'updated_by' => $userId,
        ];

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        $data['created_by'] = $userId;
        return $this->insert($data) !== false;
    }

    /**
     * Disable 2FA for user
     */
    public function disable(int $userId): bool
    {
        $existing = $this->getByUserId($userId);

        if (!$existing) {
            return true;
        }

        return $this->update($existing['id'], [
            'enabled' => false,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode(int $userId, string $code): bool
    {
        $twoFactor = $this->getByUserId($userId);

        if (!$twoFactor || !$twoFactor['enabled']) {
            return false;
        }

        $recoveryCodes = json_decode($twoFactor['recovery_codes'], true);

        if (!is_array($recoveryCodes)) {
            return false;
        }

        // Check if code exists
        $key = array_search($code, $recoveryCodes);

        if ($key === false) {
            return false;
        }

        // Remove used code
        unset($recoveryCodes[$key]);

        $this->update($twoFactor['id'], [
            'recovery_codes' => json_encode(array_values($recoveryCodes)),
        ]);

        return true;
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(int $userId): array
    {
        $twoFactor = $this->getByUserId($userId);

        if (!$twoFactor) {
            return [];
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $this->update($twoFactor['id'], [
            'recovery_codes' => json_encode($recoveryCodes),
            'updated_by' => $userId,
        ]);

        return $recoveryCodes;
    }

    /**
     * Generate recovery codes
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }

    /**
     * Get remaining recovery codes count
     */
    public function getRemainingCodesCount(int $userId): int
    {
        $twoFactor = $this->getByUserId($userId);

        if (!$twoFactor) {
            return 0;
        }

        $recoveryCodes = json_decode($twoFactor['recovery_codes'], true);

        return is_array($recoveryCodes) ? count($recoveryCodes) : 0;
    }

    /**
     * Check if 2FA is enabled for user
     */
    public function isEnabled(int $userId): bool
    {
        $twoFactor = $this->getByUserId($userId);

        return $twoFactor && $twoFactor['enabled'];
    }
}
