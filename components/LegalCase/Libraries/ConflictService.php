<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\ConflictCheckModel;
use Components\LegalCase\Models\ContactModel;

/**
 * Verificación de conflicto de interés (Ley 1123 de 2007; ABA Model Rules 1.7 y 1.9).
 *
 * Antes de que una persona entre a un asunto con un papel, busca si ESA persona —o otra que
 * parezca ser la misma— aparece en OTRO asunto del despacho en el lado contrario:
 *
 *   lado del cliente:   client, representative, payer
 *   lado contrario:     counterparty, counsel
 *
 * «La misma persona» se decide por tres criterios, de más a menos fuerte: el mismo contacto,
 * el mismo número de documento y el mismo nombre normalizado (sin tildes, mayúsculas,
 * puntuación ni sufijos societarios: S.A.S., Ltda., Inc., LLC…).
 *
 * Cuenta también los asuntos CERRADOS: el deber de lealtad con el antiguo cliente subsiste
 * (Model Rule 1.9). No decide por el abogado: si hay coincidencias, la operación se detiene
 * hasta que alguien las revise y deje una justificación; todo queda registrado como evidencia.
 */
class ConflictService
{
    public const CLIENT_SIDE  = ['client', 'representative', 'guardian', 'payer'];
    public const ADVERSE_SIDE = ['counterparty', 'counsel'];

    private const SUFFIXES = ['sas', 's a s', 'sa', 's a', 'ltda', 'limitada', 'sca', 'sc', 'eu', 'e u', 'inc', 'llc', 'corp', 'corporation', 'co', 'ltd', 'plc'];

    public static function normalize(string $name): string
    {
        $s = mb_strtolower(trim($name));
        if (class_exists(\Normalizer::class)) {
            $s = preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($s, \Normalizer::FORM_D));
        }
        $s = preg_replace('/[^a-z0-9ñ ]+/u', ' ', $s);
        $s = ' ' . preg_replace('/\s+/', ' ', $s) . ' ';
        foreach (self::SUFFIXES as $suf) {
            $s = str_replace(' ' . $suf . ' ', ' ', $s);
        }

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private static function docKey(?string $doc): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', (string) $doc);
    }

    /** Roles que serían adversos a `$role`. */
    private static function adverseTo(string $role): array
    {
        if (in_array($role, self::CLIENT_SIDE, true)) {
            return self::ADVERSE_SIDE;
        }
        if (in_array($role, self::ADVERSE_SIDE, true)) {
            return self::CLIENT_SIDE;
        }

        return [];
    }

    /**
     * Coincidencias adversas para que `$contactId` entre con `$role`.
     *
     * @return list<array{matter_id:int,number:string,title:string,status:string,role:string,contact:string,match:string}>
     */
    public function check(int $companyId, int $contactId, string $role, ?int $excludeMatterId = null): array
    {
        $adverse = self::adverseTo($role);
        $contact = (new ContactModel())->own($companyId, $contactId);
        if (!$adverse || !$contact) {
            return [];
        }

        // Personas que podrían ser la misma: el contacto, mismo documento o mismo nombre normalizado.
        $same    = [(int) $contact['id'] => 'same_contact'];
        $doc     = self::docKey($contact['doc_number'] ?? '');
        $norm    = self::normalize((string) $contact['name']);
        foreach ((new ContactModel())->where('company_id', $companyId)->where('id !=', (int) $contact['id'])->findAll(5000) as $c) {
            if ($doc !== '' && self::docKey($c['doc_number']) === $doc) {
                $same[(int) $c['id']] = 'same_document';
            } elseif ($norm !== '' && self::normalize((string) $c['name']) === $norm) {
                $same[(int) $c['id']] = 'same_name';
            }
        }

        $q = db_connect()->table('cmp_lgc_participants p')
            ->select('p.matter_id, p.role, p.contact_id, m.number, m.title, m.status, c.name AS contact_name')
            ->join('cmp_lgc_matters m', 'm.id = p.matter_id')
            ->join('cmp_lgc_contacts c', 'c.id = p.contact_id')
            ->where('p.company_id', $companyId)
            ->where('m.company_id', $companyId)
            ->where('m.deleted_at', null)
            ->whereIn('p.contact_id', array_keys($same))
            ->whereIn('p.role', $adverse);
        if ($excludeMatterId) {
            $q->where('p.matter_id !=', $excludeMatterId);
        }

        $hits = [];
        foreach ($q->get()->getResultArray() as $r) {
            $hits[] = [
                'matter_id' => (int) $r['matter_id'],
                'number'    => (string) $r['number'],
                'title'     => (string) $r['title'],
                'status'    => (string) $r['status'],
                'role'      => (string) $r['role'],
                'contact'   => (string) $r['contact_name'],
                'match'     => $same[(int) $r['contact_id']] ?? 'same_contact',
            ];
        }

        return $hits;
    }

    /**
     * Verifica y, si hay coincidencias, exige una justificación para continuar.
     * Siempre deja constancia (también cuando el resultado es «sin conflicto»).
     *
     * @return Result ok = puede continuar; fail con data['conflicts'] = detenerse
     */
    public function gate(int $companyId, int $contactId, string $role, ?int $matterId, array $in, Access $acc): Result
    {
        $hits = $this->check($companyId, $contactId, $role, $matterId);
        $ack  = !empty($in['conflict_ack']);
        $note = trim((string) ($in['conflict_note'] ?? ''));

        if ($hits && (!$ack || mb_strlen($note) < 10)) {
            $this->record($companyId, $matterId, $contactId, $role, $hits, 'none', '', $acc);

            return Result::fail(lang('Lgc.errConflict', [count($hits)]), ['conflicts' => $hits]);
        }
        $this->record($companyId, $matterId, $contactId, $role, $hits, $hits ? 'proceed' : 'none', $hits ? $note : '', $acc);

        return Result::ok();
    }

    /** Asocia al asunto recién creado las verificaciones hechas antes de existir. */
    public function attach(int $companyId, int $contactId, int $matterId): void
    {
        (new ConflictCheckModel())->where('company_id', $companyId)->where('contact_id', $contactId)->where('matter_id', null)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - 600))->set(['matter_id' => $matterId])->update();
    }

    private function record(int $companyId, ?int $matterId, int $contactId, string $role, array $hits, string $decision, string $note, Access $acc): void
    {
        (new ConflictCheckModel())->insert([
            'company_id'    => $companyId,
            'matter_id'     => $matterId,
            'contact_id'    => $contactId,
            'role'          => $role,
            'result'        => $hits ? 'potential' : 'clear',
            'hits'          => $hits ? json_encode($hits, JSON_UNESCAPED_UNICODE) : null,
            'decision'      => $decision,
            'justification' => $note !== '' ? $note : null,
            'decided_by'    => $acc->userId ?: null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        if ($hits && $decision === 'proceed') {
            Audit::log($companyId, $matterId, 'conflict.overridden', 'contact', $contactId, ['hits' => count($hits), 'role' => $role], 'user', $acc->userId ?: null);
        }
    }
}
