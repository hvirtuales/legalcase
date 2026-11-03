<?php

namespace Components\Directorio\Models;

use CodeIgniter\Model;

/**
 * Una **vista** del directorio (en el código, `board` — ver la migración).
 *
 * Todo lo que sale de aquí va acotado por `tenant_id`: el id llega por la URL y
 * sin el filtro se vería la vista de otro grupo (contrato tenant §4).
 */
class BoardModel extends Model
{
    protected $table            = 'cmp_dir_boards';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'tenant_id', 'title', 'slug', 'intro', 'mode', 'layout', 'sector_id',
        'center_lat', 'center_lng', 'zoom', 'sort_order', 'is_published', 'views',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    /** Modos admitidos. VARCHAR + validación aquí, no ENUM (portabilidad §8). */
    public const MODES = ['sector', 'manual'];

    /**
     * Qué enseña la vista. `both` es el de siempre; los otros dos existen
     * porque no toda vista quiere mapa —ni todo mapa quiere lista al lado—.
     */
    public const LAYOUTS = ['both', 'list', 'map'];

    protected $validationRules = [
        'tenant_id' => 'required|is_natural_no_zero',
        'title'     => 'required|min_length[2]|max_length[150]',
    ];

    protected $validationMessages = [
        'title' => ['required' => 'El título de la vista es obligatorio'],
    ];

    /** Una vista **de este grupo**, o null. Nunca uses `find()` a secas. */
    public function findForTenant(int $id, int $tenantId): ?array
    {
        return $this->where('tenant_id', $tenantId)->find($id);
    }

    /** Las vistas del grupo, en su orden. */
    public function forTenant(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('title', 'ASC')
            ->findAll();
    }

    /** Una vista publicada, por su slug dentro del grupo. */
    public function publishedBySlug(int $tenantId, string $slug): ?array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->where('is_published', 1)
            ->first();
    }

    /** Las publicadas del grupo (índice público). */
    public function publishedFor(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('is_published', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('title', 'ASC')
            ->findAll();
    }

    /**
     * Slug único **dentro del grupo**: la URL pública lleva el slug del tenant
     * delante, así que dos grupos pueden tener cada uno su vista «gastronomia».
     */
    public function uniqueSlug(string $title, int $tenantId, ?int $ignore = null): string
    {
        $base = $this->slugify($title) ?: 'vista';
        $slug = $base;
        $n    = 2;

        while (true) {
            $q = $this->where('tenant_id', $tenantId)->where('slug', $slug);
            if ($ignore) {
                $q->where('id !=', $ignore);
            }
            if (!$q->first()) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    /** Mapa explícito, no `iconv` (depende del locale y en Windows falla). */
    private function slugify(string $text): string
    {
        $de = ['á','à','ä','â','ã','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','õ','ú','ù','ü','û','ñ','ç','Á','À','Ä','Â','Ã','É','È','Ë','Ê','Í','Ì','Ï','Î','Ó','Ò','Ö','Ô','Õ','Ú','Ù','Ü','Û','Ñ','Ç'];
        $a  = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','n','c','a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','n','c'];

        $t = str_replace($de, $a, $text);
        $t = strtolower(trim($t));
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';

        return trim($t, '-');
    }
}
