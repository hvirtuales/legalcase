<?php

/**
 * Componente Mapas — puntos de interés sobre un plano.
 *
 * Para centros comerciales, ferias, campus o parques: se sube el plano de cada
 * **nivel** y se colocan **puntos** encima; al tocar uno se abre su ficha con
 * imagen, descripción, enlace y categoría. Cada categoría tiene su color, que
 * es el del pin.
 *
 * Las coordenadas van en **porcentaje del plano** (0–100), no en píxeles: el
 * mismo mapa se ve en un móvil, en un tótem y con cinco niveles de zoom, y el
 * día que se reemplace el plano por uno de más resolución los pines siguen en
 * su sitio.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'map',
    'name'          => 'Mapas',
    'name_key'      => 'Map.compName',
    'version'       => '1.0.0',
    'description'   => 'Mapas interactivos por niveles con puntos de interés, categorías de color y página pública con directorio.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-geo-alt',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_map_',
    'scope'         => 'company',
    'plan_flag'     => 'maps_enabled',
    'plan_limits'   => [
        ['column' => 'max_maps', 'label' => 'Mapas', 'label_key' => 'Map.limMaps', 'table' => 'cmp_map_maps'],
    ],
    'permissions'   => [
        'map.view'      => 'Ver los mapas y sus puntos',
        'map.create'    => 'Crear mapas, niveles y puntos',
        'map.edit'      => 'Editar mapas, niveles y puntos',
        'map.delete'    => 'Eliminar mapas, niveles y puntos',
        'map.configure' => 'Gestionar las categorías de puntos',
    ],
    'menus'         => [
        ['label' => 'Mapas', 'label_key' => 'Map.navMaps', 'icon' => 'icon-geo-alt', 'route' => 'mapas', 'permission' => 'map.view', 'order' => 95],
    ],
    'routes'        => ['mapas'],
    'settings'      => [],
];
