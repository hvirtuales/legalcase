<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\TaskModel;

/**
 * Lo que hace el reloj (`php spark lgc:tick`, programado con cron o el Programador de
 * tareas; la plataforma no usa colas):
 *
 *  1. **Toca informar** (F-03): por cada plan periódico o híbrido de un asunto abierto
 *     cuya fecha comprometida llegó, crea UNA tarea `update_due` para el responsable.
 *     Idempotente por `ref = update_due:<asunto>:<fecha>`. No publica nada: propone el
 *     trabajo; el borrador «sin novedades» lo redacta y lo aprueba una persona.
 *  2. **Reintenta los avisos** pendientes o fallidos del outbox.
 *  3. **Limpia** los enlaces de acceso caducados hace más de 7 días.
 */
class Clock
{
    public function __construct(private ?Outbox $outbox = null)
    {
        $this->outbox ??= new Outbox();
    }

    /** @return array{update_due:int,sent:int,failed:int,skipped:int,tokens:int} */
    public function run(?int $companyId = null): array
    {
        $out = ['update_due' => $this->dueUpdates($companyId)];
        $out += $this->outbox->dispatch(50, $companyId);

        $db = db_connect();
        $db->table('cmp_lgc_portal_tokens')->where('expires_at <', date('Y-m-d H:i:s', strtotime('-7 days')))->delete();
        $out['tokens'] = $db->affectedRows();

        return $out;
    }

    public function dueUpdates(?int $companyId = null): int
    {
        $now = date('Y-m-d H:i:s');
        $q   = db_connect()->table('cmp_lgc_comm_plans p')
            ->select('p.*, m.number, m.lead_lawyer_id')
            ->join('cmp_lgc_matters m', 'm.id = p.matter_id')
            ->where('p.is_active', 1)
            ->whereIn('p.mode', ['periodic', 'hybrid'])
            ->where('p.next_update_due_at <=', $now)
            ->where('m.status', 'open')
            ->where('m.deleted_at', null);
        if ($companyId) {
            $q->where('p.company_id', $companyId);
        }

        $tasks   = new TaskModel();
        $created = 0;
        foreach ($q->get()->getResultArray() as $p) {
            $ref = 'update_due:' . (int) $p['matter_id'] . ':' . date('Ymd', strtotime((string) $p['next_update_due_at']));
            if ((new TaskModel())->where('company_id', (int) $p['company_id'])->where('ref', $ref)->countAllResults() > 0) {
                continue;
            }
            $tasks->insert([
                'company_id'  => (int) $p['company_id'],
                'matter_id'   => (int) $p['matter_id'],
                'kind'        => 'update_due',
                'title'       => lang('Lgc.taskUpdateDue', [$p['number']]),
                'due_at'      => $p['next_update_due_at'],
                'assigned_to' => $p['owner_id'] ?: ($p['lead_lawyer_id'] ?: null),
                'status'      => 'open',
                'ref'         => $ref,
            ]);
            $created++;
        }

        return $created;
    }

    /** Asuntos con la comunicación comprometida vencida (§6.12 HC-C09). */
    public static function overdueQuery(int $companyId)
    {
        return db_connect()->table('cmp_lgc_comm_plans p')
            ->select('p.matter_id, p.next_update_due_at, p.owner_id, p.mode, m.number, m.title, m.lead_lawyer_id, m.last_published_at, m.visibility, m.company_id, m.id')
            ->join('cmp_lgc_matters m', 'm.id = p.matter_id')
            ->where('p.company_id', $companyId)
            ->where('m.company_id', $companyId)
            ->where('p.is_active', 1)
            ->whereIn('p.mode', ['periodic', 'hybrid'])
            ->where('p.next_update_due_at <', date('Y-m-d H:i:s'))
            ->where('m.status', 'open')
            ->where('m.deleted_at', null)
            ->orderBy('p.next_update_due_at', 'ASC');
    }
}
