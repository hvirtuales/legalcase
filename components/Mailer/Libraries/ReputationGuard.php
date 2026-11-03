<?php

namespace Components\Mailer\Libraries;

use Components\Mailer\Models\RecipientModel;
use Components\Mailer\Models\SuppressionModel;

/**
 * Vigila la reputación de envío por empresa para no exceder los umbrales que
 * hacen que Amazon SES ponga la cuenta bajo revisión o pause el envío:
 *  - Rebotes (permanentes) ≥ 5 % → revisión; nosotros bloqueamos a partir de 8 %.
 *  - Quejas ≥ 0.1 % → revisión; bloqueamos a partir de 0.5 %.
 * Las tasas se calculan sobre los correos realmente enviados; el numerador son
 * las supresiones por rebote/queja (los rebotes *blandos* no cuentan porque no
 * se suprimen). Con muy poco volumen no se bloquea (falsos positivos).
 */
class ReputationGuard
{
    public const BOUNCE_WARN     = 5.0;
    public const BOUNCE_BLOCK    = 8.0;
    public const COMPLAINT_WARN  = 0.1;
    public const COMPLAINT_BLOCK = 0.5;
    public const MIN_BOUNCE      = 20;  // muestra mínima para exigir tasa de rebote
    public const MIN_COMPLAINT   = 50;  // muestra mínima para exigir tasa de quejas

    /** @return array{sent:int,bounces:int,complaints:int,bounce_rate:float,complaint_rate:float} */
    public function rates(int $companyId): array
    {
        $sent       = (new RecipientModel())->where('company_id', $companyId)->where('sent_at IS NOT NULL')->countAllResults();
        $bounces    = (new SuppressionModel())->where('company_id', $companyId)->where('reason', 'bounce')->countAllResults();
        $complaints = (new SuppressionModel())->where('company_id', $companyId)->where('reason', 'complaint')->countAllResults();

        return [
            'sent'           => $sent,
            'bounces'        => $bounces,
            'complaints'     => $complaints,
            'bounce_rate'    => $sent > 0 ? round($bounces / $sent * 100, 2) : 0.0,
            'complaint_rate' => $sent > 0 ? round($complaints / $sent * 100, 2) : 0.0,
        ];
    }

    /**
     * Nivel de reputación: ok | warn | block, con motivo legible.
     *
     * @return array{sent:int,bounces:int,complaints:int,bounce_rate:float,complaint_rate:float,level:string,reason:string}
     */
    public function status(int $companyId): array
    {
        $r     = $this->rates($companyId);
        $level = 'ok';
        $reason = '';

        if ($r['sent'] >= self::MIN_BOUNCE && $r['bounce_rate'] >= self::BOUNCE_BLOCK) {
            $level  = 'block';
            $reason = lang('Mailer.rBounceBlock', [$r['bounce_rate'], self::BOUNCE_BLOCK]);
        } elseif ($r['sent'] >= self::MIN_COMPLAINT && $r['complaint_rate'] >= self::COMPLAINT_BLOCK) {
            $level  = 'block';
            $reason = lang('Mailer.rComplaintBlock', [$r['complaint_rate'], self::COMPLAINT_BLOCK]);
        } elseif ($r['sent'] >= self::MIN_BOUNCE && $r['bounce_rate'] >= self::BOUNCE_WARN) {
            $level  = 'warn';
            $reason = lang('Mailer.rBounceWarn', [$r['bounce_rate']]);
        } elseif ($r['sent'] >= self::MIN_COMPLAINT && $r['complaint_rate'] >= self::COMPLAINT_WARN) {
            $level  = 'warn';
            $reason = lang('Mailer.rComplaintWarn', [$r['complaint_rate']]);
        }

        return $r + ['level' => $level, 'reason' => $reason];
    }

    public function canSend(int $companyId): bool
    {
        return $this->status($companyId)['level'] !== 'block';
    }
}
