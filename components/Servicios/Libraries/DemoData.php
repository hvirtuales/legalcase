<?php

namespace Components\Servicios\Libraries;

use Components\Servicios\Models\ClientModel;
use Components\Servicios\Models\ProductModel;
use Components\Servicios\Models\QuoteModel;
use Components\Servicios\Models\SellerModel;

/**
 * Datos de ejemplo para probar el módulo de punta a punta: maestros poblados y
 * cotizaciones en los cuatro estados, con descuentos en % y en $ para que el
 * resumen muestre cifras reales.
 *
 * Solo se generan si la empresa aún no tiene cotizaciones.
 */
class DemoData
{
    private const CLIENTS = [
        ['Comercializadora Andina S.A.S.', '900123456-1', 'Bogotá', 'Calle 100 # 15-30', 'Laura Gómez', '6013001122', 'compras@andina.example'],
        ['Inversiones del Caribe Ltda.', '901456789-2', 'Barranquilla', 'Cra 53 # 82-15', 'Andrés Support', '6053004455', 'gerencia@caribe.example'],
        ['Constructora Los Alpes', '830987654-3', 'Medellín', 'Av. El Poblado # 10-20', 'Marcela Ruiz', '6043007788', 'proyectos@alpes.example'],
    ];

    private const PRODUCTS = [
        ['SRV-001', 'Desarrollo de software a la medida', 'Análisis, desarrollo y puesta en marcha.', 8500000, '%', 0, 19],
        ['SRV-002', 'Soporte y mantenimiento mensual', 'Mesa de ayuda 8×5 con SLA de 4 horas.', 1200000, '%', 5, 19],
        ['SRV-003', 'Consultoría en procesos', 'Levantamiento y documentación de procesos.', 3200000, '%', 0, 19],
        ['PRD-001', 'Licencia anual plataforma', 'Licenciamiento por usuario, vigencia 12 meses.', 450000, '%', 10, 19],
        ['PRD-002', 'Equipo portátil corporativo', 'Core i7, 16 GB RAM, garantía 12 meses.', 3800000, '$', 100000, 19],
        ['CAP-001', 'Capacitación presencial', 'Jornada de 8 horas, hasta 20 asistentes.', 1800000, '%', 0, 0],
    ];

    private const SELLERS = [
        ['Rosalba León', 'rosalba@empresa.example', '3001112233', 'activo'],
        ['Carlos Méndez', 'carlos@empresa.example', '3004445566', 'activo'],
    ];

    /** [días atrás, estado, [ [índice producto, cantidad], ... ], retención] */
    private const QUOTES = [
        [40, 'facturada', [[0, 1], [1, 12]], 0],
        [28, 'aprobada', [[3, 25], [5, 1]], 0],
        [21, 'aprobada', [[2, 1]], 2.5],
        [14, 'pendiente', [[4, 3], [1, 6]], 0],
        [7, 'pendiente', [[0, 1], [2, 1], [5, 2]], 0],
        [3, 'rechazada', [[4, 10]], 0],
    ];

    public function isEmpty(int $companyId): bool
    {
        return (new QuoteModel())->where('company_id', $companyId)->countAllResults() === 0;
    }

    /** @return array{quotes:int,clients:int,products:int} */
    public function generate(int $companyId): array
    {
        $clients  = new ClientModel();
        $products = new ProductModel();
        $sellers  = new SellerModel();
        $service  = new QuoteService();

        $clientIds = [];

        foreach (self::CLIENTS as [$name, $nit, $city, $address, $contact, $phone, $email]) {
            $clients->insert([
                'company_id' => $companyId, 'name' => $name, 'nit' => $nit, 'city' => $city,
                'address' => $address, 'contact_name' => $contact, 'phone' => $phone, 'email' => $email,
            ]);
            $clientIds[] = (int) $clients->getInsertID();
        }

        $productRows = [];

        foreach (self::PRODUCTS as [$code, $name, $desc, $price, $dType, $dValue, $tax]) {
            $products->insert([
                'company_id' => $companyId, 'code' => $code, 'name' => $name, 'description' => $desc,
                'unit_price' => $price, 'default_quantity' => 1, 'discount_type' => $dType,
                'discount_value' => $dValue, 'tax_percent' => $tax, 'is_active' => 1,
            ]);
            $id            = (int) $products->getInsertID();
            $productRows[] = ['id' => $id, 'code' => $code, 'name' => $name, 'description' => $desc,
                'unit_price' => $price, 'discount_type' => $dType, 'discount_value' => $dValue, 'tax_percent' => $tax];
        }

        $sellerIds = [];

        foreach (self::SELLERS as [$name, $email, $phone, $status]) {
            $sellers->insert([
                'company_id' => $companyId, 'name' => $name, 'email' => $email,
                'phone' => $phone, 'status' => $status,
            ]);
            $sellerIds[] = (int) $sellers->getInsertID();
        }

        $made = 0;

        foreach (self::QUOTES as $i => [$daysAgo, $status, $lines, $retention]) {
            $items = [];

            foreach ($lines as [$pIndex, $qty]) {
                $p       = $productRows[$pIndex];
                $items[] = [
                    'product_id'     => $p['id'],
                    'code'           => $p['code'],
                    'name'           => $p['name'],
                    'description'    => $p['description'],
                    'details_url'    => '',
                    'unit_price'     => $p['unit_price'],
                    'quantity'       => $qty,
                    'discount_type'  => $p['discount_type'],
                    'discount_value' => $p['discount_value'],
                    'tax_percent'    => $p['tax_percent'],
                ];
            }

            $res = $service->save($companyId, [
                'client_id'         => $clientIds[$i % count($clientIds)],
                'seller_id'         => $sellerIds[$i % count($sellerIds)],
                'issue_date'        => date('Y-m-d', strtotime("-{$daysAgo} days")),
                'validity_days'     => 15,
                'payment_form'      => $i % 2 === 0 ? 'Contado' : 'Crédito',
                'payment_method'    => 'Transferencia bancaria',
                'currency'          => 'COP',
                'retention_percent' => $retention,
                'intro_html'        => '<p>Agradecemos la oportunidad de presentar esta propuesta. A continuación el detalle de lo solicitado.</p>',
                'terms_html'        => '<p><strong>Términos y condiciones:</strong></p><ul><li>Precios en pesos colombianos.</li><li>Forma de pago según lo acordado.</li><li>Tiempos sujetos a confirmación de agenda.</li></ul>',
                'show_detail'       => 1,
                'status'            => $status,
            ], $items);

            if ($res['ok']) {
                $made++;
            }
        }

        return ['quotes' => $made, 'clients' => count($clientIds), 'products' => count($productRows)];
    }
}
