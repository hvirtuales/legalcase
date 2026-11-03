<?php

namespace Components\Pqrsf\Models;

use CodeIgniter\Model;

/** Adjuntos de la solicitud (evidencias). Los archivos viven fuera de `public/`. */
class AttachmentModel extends Model
{
    protected $table            = 'cmp_pqr_attachments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['owner_type', 'owner_id', 'name', 'path', 'mime', 'size', 'is_public', 'user_id', 'created_at'];

    protected $useTimestamps = false;

    public function listFor(string $ownerType, int $ownerId): array
    {
        return $this->where('owner_type', $ownerType)->where('owner_id', $ownerId)->orderBy('id', 'asc')->findAll();
    }

    /** Solo lo que la empresa decidió compartir con el ciudadano. */
    public function publicFor(string $ownerType, int $ownerId): array
    {
        return $this->where('owner_type', $ownerType)->where('owner_id', $ownerId)
            ->where('is_public', 1)->orderBy('id', 'asc')->findAll();
    }
}
