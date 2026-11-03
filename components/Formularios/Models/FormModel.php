<?php

namespace Components\Formularios\Models;

use CodeIgniter\Model;

/**
 * Formularios de la empresa activa.
 *
 * `schema_json`, `published_schema` y `settings` viajan como ARRAY en el código
 * y como JSON en su columna TEXT: los callbacks los traducen, así que ni el
 * controlador ni las vistas ven nunca una cadena JSON.
 *
 * No se declara `is_unique` sobre `slug`: además del gotcha conocido de CI4 al
 * actualizar (el placeholder {id} no se rellena y el propio registro cuenta como
 * duplicado, y el guardado se pierde en silencio), aquí la unicidad es POR
 * EMPRESA y la resuelve `FormService::uniqueSlug()` generando el siguiente libre
 * en vez de rechazar.
 */
class FormModel extends Model
{
    /** Sin ENUM (contrato 8): el juego de valores se valida aquí. */
    public const STATUSES = ['draft', 'published', 'closed'];

    /** Las dos presentaciones del formulario público. */
    public const DISPLAYS = ['card', 'fullscreen'];

    use JsonColumns;

    protected $table            = 'cmp_frm_forms';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'company_id', 'user_id', 'title', 'slug', 'public_token', 'status',
        'schema_json', 'published_schema', 'settings', 'version', 'password_hash',
        'opens_at', 'closes_at', 'max_responses', 'allow_multiple', 'views', 'published_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'title'      => 'required|min_length[2]|max_length[180]',
        'slug'       => 'required|max_length[180]|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]',
        'status'     => 'permit_empty|in_list[draft,published,closed]',
    ];

    protected $validationMessages = [
        'title' => [
            'required'   => 'El título es obligatorio.',
            'min_length' => 'El título debe tener al menos 2 caracteres.',
        ],
        'slug' => [
            'required'    => 'La dirección del formulario es obligatoria.',
            'regex_match' => 'La dirección solo admite minúsculas, números y guiones.',
        ],
        'status' => ['in_list' => 'Estado de formulario inválido.'],
    ];

    protected array $jsonColumns = ['schema_json', 'published_schema', 'settings'];

    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
    protected $afterFind    = ['decodeJson'];

    /** El formulario público: por su token y publicado. Sin filtro de empresa a propósito. */
    public function byToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return $this->where('public_token', $token)->first();
    }

    /** Uno de la empresa activa, o null. Es la comprobación de pertenencia. */
    public function ofCompany(int $id, int $companyId): ?array
    {
        return $this->where('id', $id)->where('company_id', $companyId)->first();
    }

    /** Suma una visita sin pasar por el modelo (no toca `updated_at`). */
    public function countView(int $id): void
    {
        $this->db->table($this->table)->where('id', $id)->set('views', 'views + 1', false)->update();
    }
}
