<?php

namespace Components\LegalCase\Controllers;

use App\Libraries\DataTable;
use Components\LegalCase\Libraries\MessageService;
use Components\LegalCase\Libraries\Team;
use Components\LegalCase\Models\MessageModel;
use Components\LegalCase\Models\ThreadModel;

/** Bandeja unificada de consultas de clientes, con estado y SLA (§6.8). */
class Inbox extends BaseLgc
{
    public function index()
    {
        $acc    = $this->acc();
        $status = (string) $this->request->getGet('estado');
        $late   = (string) $this->request->getGet('vencidas') === '1';

        $dt = (new DataTable(new ThreadModel(), $this->request))
            ->searchable(['cmp_lgc_threads.subject', 'm.number'])
            ->sortable(['last_message_at', 'first_response_due_at', 'status'])
            ->defaultSort('last_message_at', 'desc')
            ->query(static function ($t) use ($acc, $status, $late) {
                $t->select('cmp_lgc_threads.*, m.number AS matter_number, m.title AS matter_title')
                    ->join('cmp_lgc_matters m', 'm.id = cmp_lgc_threads.matter_id')
                    ->where('cmp_lgc_threads.company_id', $acc->companyId)
                    ->where('m.deleted_at', null);
                // Solo hilos de asuntos que el usuario puede ver.
                $acc->scopeMatters($t, 'm');
                if (in_array($status, ThreadModel::STATUSES, true)) {
                    $t->where('cmp_lgc_threads.status', $status);
                } elseif ($status === '') {
                    $t->whereIn('cmp_lgc_threads.status', ThreadModel::OPEN);
                }
                if ($late) {
                    $t->where('cmp_lgc_threads.first_response_at', null)
                        ->where('cmp_lgc_threads.first_response_due_at <', date('Y-m-d H:i:s'));
                }

                return $t;
            })
            ->paginate(20);

        return view('Components\LegalCase\Views\inbox\index', [
            'title' => lang('Lgc.inbox'), 'dt' => $dt, 'filters' => ['estado' => $status, 'vencidas' => $late ? '1' : ''],
            'users' => Team::users($this->companyId()),
        ]);
    }

    public function show($id = null)
    {
        [$thread, $matter] = $this->findChild(ThreadModel::class, $id);
        if (!$thread) {
            return $this->notFound('legalcase/mensajes');
        }

        return view('Components\LegalCase\Views\inbox\show', [
            'title'    => $thread['subject'],
            'thread'   => $thread,
            'matter'   => $matter,
            'messages' => (new MessageModel())->forThread((int) $thread['id']),
            'users'    => Team::users($this->companyId()),
        ]);
    }

    public function start($matterId = null)
    {
        $matter = $this->findMatter($matterId);
        if (!$matter) {
            return $this->notFound();
        }
        $r = (new MessageService())->staffStarts($matter, (string) $this->post('subject'), (string) $this->post('body'), $this->acc());

        return $this->done($r, $r->ok ? 'legalcase/mensajes/hilo/' . $r->id : $this->matterUrl($matter, 'mensajes'));
    }

    public function reply($id = null)
    {
        [$thread, $matter] = $this->findChild(ThreadModel::class, $id);
        if (!$thread) {
            return $this->notFound('legalcase/mensajes');
        }
        $r = (new MessageService())->staffReplies($matter, $thread, (string) $this->post('body'), (string) $this->post('next_status', 'answered'), $this->acc());

        return $this->done($r, 'legalcase/mensajes/hilo/' . (int) $thread['id']);
    }

    public function status($id = null)
    {
        [$thread] = $this->findChild(ThreadModel::class, $id);
        if (!$thread) {
            return $this->notFound('legalcase/mensajes');
        }
        $in = array_intersect_key((array) $this->request->getPost(), array_flip(['status', 'assigned_to', 'priority']));

        return $this->done((new MessageService())->setThread($thread, $in, $this->acc()), 'legalcase/mensajes/hilo/' . (int) $thread['id']);
    }
}
