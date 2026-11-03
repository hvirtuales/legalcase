<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\LeadModel;

/**
 * Prevención de duplicados (§23): email normalizado, teléfono, WhatsApp y
 * nombre + teléfono.
 *
 * Los teléfonos se comparan por sus **últimos 10 dígitos**: «+1 (555) 123-4567»,
 * «15551234567» y «555 123 4567» son el mismo número escrito por tres personas.
 * Con menos de 7 dígitos no se compara: es un número a medias y daría falsos
 * positivos.
 */
class Dedupe
{
    public static function email(?string $email): ?string
    {
        $e = mb_strtolower(trim((string) $email));

        return $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    public static function phone(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);

        return strlen($d) >= 7 ? substr($d, 0, 20) : null;
    }

    public static function key10(?string $digits): ?string
    {
        return $digits !== null && strlen($digits) >= 7 ? substr($digits, -10) : null;
    }

    /**
     * Leads de la empresa que parecen la misma persona.
     *
     * @return array<int,array{lead:array,reasons:string[]}>
     */
    public function find(int $companyId, array $data, ?int $excludeId = null, int $limit = 5): array
    {
        $email = self::email($data['email'] ?? null);
        $keys  = array_values(array_unique(array_filter([
            self::key10(self::phone($data['phone'] ?? null)),
            self::key10(self::phone($data['whatsapp'] ?? null)),
        ])));

        if ($email === null && $keys === []) {
            return [];
        }

        $m = new LeadModel();
        $m->where('company_id', $companyId)->groupStart();
        $first = true;
        if ($email !== null) {
            $m->where('email_norm', $email);
            $first = false;
        }
        foreach ($keys as $k) {
            foreach (['phone_norm', 'whatsapp_norm'] as $col) {
                $first ? $m->like($col, $k, 'before') : $m->orLike($col, $k, 'before');
                $first = false;
            }
        }
        $m->groupEnd();
        if ($excludeId) {
            $m->where('id !=', $excludeId);
        }

        $out = [];
        foreach ($m->orderBy('id', 'desc')->findAll($limit * 3) as $lead) {
            $reasons = [];
            if ($email !== null && $lead['email_norm'] === $email) {
                $reasons[] = 'email';
            }
            foreach ($keys as $k) {
                if (self::key10($lead['phone_norm']) === $k || self::key10($lead['whatsapp_norm']) === $k) {
                    $reasons[] = 'phone';
                    // Nombre + teléfono: la coincidencia más fuerte que el §23 enumera.
                    if (mb_strtolower(trim((string) ($data['last_name'] ?? ''))) === mb_strtolower((string) $lead['last_name'])) {
                        $reasons[] = 'name_phone';
                    }
                    break;
                }
            }
            if ($reasons) {
                $out[] = ['lead' => $lead, 'reasons' => array_values(array_unique($reasons))];
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
