<?php

namespace Components\Servicios\Libraries;

use App\Models\CompanyModel;
use Components\Servicios\Models\CatalogModel;
use Components\Servicios\Models\CatalogProductModel;
use Components\Servicios\Models\CatalogSellerModel;
use Components\Servicios\Models\ClientModel;
use Components\Servicios\Models\ProductModel;
use Components\Servicios\Models\ProductPriceModel;
use Components\Servicios\Models\QuoteModel;
use Components\Servicios\Models\SellerModel;

/**
 * Reglas del catálogo digital: qué ve el cliente y qué pasa cuando pide.
 *
 * El pedido **no tiene tabla propia**: se guarda como una **cotización** del
 * componente (estado «pendiente»), con el vendedor del enlace y el cliente que
 * se registró. Así el pedido entra al mismo flujo que todo lo demás —detalle,
 * PDF, estados— y el vendedor solo tiene que aprobarlo.
 */
class CatalogService
{
    private CatalogModel $catalogs;
    private CatalogSellerModel $assignments;
    private CatalogProductModel $picked;

    public function __construct()
    {
        $this->catalogs    = new CatalogModel();
        $this->assignments = new CatalogSellerModel();
        $this->picked      = new CatalogProductModel();
    }

    /** URL que se le comparte al cliente. */
    public function publicUrl(array $assignment): string
    {
        return site_url('servicios/catalogo/' . $assignment['token']);
    }

    /**
     * Resuelve el enlace: catálogo publicado, vendedor activo y empresa activa.
     *
     * @return array{0:?array,1:?array,2:?array,3:?array} [asignación, catálogo, vendedor, empresa]
     */
    public function resolve(string $token): array
    {
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
            return [null, null, null, null];
        }

        $assignment = $this->assignments->where('token', $token)->first();
        if (!$assignment) {
            return [null, null, null, null];
        }

        $catalog = $this->catalogs->find((int) $assignment['catalog_id']);
        if (!$catalog || empty($catalog['is_published'])) {
            return [null, null, null, null];
        }

        $seller = (new SellerModel())->find((int) $assignment['seller_id']);
        if (!$seller || ($seller['status'] ?? '') !== 'activo') {
            return [null, null, null, null];
        }

        $company = (new CompanyModel())->find((int) $catalog['company_id']);
        if (!$company || empty($company['is_active'])) {
            return [null, null, null, null];
        }

        return [$assignment, $catalog, $seller, $company];
    }

    /** Cuántos productos trae cada página de la vitrina. */
    public const PER_PAGE = 24;

    /** Tope de productos que se llevan al PDF (dompdf no aguanta miles). */
    public const PDF_MAX = 300;

    /**
     * Consulta base de la vitrina: los productos del catálogo, activos, en el
     * orden en que se armó, con el filtro de búsqueda y categoría aplicado **en
     * la base de datos**.
     *
     * Un catálogo puede tener miles de referencias: ni la vitrina ni el pedido
     * pueden traérselas todas a memoria.
     */
    private function query(array $catalog, string $q = '', ?int $categoryId = null)
    {
        $builder = \Config\Database::connect()
            ->table('cmp_quo_catalog_products cp')
            ->select('p.*, cp.sort_order')
            ->join('cmp_quo_products p', 'p.id = cp.product_id')
            ->where('cp.catalog_id', (int) $catalog['id'])
            ->where('p.company_id', (int) $catalog['company_id'])
            ->where('p.is_active', 1)
            ->where('p.deleted_at IS NULL');

        $q = trim($q);
        if ($q !== '') {
            $builder->groupStart()
                ->like('p.name', $q)
                ->orLike('p.code', $q)
                ->orLike('p.description', $q)
                ->groupEnd();
        }
        if ($categoryId === 0) {
            $builder->where('p.category_id IS NULL');
        } elseif ($categoryId !== null) {
            $builder->where('p.category_id', $categoryId);
        }

        return $builder;
    }

    /**
     * Una página de la vitrina.
     *
     * @return array{rows:list<array>,total:int,pages:int,page:int}
     */
    public function page(array $catalog, string $q = '', ?int $categoryId = null, int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?: self::PER_PAGE;
        $page    = max(1, $page);
        $total   = $this->query($catalog, $q, $categoryId)->countAllResults();

        $rows = $this->query($catalog, $q, $categoryId)
            ->orderBy('cp.sort_order', 'asc')
            ->orderBy('cp.id', 'asc')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        return [
            'rows'  => $this->decorate($catalog, $rows),
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'page'  => $page,
        ];
    }

    /** Productos concretos del catálogo (para validar el carrito al pedir). */
    public function itemsByIds(array $catalog, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }

        $rows = $this->query($catalog)->whereIn('p.id', $ids)->get()->getResultArray();

        return $this->decorate($catalog, $rows);
    }

    /**
     * Todos los productos del catálogo. **Solo para el PDF**, y acotado: la
     * vitrina web usa `page()`.
     */
    public function items(array $catalog, ?int $limit = null): array
    {
        $rows = $this->query($catalog)
            ->orderBy('cp.sort_order', 'asc')
            ->orderBy('cp.id', 'asc')
            ->limit($limit ?? self::PDF_MAX)
            ->get()->getResultArray();

        return $this->decorate($catalog, $rows);
    }

    /** Cuántos productos tiene el catálogo (sin traérselos). */
    public function count(array $catalog): int
    {
        return $this->query($catalog)->countAllResults();
    }

    /** Categorías presentes en el catálogo, para el carrusel (una sola consulta). */
    public function categories(array $catalog): array
    {
        $rows = \Config\Database::connect()
            ->table('cmp_quo_catalog_products cp')
            ->select('c.id, c.name')
            ->join('cmp_quo_products p', 'p.id = cp.product_id')
            ->join('cmp_quo_categories c', 'c.id = p.category_id')
            ->where('cp.catalog_id', (int) $catalog['id'])
            ->where('p.is_active', 1)
            ->where('p.deleted_at IS NULL')
            ->where('c.deleted_at IS NULL')
            ->groupBy('c.id, c.name')
            ->orderBy('c.name', 'asc')
            ->get()->getResultArray();

        return array_column($rows, 'name', 'id');
    }

    /** ¿Hay productos sin clasificar? (para ofrecer esa ficha en el carrusel) */
    public function hasUncategorized(array $catalog): bool
    {
        return $this->query($catalog)->where('p.category_id IS NULL')->countAllResults() > 0;
    }

    /**
     * Aplica a cada producto las condiciones del **valor** del catálogo (1..3) o,
     * si no lo define, las suyas base. Los precios de la página se piden en una
     * sola consulta.
     */
    private function decorate(array $catalog, array $products): array
    {
        if (!$products) {
            return [];
        }

        $tier  = (int) ($catalog['price_tier'] ?? 0);
        $tiers = $tier ? (new ProductPriceModel())->forProducts(array_column($products, 'id')) : [];
        $cats  = (new \Components\Servicios\Models\CategoryModel())->mapFor((int) $catalog['company_id']);
        $out   = [];

        foreach ($products as $p) {
            $t     = $tier ? ($tiers[(int) $p['id']][$tier] ?? null) : null;
            $out[] = [
                'id'             => (int) $p['id'],
                'code'           => (string) $p['code'],
                'category_id'    => (int) ($p['category_id'] ?? 0),
                'category'       => $cats[$p['category_id'] ?? 0] ?? '',
                'name'           => (string) $p['name'],
                'description'    => (string) $p['description'],
                'photo'          => $p['photo'] ?? null,
                'details_url'    => (string) $p['details_url'],
                'unit_price'     => (float) ($t['unit_price'] ?? $p['unit_price']),
                'quantity'       => (float) ($t['quantity'] ?? $p['default_quantity']) ?: 1,
                'discount_type'  => (string) ($t['discount_type'] ?? $p['discount_type']),
                'discount_value' => (float) ($t['discount_value'] ?? $p['discount_value']),
                'tax_percent'    => (float) ($t['tax_percent'] ?? $p['tax_percent']),
            ];
        }

        return $out;
    }

    /** Suma una visita al enlace del vendedor. */
    public function trackVisit(array $assignment): void
    {
        $this->assignments->update((int) $assignment['id'], ['visits' => (int) $assignment['visits'] + 1]);
    }

    /**
     * Guarda el pedido **como cotización** y devuelve el enlace de WhatsApp al
     * vendedor.
     *
     * Los precios NUNCA vienen del navegador: del carrito solo se aceptan el
     * producto y la cantidad; las condiciones se releen del catálogo.
     *
     * @param array $buyer ['name','nit','phone','email','city','address','notes']
     * @param array $cart  [product_id => cantidad]
     *
     * @return array{ok:bool,message:string,quote:?array,whatsapp:?string}
     */
    public function placeOrder(array $catalog, array $assignment, array $seller, array $company, array $buyer, array $cart): array
    {
        $companyId = (int) $catalog['company_id'];
        $guard     = new PlanGuard();

        if (!$guard->serviceEnabled($companyId)) {
            return ['ok' => false, 'message' => lang('Quo.errPlanDisabled'), 'quote' => null, 'whatsapp' => null];
        }
        $left = $guard->quotesLeft($companyId);
        if ($left !== null && $left <= 0) {
            return ['ok' => false, 'message' => lang('Quo.errPlanQuota'), 'quote' => null, 'whatsapp' => null];
        }

        $name = trim((string) ($buyer['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => lang('Quo.errOrderName'), 'quote' => null, 'whatsapp' => null];
        }

        // Solo se consultan los productos del carrito (un catálogo puede tener
        // miles) y se releen sus condiciones: del navegador solo vale la cantidad.
        $lines = [];

        foreach ($this->itemsByIds($catalog, array_keys($cart)) as $item) {
            $qty = (float) ($cart[$item['id']] ?? 0);
            if ($qty > 0) {
                $item['product_id'] = $item['id'];
                $item['quantity']   = $qty;
                $lines[]            = $item;
            }
        }

        if (!$lines) {
            return ['ok' => false, 'message' => lang('Quo.errOrderEmpty'), 'quote' => null, 'whatsapp' => null];
        }

        $clientId = $this->upsertClient($companyId, $buyer);

        $res = (new QuoteService())->save($companyId, [
            'client_id'  => $clientId,
            'seller_id'  => (int) $seller['id'],
            'status'     => 'pendiente',
            'issue_date' => date('Y-m-d'),
            'intro_html' => $this->orderNote($catalog, $buyer),
        ], $lines);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message'], 'quote' => null, 'whatsapp' => null];
        }

        $quotes = new QuoteModel();
        $quotes->skipValidation(true)->update($res['id'], ['catalog_id' => (int) $catalog['id']]);
        $quotes->skipValidation(false);

        $quote = $quotes->find($res['id']);

        return [
            'ok'       => true,
            'message'  => lang('Quo.okOrder', [$quote['number']]),
            'quote'    => $quote,
            'whatsapp' => $this->whatsappUrl($seller, $company, $quote, $lines, $buyer),
        ];
    }

    /**
     * Reusa el cliente si ya existe en la empresa (por NIT, correo o teléfono) y
     * si no lo crea: el catálogo no debe llenar el maestro de duplicados.
     */
    private function upsertClient(int $companyId, array $buyer): int
    {
        $clients = new ClientModel();
        $found   = null;

        foreach (['nit', 'email', 'phone'] as $field) {
            $value = trim((string) ($buyer[$field] ?? ''));
            if ($value !== '') {
                $found = $clients->where('company_id', $companyId)->where($field, $value)->first();
                if ($found) {
                    break;
                }
            }
        }

        $data = [
            'company_id'   => $companyId,
            'name'         => mb_substr(trim((string) $buyer['name']), 0, 200),
            'nit'          => mb_substr(trim((string) ($buyer['nit'] ?? '')), 0, 50),
            'city'         => mb_substr(trim((string) ($buyer['city'] ?? '')), 0, 100),
            'address'      => mb_substr(trim((string) ($buyer['address'] ?? '')), 0, 255),
            'contact_name' => mb_substr(trim((string) ($buyer['contact_name'] ?? $buyer['name'])), 0, 150),
            'phone'        => mb_substr(trim((string) ($buyer['phone'] ?? '')), 0, 50),
            'email'        => mb_substr(trim((string) ($buyer['email'] ?? '')), 0, 190),
        ];

        if ($found) {
            $clients->skipValidation(true)->update((int) $found['id'], array_filter($data, static fn ($v) => $v !== ''));
            $clients->skipValidation(false);

            return (int) $found['id'];
        }

        $clients->insert($data);

        return (int) $clients->getInsertID();
    }

    /** Nota que queda en la cotización: de dónde vino el pedido. */
    private function orderNote(array $catalog, array $buyer): string
    {
        $notes = trim((string) ($buyer['notes'] ?? ''));

        return '<p><strong>' . esc(lang('Quo.orderFromCatalog', [$catalog['name']])) . '</strong></p>'
            . ($notes !== '' ? '<p>' . esc($notes) . '</p>' : '');
    }

    /** Mensaje de WhatsApp al vendedor con el resumen del pedido. */
    private function whatsappUrl(array $seller, array $company, array $quote, array $lines, array $buyer): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) ($seller['phone'] ?: ($company['whatsapp'] ?? '')));
        if ($phone === '') {
            return null;
        }

        $text = lang('Quo.waOrderIntro', [$company['name']]) . "\n"
            . lang('Quo.waOrderRef', [$quote['number']]) . "\n"
            . lang('Quo.waOrderClient', [$buyer['name']]) . "\n\n";

        foreach ($lines as $l) {
            $text .= '• ' . $l['name'] . ' x ' . rtrim(rtrim(number_format($l['quantity'], 2, ',', '.'), '0'), ',') . "\n";
        }

        $text .= "\n" . lang('Quo.waOrderTotal', [number_format((float) $quote['total'], 0, ',', '.')]);

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
    }
}
