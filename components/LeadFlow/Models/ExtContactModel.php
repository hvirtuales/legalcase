<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;
use Components\LeadFlow\Models\Concerns\CompanyScoped;

/**
 * Caché local de los contactos de Docketwise.
 *
 * Existe porque la API v1 **no permite buscar contactos por email ni teléfono**:
 * para detectar un posible duplicado antes de convertir (§23) hay que traer la
 * lista y comparar aquí. El listado de la API solo devuelve `email` y
 * `lead_phone`, así que la comparación por teléfono es parcial — la pantalla de
 * conversión lo dice.
 */
class ExtContactModel extends Model
{
    use CompanyScoped;

    protected $table         = 'cmp_lfl_ext_contacts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['company_id', 'provider', 'external_id', 'first_name', 'last_name', 'email_norm', 'phone_norm', 'synced_at'];
}
