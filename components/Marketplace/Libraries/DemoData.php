<?php

namespace Components\Marketplace\Libraries;

use Components\Marketplace\Models\CategoryModel;
use Components\Marketplace\Models\InterestModel;
use Components\Marketplace\Models\ListingModel;

/**
 * Datos de ejemplo para probar el catálogo: publicaciones variadas que cubren los
 * cuatro tipos, los tres modos de precio, varios estados, categorías y etiquetas.
 *
 * Se crean para la empresa indicada y **solo si aún no tiene publicaciones**, para
 * no ensuciar datos reales. Se borran como cualquier otra publicación.
 */
class DemoData
{
    /**
     * [tipo, título, resumen, categoría (slug), etiquetas, ciudad, price_type,
     *  importe, estado, vigencia en días (null = sin límite), vistas]
     */
    private const LISTINGS = [
        ['service', 'Desarrollo de software a la medida', 'Equipo senior en PHP, JavaScript y bases de datos.',
            'tecnologia-software', 'software, desarrollo web, integración', 'Bogotá', 'from', 5000000, 'published', null, 42],
        ['service', 'Soporte técnico y mesa de ayuda', 'Atención remota 8×5 con acuerdos de nivel de servicio.',
            'tecnologia-software', 'soporte, help desk, infraestructura', 'Medellín', 'from', 1200000, 'published', null, 17],
        ['product', 'Licencias de software de gestión', 'Licenciamiento anual con implementación incluida.',
            'tecnologia-software', 'licencias, erp, software', 'Bogotá', 'fixed', 3500000, 'published', 90, 31],
        ['product', 'Dotación corporativa y uniformes', 'Confección propia, entrega en todo el país.',
            'comercio-distribucion', 'dotación, uniformes, textil', 'Bucaramanga', 'from', 85000, 'published', null, 8],
        ['service', 'Outsourcing contable y tributario', 'Cierre mensual, medios magnéticos y NIIF.',
            'contabilidad-finanzas', 'contabilidad, niif, tributaria', 'Bogotá', 'from', 900000, 'published', null, 55],
        ['service', 'Asesoría jurídica empresarial', 'Contratos, laboral y propiedad intelectual.',
            'legal', 'legal, contratos, laboral', 'Cali', 'quote', 0, 'published', null, 12],
        ['service', 'Campañas de marketing digital', 'Pauta, contenidos y analítica mensual.',
            'marketing-publicidad', 'marketing, redes sociales, pauta', 'Medellín', 'from', 2500000, 'published', 60, 23],
        ['product', 'Equipos de cómputo reacondicionados', 'Portátiles con garantía de 12 meses.',
            'tecnologia-software', 'hardware, portátiles, equipos', 'Bogotá', 'fixed', 1800000, 'published', null, 19],

        ['need', 'Necesitamos proveedor de logística nacional', 'Envíos diarios a 5 ciudades principales.',
            'logistica-transporte', 'logística, transporte, mensajería', 'Bogotá', 'quote', 0, 'published', 45, 27],
        ['need', 'Buscamos contratista para adecuación de oficinas', '350 m², obra blanca y redes eléctricas.',
            'construccion-inmuebles', 'construcción, remodelación, oficinas', 'Cali', 'quote', 0, 'published', 30, 14],
        ['opportunity', 'Alianza para distribución en el eje cafetero', 'Buscamos distribuidor con fuerza comercial propia.',
            'comercio-distribucion', 'alianza, distribución, comercial', 'Pereira', 'quote', 0, 'published', null, 36],
        ['opportunity', 'Coinversión en planta de procesamiento', 'Proyecto agroindustrial con estudios listos.',
            'agroindustria', 'inversión, agroindustria, alianza', 'Villavicencio', 'quote', 0, 'published', null, 9],

        // Para ver los filtros de estado en «Mis publicaciones»
        ['product', 'Catálogo mayorista temporada 2026', 'Pendiente de revisión de precios.',
            'comercio-distribucion', 'mayorista, catálogo', 'Bogotá', 'quote', 0, 'draft', null, 0],
        ['service', 'Capacitación en seguridad y salud en el trabajo', 'Programa anual certificado.',
            'educacion-formacion', 'capacitación, sst, formación', 'Bogotá', 'fixed', 1500000, 'paused', null, 6],
    ];

    /** Mensajes de los interesados de ejemplo. */
    private const INTERESTS = [
        'Estamos evaluando proveedores para un proyecto que arranca el próximo trimestre. ¿Podemos agendar una llamada?',
        'Nos interesa mucho su propuesta. ¿Manejan descuentos por volumen o contratos anuales?',
    ];

    /** ¿La empresa ya tiene publicaciones? El botón solo aparece si no. */
    public function isEmpty(int $companyId): bool
    {
        return (new ListingModel())->where('company_id', $companyId)->countAllResults() === 0;
    }

    /**
     * Crea las publicaciones de ejemplo (y algún interesado entrante si hay otra
     * empresa en la plataforma con la que simular el cruce).
     *
     * @return array{listings:int,interests:int}
     */
    public function generate(int $companyId, int $userId): array
    {
        $listings   = new ListingModel();
        $categories = new CategoryModel();
        $market     = new MarketService();

        // Asegura que existan las categorías base antes de referenciarlas.
        $market->categories();
        $bySlug = array_column($categories->findAll(), 'id', 'slug');

        $made = 0;

        foreach (self::LISTINGS as [$type, $title, $summary, $catSlug, $tags, $city, $priceType, $amount, $status, $days, $views]) {
            $ok = $listings->insert([
                'company_id'     => $companyId,
                'user_id'        => $userId,
                'category_id'    => $bySlug[$catSlug] ?? null,
                'type'           => $type,
                'title'          => $title,
                'summary'        => $summary,
                'description'    => $summary . "\n\nPublicación de ejemplo generada para probar el catálogo. "
                    . 'Puedes editarla o eliminarla cuando quieras.',
                'price_type'     => $priceType,
                'price_amount'   => $amount,
                'price_currency' => 'COP',
                'city'           => $city,
                'status'         => $status,
                'valid_until'    => $days ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null,
                'views'          => $views,
            ]);

            if ($ok) {
                $made++;
                $market->syncTags((int) $listings->getInsertID(), $tags);
            }
        }

        return ['listings' => $made, 'interests' => $this->seedInterests($companyId)];
    }

    /**
     * Interesados de ejemplo en las publicaciones de la empresa. Requiere otra
     * empresa en la plataforma (el interés siempre viene de una empresa distinta);
     * si no la hay, no se crea ninguno.
     */
    private function seedInterests(int $companyId): int
    {
        $other = (new \App\Models\CompanyModel())
            ->where('id !=', $companyId)
            ->where('is_active', 1)
            ->first();

        if (!$other) {
            return 0;
        }

        $targets = (new ListingModel())
            ->where('company_id', $companyId)
            ->where('status', 'published')
            ->orderBy('id', 'asc')
            ->findAll(2);

        $interests = new InterestModel();
        $made      = 0;

        foreach ($targets as $i => $listing) {
            $ok = $interests->insert([
                'listing_id' => (int) $listing['id'],
                'company_id' => (int) $other['id'],
                'user_id'    => $other['owner_id'] ?? null,
                'message'    => self::INTERESTS[$i] ?? self::INTERESTS[0],
                'status'     => 'new',
            ]);
            if ($ok) {
                $made++;
            }
        }

        return $made;
    }
}
