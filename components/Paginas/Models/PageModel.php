<?php

namespace Components\Paginas\Models;

use CodeIgniter\Model;

/**
 * Páginas construidas por bloques.
 *
 * `blocks` y `settings` viajan como **array** en el código y como JSON en la
 * columna TEXT: los callbacks `beforeInsert`/`beforeUpdate` serializan y
 * `afterFind` deserializa, así ni el controlador ni las vistas ven nunca una
 * cadena JSON.
 *
 * No se declara `is_unique` sobre `slug`: además del gotcha conocido de CI4 al
 * actualizar (el `{id}` no se rellena y el propio registro cuenta como
 * duplicado), la unicidad aquí es **por empresa** y la resuelve
 * `PageService::uniqueSlug()` generando el siguiente libre en vez de rechazar.
 */
class PageModel extends Model
{
    use JsonColumns;

    public const STATUSES = ['draft', 'published'];

    protected $table            = 'cmp_pag_pages';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'user_id', 'title', 'slug', 'status', 'blocks', 'settings',
        'meta_title', 'meta_description', 'og_image', 'views', 'published_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id'       => 'required|is_natural_no_zero',
        'title'            => 'required|min_length[2]|max_length[180]',
        'slug'             => 'required|max_length[180]|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]',
        'status'           => 'permit_empty|in_list[draft,published]',
        'meta_title'       => 'permit_empty|max_length[180]',
        'meta_description' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'title' => [
            'required'   => 'El título es obligatorio.',
            'min_length' => 'El título debe tener al menos 2 caracteres.',
        ],
        'slug' => [
            'required'     => 'La dirección de la página es obligatoria.',
            'regex_match'  => 'La dirección solo admite minúsculas, números y guiones.',
        ],
        'status' => ['in_list' => 'Estado de página inválido.'],
    ];

    /** Columnas que guardan un árbol/objeto JSON en TEXT (portabilidad §8). */
    protected array $jsonColumns = ['blocks', 'settings'];

    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
    protected $afterFind    = ['decodeJson'];

    /** La página pública: de esta empresa, con este slug y publicada. */
    public function published(int $companyId, string $slug): ?array
    {
        return $this->where('company_id', $companyId)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();
    }

    /** Suma una visita sin pasar por el modelo (no toca `updated_at`). */
    public function countView(int $id): void
    {
        $this->db->table($this->table)->where('id', $id)->set('views', 'views + 1', false)->update();
    }
}
