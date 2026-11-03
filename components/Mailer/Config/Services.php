<?php

namespace Components\Mailer\Config;

use CodeIgniter\Config\BaseService;
use Components\Mailer\Libraries\MailerService;

/**
 * Servicios del componente Mailer (auto-descubiertos). Resuélvelos con
 * service('mailerService').
 */
class Services extends BaseService
{
    public static function mailerService(bool $getShared = true): MailerService
    {
        if ($getShared) {
            return static::getSharedInstance('mailerService');
        }

        return new MailerService();
    }
}
