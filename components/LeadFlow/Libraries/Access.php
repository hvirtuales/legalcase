<?php

namespace Components\LeadFlow\Libraries;

use Components\LeadFlow\Models\MemberModel;

/**
 * Sobre QUÉ leads puede actuar el usuario en sesión (§6).
 *
 * Los permisos `lfl.*` dicen QUÉ se puede hacer; esto dice SOBRE QUÉ. Son dos
 * preguntas distintas y las dos se contestan en el servidor:
 *
 *  · Super Admin, dueño o admin de la empresa, y **supervisor** → todos los leads.
 *  · **Auditor** → todos, solo lectura (`canWrite` falso).
 *  · **Asesor** → los que tiene asignados y los que no tiene nadie (la bandeja
 *    «Sin asignar» es de donde se los lleva).
 *  · **Abogado** → los que tiene como abogado o asignados.
 *  · Sin función comercial → ninguno. Tener el permiso sin función es un error de
 *    configuración, y la pantalla lo dice en vez de enseñar una lista vacía.
 */
class Access
{
    public int $companyId;
    public int $userId;
    public ?string $role;
    public string $scope;      // all|own|lawyer|none
    public bool $canWrite;
    public bool $isManager;
    public bool $isSuper;

    private static array $cache = [];

    private function __construct()
    {
    }

    public static function current(): self
    {
        return self::for((int) (session('active_company_id') ?? 0), (int) (session('user_id') ?? 0));
    }

    public static function for(int $companyId, int $userId): self
    {
        $key = $companyId . ':' . $userId;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $a            = new self();
        $a->companyId = $companyId;
        $a->userId    = $userId;
        $a->isSuper   = $userId > 0 && self::isSuperAdmin($userId);
        $a->isManager = $companyId > 0 && $userId > 0 && (new \App\Models\CompanyModel())->isManager($companyId, $userId);
        $a->role      = ($companyId > 0 && $userId > 0) ? (new MemberModel())->roleOf($companyId, $userId) : null;

        if ($companyId <= 0 || $userId <= 0) {
            $a->scope = 'none';
        } elseif ($a->isSuper || $a->isManager || $a->role === 'supervisor' || $a->role === 'auditor') {
            $a->scope = 'all';
        } elseif ($a->role === 'advisor') {
            $a->scope = 'own';
        } elseif ($a->role === 'lawyer') {
            $a->scope = 'lawyer';
        } else {
            $a->scope = 'none';
        }

        // El auditor lee todo y no escribe nada. Super Admin y administradores escriben
        // aunque tengan además la función de auditor asignada.
        $a->canWrite = $a->scope !== 'none' && ! ($a->role === 'auditor' && ! $a->isSuper && ! $a->isManager);

        return self::$cache[$key] = $a;
    }

    /**
     * El acceso del SISTEMA: lo usan las automatizaciones, que actúan en nombre de
     * la firma y no de una persona. Ve y escribe todo dentro de esa empresa —y solo
     * de esa—, sin suplantar a ningún usuario (`userId = 0`, así la línea de tiempo
     * lo registra como del sistema).
     */
    public static function system(int $companyId): self
    {
        $a            = new self();
        $a->companyId = $companyId;
        $a->userId    = 0;
        $a->isSuper   = false;
        $a->isManager = true;
        $a->role      = null;
        $a->scope     = 'all';
        $a->canWrite  = true;

        return $a;
    }

    /** Para pruebas y CLI: olvida lo resuelto. */
    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function isSuperAdmin(int $userId): bool
    {
        $u = (new \App\Models\UserModel())->getUserWithRoles($userId);

        return in_array('Super Admin', array_column($u['roles'] ?? [], 'name'), true);
    }

    /**
     * ¿Puede verificar perfiles del directorio?
     *
     * **Super Admin siempre, y además quien tenga `lfl.verify`.** La verificación es
     * una afirmación de la PLATAFORMA sobre una firma —«comprobamos su licencia»—, no
     * de la firma sobre sí misma, así que no puede colgar de `lfl.configure`, que lo
     * tiene el administrador de cualquier firma.
     *
     * Al ser un permiso y no un rol escrito en el código, la plataforma puede crear un
     * rol «Verificador» con esto y nada más, sin que el componente tenga que saber cómo
     * se llama ese rol. Ojo: el instalador del núcleo concede los permisos de todo
     * componente a «Super Admin» Y a «Admin»; si no se quiere que Admin verifique, hay
     * que retirárselo en Roles.
     */
    public static function canVerify(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return self::isSuperAdmin($userId)
            || (new \App\Models\UserModel())->hasPermission($userId, 'lfl.verify');
    }

    /** ¿Ve todos los leads de la firma (y puede asignar a otros)? */
    public function seesAll(): bool
    {
        return $this->scope === 'all';
    }

    public function canAssignOthers(): bool
    {
        return $this->scope === 'all' && $this->canWrite;
    }

    /**
     * Acota una consulta de leads a lo que el usuario puede ver.
     *
     * Siempre en un grupo: el filtro de visibilidad **nunca** puede quedar suelto
     * junto a un `orWhere` de búsqueda, o dejaría pasar leads ajenos.
     *
     * @param \CodeIgniter\Model|\CodeIgniter\Database\BaseBuilder $b
     */
    public function scope($b, string $t = 'cmp_lfl_leads'): void
    {
        $b->where($t . '.company_id', $this->companyId);

        switch ($this->scope) {
            case 'all':
                return;

            case 'own':
                $b->groupStart()
                    ->where($t . '.assigned_user_id', $this->userId)
                    ->orWhere($t . '.assigned_user_id', null)
                    ->groupEnd();

                return;

            case 'lawyer':
                $b->groupStart()
                    ->where($t . '.lawyer_user_id', $this->userId)
                    ->orWhere($t . '.assigned_user_id', $this->userId)
                    ->groupEnd();

                return;

            default:
                $b->where($t . '.id', 0);
        }
    }

    public function canSee(?array $lead): bool
    {
        if (! $lead || (int) $lead['company_id'] !== $this->companyId) {
            return false;
        }

        return match ($this->scope) {
            'all'    => true,
            'own'    => empty($lead['assigned_user_id']) || (int) $lead['assigned_user_id'] === $this->userId,
            'lawyer' => (int) ($lead['lawyer_user_id'] ?? 0) === $this->userId || (int) ($lead['assigned_user_id'] ?? 0) === $this->userId,
            default  => false,
        };
    }

    public function canEdit(?array $lead): bool
    {
        return $this->canWrite && $this->canSee($lead);
    }
}
