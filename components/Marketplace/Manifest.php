<?php

/**
 * Componente Marketplace — vitrina de oferta y demanda entre empresas.
 *
 * Cada empresa publica lo que **ofrece** (productos, servicios) y lo que
 * **necesita** (necesidades, oportunidades de negocio). El catálogo es común a
 * toda la plataforma: ahí se cruzan oferta y demanda. Las publicaciones SÍ
 * pertenecen a una empresa (`company_id`), así que «Mis publicaciones» y la
 * bandeja de interesados quedan aisladas por la empresa activa; el catálogo, en
 * cambio, muestra lo publicado por todas.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'mkt',
    'name'          => 'Marketplace',
    'name_key'      => 'Mkt.compName',
    'version'       => '1.0.0',
    'description'   => 'Vitrina de oferta y demanda: productos, servicios, necesidades y oportunidades de negocio entre empresas.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-box-seam',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_mkt_',
    // Columna booleana de `plans` que habilita el componente; el cupo va en
    // `plans.max_listings` (0 = ilimitado).
    'plan_flag'     => 'marketplace_enabled',
    'plan_limits'   => [
        ['column' => 'max_listings', 'label' => 'Publicaciones', 'label_key' => 'Mkt.limListings', 'table' => 'cmp_mkt_listings'],
    ],
    'permissions'   => [
        'mkt.view'   => 'Ver el catálogo y las publicaciones de la empresa',
        'mkt.create' => 'Crear publicaciones y manifestar interés',
        'mkt.edit'   => 'Editar publicaciones y gestionar los interesados',
        'mkt.delete' => 'Eliminar publicaciones',
    ],
    'menus'         => [
        ['label' => 'Catálogo', 'label_key' => 'Mkt.navCatalog', 'icon' => 'icon-box-seam', 'route' => 'marketplace', 'permission' => 'mkt.view', 'order' => 90],
        ['label' => 'Mis publicaciones', 'label_key' => 'Mkt.navMine', 'icon' => 'icon-clipboard', 'route' => 'marketplace/mis-publicaciones', 'permission' => 'mkt.view', 'order' => 91],
        ['label' => 'Interesados', 'label_key' => 'Mkt.navInterests', 'icon' => 'icon-people', 'route' => 'marketplace/interesados', 'permission' => 'mkt.view', 'order' => 92],
    ],
    'routes'        => ['marketplace'],
    'settings'      => [],
];
