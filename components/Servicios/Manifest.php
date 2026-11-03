<?php

/**
 * Componente Servicios — gestión comercial: maestros (clientes, productos,
 * vendedores), cotizador con cálculo de totales, estados y PDF.
 *
 * Porta la app autónoma de C:\xampp\htdocs\cotizar (IndexedDB + TCPDF) a la
 * plataforma: los datos pasan a la BD, aislados por empresa activa, con RBAC y
 * plan. Los datos de la empresa emisora (razón social, NIT, logo, color de marca)
 * NO se recrean: salen de `companies`.
 *
 * Se llamó «Cotizaciones» hasta que se renombró a «Servicios» (y las rutas
 * pasaron de `/cotizaciones` a `/servicios`). El `code` sigue siendo `quo`
 * **a propósito**: de él cuelgan el prefijo de tablas `cmp_quo_` y los permisos
 * `quo.*`, que ya están en la BD; renombrarlo obligaría a migrar datos sin
 * ganar nada.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'quo',
    'name'          => 'Servicios',
    'name_key'      => 'Quo.compName',
    'version'       => '1.0.0',
    'description'   => 'Gestión comercial: clientes, productos, vendedores, cotizador con totales y PDF.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-receipt',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_quo_',
    'plan_flag'     => 'quotes_enabled',
    'plan_limits'   => [
        ['column' => 'max_quotes', 'label' => 'Cotizaciones', 'label_key' => 'Quo.limQuotes', 'table' => 'cmp_quo_quotes'],
    ],
    'permissions'   => [
        'quo.view'      => 'Ver cotizaciones y maestros comerciales',
        'quo.create'    => 'Crear cotizaciones, clientes, productos y vendedores',
        'quo.edit'      => 'Editar cotizaciones y cambiar su estado',
        'quo.delete'    => 'Eliminar cotizaciones y registros de los maestros',
        'quo.configure' => 'Configurar el documento (tipo, prefijo, numeración, términos y carta)',
    ],
    'menus'         => [
        ['label' => 'Cotizaciones', 'label_key' => 'Quo.navQuotes', 'icon' => 'icon-receipt', 'route' => 'servicios/cotizaciones', 'permission' => 'quo.view', 'order' => 100],
        ['label' => 'Clientes', 'label_key' => 'Quo.navClients', 'icon' => 'icon-people', 'route' => 'servicios/clientes', 'permission' => 'quo.view', 'order' => 102],
        ['label' => 'Productos', 'label_key' => 'Quo.navProducts', 'icon' => 'icon-box-seam', 'route' => 'servicios/productos', 'permission' => 'quo.view', 'order' => 103],
        ['label' => 'Catálogo Digital', 'label_key' => 'Quo.navCatalog', 'icon' => 'icon-book', 'route' => 'servicios/catalogo-digital', 'permission' => 'quo.view', 'order' => 104],
        ['label' => 'Vendedores', 'label_key' => 'Quo.navSellers', 'icon' => 'icon-person-badge', 'route' => 'servicios/vendedores', 'permission' => 'quo.view', 'order' => 106],
        ['label' => 'Configuración', 'label_key' => 'Quo.navSettings', 'icon' => 'icon-gear', 'route' => 'servicios/configuracion', 'permission' => 'quo.configure', 'order' => 107],
    ],
    'routes'        => ['servicios'],
    'settings'      => [],
];
