<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

/** Productos escogidos para un catálogo (y en qué orden se muestran). */
class CatalogProductModel extends Model
{
    protected $table            = 'cmp_quo_catalog_products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $protectFields    = true;

    protected $allowedFields = ['catalog_id', 'product_id', 'sort_order'];

    /** Ids de los productos del catálogo, en orden. */
    public function idsFor(int $catalogId): array
    {
        $rows = $this->where('catalog_id', $catalogId)->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->findAll();

        return array_map('intval', array_column($rows, 'product_id'));
    }

    /** Deja el catálogo exactamente con los productos indicados, en ese orden. */
    public function sync(int $catalogId, array $productIds): void
    {
        $this->where('catalog_id', $catalogId)->delete();

        $order = 0;

        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId) {
                $this->insert(['catalog_id' => $catalogId, 'product_id' => $productId, 'sort_order' => $order++]);
            }
        }
    }
}
