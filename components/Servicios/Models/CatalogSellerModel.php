<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

/**
 * Vendedor asignado a un catálogo. **Cada uno tiene su propia URL**: el token es
 * aleatorio (no el id) para que los enlaces no se puedan recorrer, y sirve además
 * para saber a qué vendedor entra el pedido.
 */
class CatalogSellerModel extends Model
{
    protected $table            = 'cmp_quo_catalog_sellers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = ['catalog_id', 'seller_id', 'token', 'visits'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Sincroniza los vendedores del catálogo conservando los tokens ya repartidos. */
    public function sync(int $catalogId, array $sellerIds): void
    {
        $current = array_column($this->where('catalog_id', $catalogId)->findAll(), 'id', 'seller_id');

        foreach ($sellerIds as $sellerId) {
            $sellerId = (int) $sellerId;
            if ($sellerId && !isset($current[$sellerId])) {
                $this->insert([
                    'catalog_id' => $catalogId,
                    'seller_id'  => $sellerId,
                    'token'      => bin2hex(random_bytes(16)),
                ]);
            }
            unset($current[$sellerId]);
        }

        // Los que se quitaron: su enlace deja de existir (los pedidos ya hechos
        // no se tocan, la cotización guarda el vendedor congelado).
        foreach ($current as $id) {
            $this->delete($id);
        }
    }

    /** Asignaciones del catálogo con el nombre y el teléfono del vendedor. */
    public function withSeller(int $catalogId): array
    {
        return $this->select('cmp_quo_catalog_sellers.*, s.name AS seller_name, s.phone AS seller_phone, s.status AS seller_status')
            ->join('cmp_quo_sellers s', 's.id = cmp_quo_catalog_sellers.seller_id')
            ->where('catalog_id', $catalogId)
            ->orderBy('s.name', 'asc')
            ->findAll();
    }
}
