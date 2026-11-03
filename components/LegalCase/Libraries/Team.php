<?php

namespace Components\LegalCase\Libraries;

use App\Models\CompanyModel;
use App\Models\CompanyUserModel;

/**
 * Personal de la empresa al que se le puede asignar trabajo (responsable, equipo,
 * validador). Se lee del núcleo por sus métodos públicos de siempre; el componente
 * no consulta las pivotes por su cuenta.
 */
class Team
{
    /** @var array<int,array<int,string>> */
    private static array $cache = [];

    /** [user_id => nombre visible] del dueño y los miembros de la empresa. */
    public static function users(int $companyId): array
    {
        if (isset(self::$cache[$companyId])) {
            return self::$cache[$companyId];
        }

        $out = [];
        foreach ((new CompanyUserModel())->getUsers($companyId) as $u) {
            $out[(int) $u['id']] = self::label($u);
        }

        $company = (new CompanyModel())->find($companyId);
        $ownerId = (int) ($company['owner_id'] ?? 0);
        if ($ownerId && !isset($out[$ownerId])) {
            $owner = (new \App\Models\UserModel())->find($ownerId);
            if ($owner) {
                $out[$ownerId] = self::label($owner);
            }
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return self::$cache[$companyId] = $out;
    }

    public static function isMember(int $companyId, int $userId): bool
    {
        return $userId > 0 && isset(self::users($companyId)[$userId]);
    }

    public static function name(int $companyId, ?int $userId): string
    {
        if (!$userId) {
            return lang('Lgc.system');
        }

        return self::users($companyId)[$userId] ?? ('#' . $userId);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function label(array $u): string
    {
        return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: (string) ($u['username'] ?? ('#' . $u['id']));
    }
}
