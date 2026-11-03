<?php

namespace Components\LegalCase\Models;

/** Mensajes de un hilo. Inmutables: no hay edición ni borrado desde la aplicación. */
class MessageModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_messages';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['company_id', 'matter_id', 'thread_id', 'direction', 'body', 'author_user_id', 'author_account_id', 'is_auto', 'created_at'];

    public function forThread(int $threadId): array
    {
        return $this->where('thread_id', $threadId)->orderBy('created_at', 'ASC')->orderBy('id', 'ASC')->findAll(500);
    }
}
