<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;

/**
 * Áreas de práctica del directorio. **Tabla global, sin `company_id`** (ver la
 * migración `CreateLflDirectoryTables`): son el vocabulario con el que busca el
 * visitante, y un vocabulario por firma no se puede filtrar.
 *
 * Las 12 iniciales son las del §9 del insumo de contenidos. El `code` es estable
 * —lo usan las claves de idioma y las semillas— y el `slug` es lo que viaja en la
 * URL de la landing por área.
 */
class AreaModel extends Model
{
    /** code => [slug, orden]. El nombre visible sale de `Lfl.area_<code>` al sembrar. */
    public const SYSTEM = [
        'immigration'    => ['inmigracion', 10],
        'family'         => ['familia', 20],
        'personalinjury' => ['lesiones-personales', 30],
        'labor'          => ['laboral', 40],
        'corporate'      => ['corporativo', 50],
        'contracts'      => ['contratos', 60],
        'realestate'     => ['bienes-raices', 70],
        'criminal'       => ['defensa-penal', 80],
        'civil'          => ['tramites-civiles', 90],
        'business'       => ['negocios', 100],
        'ip'             => ['propiedad-intelectual', 110],
        'commercial'     => ['asuntos-comerciales', 120],
    ];

    protected $table         = 'cmp_lfl_areas';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'slug', 'name', 'sort', 'is_active'];

    protected $validationRules = [
        'code' => 'required|max_length[40]',
        'slug' => 'required|max_length[60]',
        'name' => 'required|max_length[120]',
    ];
    protected $validationMessages = [
        'name' => ['required' => 'Lfl.vName'],
    ];

    /** @return array<int,array> todas las activas, ordenadas */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('sort', 'asc')->orderBy('name', 'asc')->findAll();
    }

    /** @return array<int,string> id => nombre */
    public function options(): array
    {
        return array_column($this->active(), 'name', 'id');
    }

    public function bySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->where('is_active', 1)->first();
    }

    public function byCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }
}
