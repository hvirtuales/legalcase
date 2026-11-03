<?php

namespace Components\Paginas\Models;

use CodeIgniter\Model;

/**
 * Biblioteca de medios de la empresa activa.
 *
 * Todo lo que sale de aquí va acotado por `company_id`: la imagen de una empresa
 * no puede aparecer en el selector de otra (contrato §4).
 */
class MediaModel extends Model
{
    protected $table            = 'cmp_pag_media';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'user_id', 'path', 'name', 'mime', 'width', 'height', 'bytes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    /** Las de esta empresa, la última primero. */
    public function forCompany(int $companyId, string $q = '', int $limit = 0): array
    {
        $b = $this->where('company_id', $companyId);

        $q = trim($q);
        if ($q !== '') {
            $b->like('name', $q);
        }
        $b->orderBy('id', 'DESC');

        return $limit > 0 ? $b->findAll($limit) : $b->findAll();
    }

    /** Una imagen **de esta empresa**, o null. Nunca uses `find()` a secas. */
    public function findForCompany(int $id, int $companyId): ?array
    {
        return $this->where('company_id', $companyId)->find($id);
    }

    /**
     * Anota una imagen recién subida.
     *
     * Idempotente por `path`: la misma referencia no se apunta dos veces (subir
     * y volver a subir el mismo archivo produce rutas distintas, pero un
     * componente que reutilice una referencia no debe duplicar la ficha).
     */
    public function remember(int $companyId, string $path, array $extra = []): int
    {
        $ya = $this->where('company_id', $companyId)->where('path', $path)->first();
        if ($ya) {
            return (int) $ya['id'];
        }

        $this->insert(array_merge([
            'company_id' => $companyId,
            'user_id'    => (int) session()->get('user_id') ?: null,
            'path'       => $path,
        ], $extra));

        return (int) $this->getInsertID();
    }
}
