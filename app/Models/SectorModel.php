<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Catálogo de sectores — la clasificación de las empresas.
 *
 * Es de **plataforma**: lo mantiene el Super Admin y lo usa todo el mundo.
 * Clasificar una empresa (elegir su sector) es otra cosa y va con
 * `companies.edit`.
 */
class SectorModel extends Model
{
    protected $table            = 'sectors';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'name', 'slug', 'description', 'icon', 'color', 'sort_order', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    /**
     * La validación de unicidad se hace **en el controlador** con el id real:
     * `is_unique[sectors.name,id,{id}]` no rellena el marcador en `update()`,
     * el propio registro cuenta como duplicado y el guardado se pierde en
     * silencio (mismo gotcha que en User, Role, Permission y Company).
     */
    protected $validationRules = [
        'name'  => 'required|min_length[2]|max_length[100]',
        'color' => 'permit_empty|regex_match[/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/]',
    ];

    protected $validationMessages = [
        'name'  => [
            'required'   => 'El nombre del sector es obligatorio',
            'min_length' => 'El nombre debe tener al menos 2 caracteres',
        ],
        'color' => ['regex_match' => 'El color debe ser un hexadecimal como #1a2b3c'],
    ];

    /** Los sectores que se ofrecen para clasificar, en su orden. */
    public function active(): array
    {
        return $this->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /** Todos, ordenados igual (para el catálogo y los selectores del admin). */
    public function ordered(): array
    {
        return $this->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
    }

    /** `[id => fila]`, para pintar el sector de una lista sin una consulta por fila. */
    public function map(): array
    {
        $out = [];

        foreach ($this->withDeleted()->findAll() as $s) {
            $out[(int) $s['id']] = $s;
        }

        return $out;
    }

    /**
     * ¿Cuántas empresas hay en cada sector? `[sector_id => n]`.
     *
     * Cuenta solo las vivas: si no, borrar una empresa dejaría al sector
     * pareciendo ocupado y no se podría limpiar el catálogo.
     */
    public function companyCounts(): array
    {
        $filas = $this->db->table('companies')
            ->select('sector_id, COUNT(*) AS n')
            ->where('sector_id IS NOT NULL')
            ->where('deleted_at IS NULL')
            ->groupBy('sector_id')
            ->get()
            ->getResultArray();

        $out = [];

        foreach ($filas as $f) {
            $out[(int) $f['sector_id']] = (int) $f['n'];
        }

        return $out;
    }

    /**
     * Slug único entre los sectores **vivos**.
     *
     * Se calcula aquí y no en el formulario: es un identificador que acaba en
     * una URL pública del Directorio, y dejarlo escribir invita a duplicados y
     * a acentos. `$ignore` es el id que se está editando.
     */
    public function uniqueSlug(string $name, ?int $ignore = null): string
    {
        $base = $this->slugify($name) ?: 'sector';
        $slug = $base;
        $n    = 2;

        while (true) {
            $q = $this->where('slug', $slug);
            if ($ignore) {
                $q->where('id !=', $ignore);
            }
            if (!$q->first()) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    /**
     * Mapa de transliteración explícito, no `iconv(... 'ASCII//TRANSLIT')`:
     * ese depende del locale del sistema y en Windows convierte «é» en «?e»
     * o en cadena vacía según cómo esté configurado.
     */
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
