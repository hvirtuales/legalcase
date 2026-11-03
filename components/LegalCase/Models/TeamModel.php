<?php

namespace Components\LegalCase\Models;

/** Equipo interno del asunto: quién trabaja en él. Decide la visibilidad de los asuntos `team`. */
class TeamModel extends BaseLgcModel
{
    protected $table          = 'cmp_lgc_matter_team';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['company_id', 'matter_id', 'user_id', 'role', 'created_at'];

    public const ROLES = ['responsible', 'collaborator', 'assistant', 'observer'];

    protected $validationRules = ['role' => 'required|in_list[responsible,collaborator,assistant,observer]'];

    /** @return list<int> */
    public function userIds(int $matterId): array
    {
        return array_map('intval', array_column($this->where('matter_id', $matterId)->findAll(), 'user_id'));
    }
}
