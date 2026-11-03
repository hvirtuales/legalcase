<?php

namespace Components\LegalCase\Libraries;

use App\Models\CompanyModel;
use App\Models\UserModel;
use CodeIgniter\Model;
use Components\LegalCase\Models\SupportGrantModel;
use Components\LegalCase\Models\TeamModel;

/**
 * Quién actúa y sobre qué asuntos (RBAC + ABAC del documento maestro §3).
 *
 * Tres niveles, de menos a más:
 *
 *  1. **Acceso a la empresa** (`hasCompanyAccess`): ser dueño o miembro del despacho, o
 *     tener un acceso de SOPORTE vigente concedido por el despacho. El Super Admin de la
 *     plataforma NO entra por defecto (§3: la plataforma no lee datos jurídicos).
 *  2. **Ver un asunto** (`canSeeMatter`): `firm` lo ve todo el despacho; `team`, solo el
 *     responsable, su equipo y quien administra la empresa. Un acceso de soporte ve, no escribe.
 *  3. **Trabajar un asunto** (`canWorkOn`): solo el responsable, el equipo (salvo el papel
 *     «observador») y quien administra la empresa. Un abogado del despacho ajeno al asunto
 *     lo consulta pero no actúa sobre él.
 *
 * Además, `isInsider()` decide quién ve lo PRIVILEGIADO (hechos, objetivos, estrategia y
 * documentos privilegiados): el equipo del asunto y quien administra la empresa
 * («necesidad de conocer»). Un auditor con `lgc.view` ve el asunto, no su estrategia.
 *
 * El filtro de empresa va SIEMPRE fuera del grupo OR.
 */
class Access
{
    public int $companyId;
    public int $userId;
    private ?bool $manager = null;
    private ?bool $member = null;
    private ?array $support = null;
    private bool $supportResolved = false;
    private bool $system = false;

    /** @var array<int,?string> rol en el equipo por asunto (null = no está) */
    private array $teamRole = [];

    public function __construct(int $companyId, int $userId)
    {
        $this->companyId = $companyId;
        $this->userId    = $userId;
    }

    public static function current(): self
    {
        return new self((int) (session('active_company_id') ?? 0), (int) (session('user_id') ?? 0));
    }

    /** El sistema dentro de UNA empresa (reloj, autotest, portal). `userId = 0`. */
    public static function system(int $companyId): self
    {
        $a         = new self($companyId, 0);
        $a->system = true;

        return $a;
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    public function can(string $permission): bool
    {
        if ($this->system) {
            return true;
        }

        return $this->userId > 0 && (new UserModel())->hasPermission($this->userId, $permission);
    }

    /** Dueño o miembro del despacho. */
    public function isMember(): bool
    {
        if ($this->system) {
            return true;
        }

        return $this->member ??= Team::isMember($this->companyId, $this->userId);
    }

    /** Administra la empresa (dueño o miembro administrador). */
    public function isManager(): bool
    {
        if ($this->system) {
            return true;
        }
        if ($this->userId <= 0 || $this->companyId <= 0) {
            return false;
        }

        return $this->manager ??= (new CompanyModel())->isManager($this->companyId, $this->userId);
    }

    /** Acceso de soporte vigente concedido por el despacho (o null). */
    public function supportGrant(): ?array
    {
        if (!$this->supportResolved) {
            $this->supportResolved = true;
            $this->support = $this->userId > 0 && $this->companyId > 0
                ? (new SupportGrantModel())->activeFor($this->companyId, $this->userId)
                : null;
        }

        return $this->support;
    }

    public function isSupport(): bool
    {
        return !$this->isMember() && $this->supportGrant() !== null;
    }

    public function hasCompanyAccess(): bool
    {
        return $this->companyId > 0 && ($this->isMember() || $this->supportGrant() !== null);
    }

    /** ¿Ve todos los asuntos de la empresa? Administradores y soporte autorizado (solo lectura). */
    public function seesAll(): bool
    {
        return $this->system || $this->isManager() || $this->isSupport();
    }

    /** Papel del usuario en el equipo del asunto, o null. */
    public function teamRole(array $matter): ?string
    {
        $id = (int) $matter['id'];
        if (!array_key_exists($id, $this->teamRole)) {
            $row = (new TeamModel())->where('matter_id', $id)->where('user_id', $this->userId)->first();
            $this->teamRole[$id] = $row['role'] ?? null;
        }

        return $this->teamRole[$id];
    }

    private function sameCompany(?array $matter): bool
    {
        return $matter && $this->companyId > 0 && (int) $matter['company_id'] === $this->companyId;
    }

    public function canSeeMatter(?array $matter): bool
    {
        if (!$this->sameCompany($matter) || !$this->hasCompanyAccess()) {
            return false;
        }
        if (($matter['visibility'] ?? 'firm') === 'firm' || $this->seesAll()) {
            return true;
        }

        return (int) ($matter['lead_lawyer_id'] ?? 0) === $this->userId || $this->teamRole($matter) !== null;
    }

    /** Equipo del asunto: ve lo privilegiado. */
    public function isInsider(?array $matter): bool
    {
        if (!$this->sameCompany($matter)) {
            return false;
        }
        if ($this->system || ($this->isMember() && $this->isManager())) {
            return true;
        }
        if (!$this->isMember()) {
            return false; // el soporte nunca ve lo privilegiado
        }

        return (int) ($matter['lead_lawyer_id'] ?? 0) === $this->userId || $this->teamRole($matter) !== null;
    }

    /** Puede actuar sobre el asunto (cualquier escritura). */
    public function canWorkOn(?array $matter): bool
    {
        if (!$this->sameCompany($matter) || !$this->isMember()) {
            return $this->system && $this->sameCompany($matter);
        }
        if ($this->system || $this->isManager() || (int) ($matter['lead_lawyer_id'] ?? 0) === $this->userId) {
            return true;
        }
        $role = $this->teamRole($matter);

        return $role !== null && $role !== 'observer';
    }

    /** Acota una consulta de asuntos a lo que este usuario puede ver. */
    public function scopeMatters(Model $m, string $table = 'cmp_lgc_matters'): Model
    {
        $m->where($table . '.company_id', $this->companyId);

        if (!$this->hasCompanyAccess()) {
            return $m->where($table . '.id', 0);
        }
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
