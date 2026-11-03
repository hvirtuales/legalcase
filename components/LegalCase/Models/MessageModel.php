<?php

namespace Components\LegalCase\Models;

/** Mensajes de un hilo. Inmutables: no hay edición ni borrado desde la aplicación. */
class MessageModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_messages';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['company_id', 'matter_id', 'thread_id', 'direction', 'body', 'author_user_id', 'author_account_id', 'is_auto', 'created_at', 'status', 'reviewed_by', 'reviewed_at'];

    public const STATUSES = ['sent', 'pending_review', 'discarded'];

    /** Lo que ve el cliente: solo mensajes enviados (nunca borradores en revisión ni descartados). */
    public function sentForThread(int $threadId): array
    {
        return $this->where('thread_id', $threadId)->where('status', 'sent')->orderBy('created_at', 'ASC')->orderBy('id', 'ASC')->findAll(500);
    }

    public function forThread(int $threadId): array
    {
        return $this->where('thread_id', $threadId)->where('status !=', 'discarded')->orderBy('created_at', 'ASC')->orderBy('id', 'ASC')->findAll(500);
    }
}
