<?php

namespace Components\LegalCase\Models;

/**
 * Actualización al cliente. El ciclo (§8.5) lo hace valer `UpdateService`; el modelo
 * solo guarda. Un texto en `published` no se vuelve a editar.
 */
class UpdateModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_updates';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['company_id', 'matter_id', 'kind', 'status', 'title', 'what_happened', 'what_it_means', 'what_we_do', 'client_action', 'next_update_at', 'stage_label', 'verified_source', 'verified_at', 'version', 'supersedes_id', 'author_id', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'published_by', 'published_at', 'revoked_by', 'revoked_at', 'revoke_reason'];

    public const KINDS    = ['regular', 'no_news', 'welcome', 'hearing', 'closing'];
    public const STATUSES = ['draft', 'pending_review', 'approved', 'published', 'superseded', 'revoked'];
    /** Los únicos estados en los que el texto se puede editar. */
    public const EDITABLE = ['draft', 'pending_review'];

    protected $validationRules = [
        'kind'   => 'required|in_list[regular,no_news,welcome,hearing,closing]',
        'status' => 'required|in_list[draft,pending_review,approved,published,superseded,revoked]',
        'title'  => 'required|max_length[190]',
    ];
    protected $validationMessages = ['title' => ['required' => 'Lgc.vTitle']];

    /** Publicadas y vigentes de un asunto, la más reciente primero. */
    public function publishedFor(int $matterId): array
    {
        return $this->where('matter_id', $matterId)->where('status', 'published')
            ->orderBy('published_at', 'DESC')->findAll(100);
    }
}
