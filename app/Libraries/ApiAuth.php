<?php

namespace App\Libraries;

/**
 * Contexto de autenticación de la API para el request actual. Lo llena el
 * ApiAuthFilter tras validar el JWT y lo leen los controladores de la API.
 * Se registra como servicio compartido (singleton por request).
 */
class ApiAuth
{
    private ?int $userId    = null;
    private array $roleIds   = [];
    private array $perms     = [];
    private array $user      = [];
    private ?int $companyId  = null;
    private array $company   = [];

    public function setContext(int $userId, array $roleIds, array $permissions, array $user = [], ?int $companyId = null, array $company = []): void
    {
        $this->userId    = $userId;
        $this->roleIds   = $roleIds;
        $this->perms     = $permissions;
        $this->user      = $user;
        $this->companyId = $companyId;
        $this->company   = $company;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function company(): array
    {
        return $this->company;
    }

    /** ¿El token está acotado a una empresa? */
    public function scopedToCompany(): bool
    {
        return $this->companyId !== null;
    }

    public function check(): bool
    {
        return $this->userId !== null;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function user(): array
    {
        return $this->user;
    }

    public function roleIds(): array
    {
        return $this->roleIds;
    }

    public function permissions(): array
    {
        return $this->perms;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->perms, true);
    }
}
