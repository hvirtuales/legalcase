<?php

namespace Components\LegalCase\Libraries;

use App\Models\CompanyModel;
use App\Models\UserModel;
use CodeIgniter\Model;
use Components\LegalCase\Models\TeamModel;

/**
 * Quién actúa y sobre qué asuntos (RBAC + ABAC del documento maestro §3).
 *
 *  · Los permisos `lgc.*` del núcleo deciden **qué** se puede hacer.
 *  · Esta clase decide **sobre qué asuntos**: un asunto `firm` lo ve todo el despacho;
 *    uno `team` solo su abogado responsable, su equipo y quien administra la empresa.
 *
 * Toda consulta de asuntos pasa por `scopeMatters()` y toda ficha por `canSeeMatter()`.
 * El filtro de empresa va SIEMPRE fuera del grupo OR (gotcha del DataTable): un OR que
 * lo envolviera dejaría ver asuntos de otro despacho.
 */
class Access
{
    public int $companyId;
    public int $userId;
    private ?bool $seesAll = null;

    /** @var array<int,bool> caché por petición de pertenencia al equipo */
    private array $teamCache = [];

    public function __construct(int $companyId, int $userId)
    {
        $this->companyId = $companyId;
        $this->userId    = $userId;
    }

    /** El usuario y la empresa de la sesión del personal. */
    public static function current(): self
    {
        return new self((int) (session('active_company_id') ?? 0), (int) (session('user_id') ?? 0));
    }

    /**
     * El sistema actuando dentro de UNA empresa (reloj, autotest, portal): ve todos los
     * asuntos de esa empresa y de ninguna otra. `userId = 0` deja el rastro como «sistema».
     */
    public static function system(int $companyId): self
    {
        $a          = new self($companyId, 0);
        $a->seesAll = true;

        return $a;
    }

    public function isSystem(): bool
    {
        return $this->userId === 0;
    }

    public function can(string $permission): bool
    {
        if ($this->isSystem()) {
            return true;
        }

        return $this->userId > 0 && (new UserModel())->hasPermission($this->userId, $permission);
    }

    /** ¿Ve todos los asuntos de la empresa? Super Admin y administradores de la empresa. */
    public function seesAll(): bool
    {
        if ($this->seesAll !== null) {
            return $this->seesAll;
        }
        if ($this->userId <= 0 || $this->companyId <= 0) {
            return $this->seesAll = false;
        }

        $u = (new UserModel())->getUserWithRoles($this->userId);
        if (in_array('Super Admin', array_column($u['roles'] ?? [], 'name'), true)) {
            return $this->seesAll = true;
        }

        return $this->seesAll = (new CompanyModel())->isManager($this->companyId, $this->userId);
    }

    public function canSeeMatter(?array $matter): bool
    {
        if (!$matter || (int) $matter['company_id'] !== $this->companyId || $this->companyId <= 0) {
            return false;
        }
        if (($matter['visibility'] ?? 'firm') === 'firm' || $this->seesAll()) {
            return true;
        }
        if ((int) ($matter['lead_lawyer_id'] ?? 0) === $this->userId) {
            return true;
        }

        $id = (int) $matter['id'];

        return $this->teamCache[$id] ??= in_array($this->userId, (new TeamModel())->userIds($id), true);
    }

    /**
     * Acota una consulta de asuntos a lo que este usuario puede ver.
     *
     * @param string $table nombre (o alias) de la tabla de asuntos en la consulta
     */
    public function scopeMatters(Model $m, string $table = 'cmp_lgc_matters'): Model
    {
        $m->where($table . '.company_id', $this->companyId);

        if ($this->seesAll()) {
            return $m;
        }

        $userId = $this->userId;
        $m->groupStart()
            ->where($table . '.visibility', 'firm')
            ->orWhere($table . '.lead_lawyer_id', $userId)
            ->orWhereIn($table . '.id', static function ($b) use ($userId) {
                return $b->select('matter_id')->from('cmp_lgc_matter_team')->where('user_id', $userId);
            })
            ->groupEnd();

        return $m;
    }
}
