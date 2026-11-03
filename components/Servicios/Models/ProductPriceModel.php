<?php

namespace Components\Servicios\Models;

use CodeIgniter\Model;

/**
 * Los **valores** de un producto: hasta 3 escalones (`tier` 1..3), cada uno con
 * su precio, cantidad, descuento e impuesto.
 *
 * Sin fila para un escalón, ese producto se cotiza con sus condiciones base
 * (las del propio producto). El cliente indica qué escalón se le aplica.
 */
class ProductPriceModel extends Model
{
    /** Cuántos valores admite un producto. */
    public const TIERS = [1, 2, 3];

    protected $table            = 'cmp_quo_product_prices';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'product_id', 'tier', 'label', 'unit_price', 'quantity',
        'discount_type', 'discount_value', 'tax_percent',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Valores de un producto: `[tier => fila]`. */
    public function forProduct(int $productId): array
    {
        $out = [];

        foreach ($this->where('product_id', $productId)->orderBy('tier', 'asc')->findAll() as $r) {
            $out[(int) $r['tier']] = $r;
        }

        return $out;
    }

    /** Lo mismo para varios productos: `[product_id => [tier => fila]]`. */
    public function forProducts(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $out = [];

        foreach ($this->whereIn('product_id', $productIds)->orderBy('tier', 'asc')->findAll() as $r) {
            $out[(int) $r['product_id']][(int) $r['tier']] = $r;
        }

        return $out;
    }

    /**
     * Guarda los 3 escalones que llegan del formulario.
     *
     * Un escalón **sin precio se borra**: eso significa «este producto no tiene
     * ese valor» y el cotizador usará las condiciones base. Un precio de 0 sí se
     * guarda (puede ser un producto de obsequio).
     *
     * @param array $rows [tier => ['unit_price','quantity','discount_type','discount_value','tax_percent','label']]
     */
    public function saveTiers(int $productId, array $rows): void
    {
        $current = $this->forProduct($productId);

        foreach (self::TIERS as $tier) {
            $row   = $rows[$tier] ?? [];
            $price = trim((string) ($row['unit_price'] ?? ''));

            if ($price === '') {
                if (isset($current[$tier])) {
                    $this->where('product_id', $productId)->where('tier', $tier)->delete();
                }

                continue;
            }

            $data = [
                'product_id'     => $productId,
                'tier'           => $tier,
                'label'          => mb_substr(trim((string) ($row['label'] ?? '')), 0, 60),
                'unit_price'     => (float) $price,
                'quantity'       => (float) ($row['quantity'] ?? 1) ?: 1,
                'discount_type'  => ($row['discount_type'] ?? '%') === '$' ? '$' : '%',
                'discount_value' => (float) ($row['discount_value'] ?? 0),
                'tax_percent'    => (float) ($row['tax_percent'] ?? 0),
            ];

            if (isset($current[$tier])) {
                $this->where('product_id', $productId)->where('tier', $tier)->set($data)->update();
            } else {
                $this->insert($data);
            }
        }
    }
}
