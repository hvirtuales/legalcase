<?php

namespace Components\LegalCase\Libraries;

use App\Models\UserModel;
use Components\LegalCase\Models\SupportGrantModel;

/**
 * Acceso de soporte de la plataforma (documento maestro §3): la plataforma y el distribuidor
 * NO leen los asuntos de un despacho salvo que el propio despacho lo autorice, a una persona
 * concreta, con un motivo y por un tiempo limitado. Es de solo lectura y nunca alcanza lo
 * privilegiado. Conceder, revocar y cada sesión de uso quedan en la bitácora.
 */
class Support
{
    public const MAX_HOURS = 72;

    public function grant(string $who, int $hours, string $reason, Access $acc): Result
    {
        $who    = trim($who);
        $reason = trim($reason);
        if ($who === '' || mb_strlen($reason) < 10) {
            return Result::fail(lang('Lgc.errSupportForm'));
        }
        $user = (new UserModel())->groupStart()->where('username', $who)->orWhere('email', $who)->groupEnd()->first();
        if (!$user) {
            return Result::fail(lang('Lgc.errSupportUser'));
        }
        if (Team::isMember($acc->companyId, (int) $user['id'])) {
            return Result::fail(lang('Lgc.errSupportMember'));
        }
        $hours = max(1, min(self::MAX_HOURS, $hours));
        $model = new SupportGrantModel();
        $model->insert([
            'company_id' => $acc->companyId, 'user_id' => (int) $user['id'], 'reason' => mb_substr($reason, 0, 255),
            'granted_by' => $acc->userId ?: null, 'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log($acc->companyId, null, 'support.granted', 'user', (int) $user['id'], ['hours' => $hours]);

        return Result::ok(lang('Lgc.okSupportGranted', [(string) $user['username'], $hours]));
    }

    public function revoke(int $grantId, Access $acc): Result
    {
        $model = new SupportGrantModel();
        $row   = $model->where('company_id', $acc->companyId)->find($grantId);
        if (!$row) {
            return Result::fail(lang('Lgc.errNotFound'));
        }
        $model->update($grantId, ['revoked_at' => date('Y-m-d H:i:s')]);
        Audit::log($acc->companyId, null, 'support.revoked', 'user', (int) $row['user_id']);

        return Result::ok(lang('Lgc.okSupportRevoked'));
    }
}
