<?php

namespace Components\Marketplace\Models;

use CodeIgniter\Model;

class TagModel extends Model
{
    protected $table            = 'cmp_mkt_tags';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['name', 'slug', 'created_at'];

    protected $useTimestamps = false; // solo created_at, se fija al insertar

    protected $validationRules = [
        'name' => 'required|max_length[60]',
        'slug' => 'required|max_length[80]',
    ];

    /**
     * Normaliza una etiqueta a su slug: minúsculas, sin acentos ni signos.
     * Así «Diseño Web», «diseño web» y «DISEÑO-WEB» son la misma etiqueta.
     */
    public static function slugify(string $name): string
    {
        $s = trim(mb_strtolower($name, 'UTF-8'));
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c',
        ]);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);

        return trim((string) $s, '-');
    }

    /** Devuelve el id de la etiqueta, creándola si no existía. */
    public function idFor(string $name): ?int
    {
        $name = trim($name);
        $slug = self::slugify($name);
        if ($slug === '' || mb_strlen($slug) > 80) {
            return null;
        }

        $row = $this->where('slug', $slug)->first();
        if ($row) {
            return (int) $row['id'];
        }

        $this->insert([
            'name'       => mb_substr($name, 0, 60),
            'slug'       => $slug,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->getInsertID();
    }
}
