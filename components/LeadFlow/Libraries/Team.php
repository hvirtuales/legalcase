<?php

namespace Components\LeadFlow\Libraries;

/**
 * Los usuarios de la firma: miembros de `company_users` más el dueño.
 *
 * Es lo único a lo que se puede asignar un lead, una actividad o una consulta:
 * un id de usuario que llegue en el POST se comprueba aquí antes de guardarse,
 * o bastaría con cambiarlo para asignar el lead a alguien de otra firma.
 */
class Team
{
    private static array $cache = [];

    /** @return array<int,string> id => nombre para mostrar */
    public static function users(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }
        if (isset(self::$cache[$companyId])) {
            return self::$cache[$companyId];
        }

        $out = [];
        foreach ((new \App\Models\CompanyUserModel())->getUsers($companyId) as $u) {
            $out[(int) $u['id']] = self::name($u);
        }

        $db      = \Config\Database::connect();
        $company = $db->table('companies')->select('owner_id')->where('id', $companyId)->get()->getRowArray();
        if (! empty($company['owner_id']) && ! isset($out[(int) $company['owner_id']])) {
            $owner = $db->table('users')->where('id', (int) $company['owner_id'])->where('deleted_at', null)->get()->getRowArray();
            if ($owner) {
                $out[(int) $owner['id']] = self::name($owner);
            }
        }
        asort($out, SORT_LOCALE_STRING);

        return self::$cache[$companyId] = $out;
    }

    public static function isMember(int $companyId, ?int $userId): bool
    {
        return $userId !== null && $userId > 0 && isset(self::users($companyId)[$userId]);
    }

    public static function name(array $u): string
    {
        $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));

        return $n !== '' ? $n : (string) ($u['username'] ?? $u['email'] ?? '#' . ($u['id'] ?? ''));
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
