<?php

/**
 * Componente Directorio — vistas y mapa de las empresas de un GRUPO.
 *
 * Ámbito **tenant** (contrato de componentes de tenant): sus datos cuelgan del
 * grupo, el id sale siempre de `session('active_tenant_id')` —nunca del POST— y
 * sus rutas llevan el filtro `tenant`, que es lo que convierte «tiene el
 * permiso» en «tiene el permiso sobre ESTE grupo».
 *
 * **El componente ES las vistas (v3.0.0).** Las entradas escritas a mano
 * (`cmp_dir_entries`) se retiraron: los mismos datos ya estaban en las empresas
 * del grupo, sin teclear nada y sin la garantía de que un día dejaran de
 * coincidir con su ficha. Cada **vista** es una página pública con un
 * subconjunto de las empresas del grupo —por sector, que se mantiene sola, o a
 * mano—, con listado, mapa o las dos cosas.
 *
 * El cupo del plan sigue llamándose `max_entries` **a propósito**: renombrar la
 * columna obligaría a otra migración y rompería la simetría del desinstalado.
 * Lo que se ve en el formulario de planes es la etiqueta, no el nombre.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES_TENANT.md
 */

return [
    'code'          => 'dir',
    'name'          => 'Directorio',
    'name_key'      => 'Dir.compName',
    'version'       => '3.0.0',
    'description'   => 'Vistas públicas de las empresas del grupo, con listado, buscador y mapa por sector.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-signpost',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_dir_',

    // Ámbito GRUPO: las tablas llevan `tenant_id` y las rutas van con el filtro
    // `tenant` además de `auth` y `permission:`.
    'scope'         => 'tenant',

    // Bandera y cupo en `plans`, que añade la migración propia
    // 2026-08-05-110002_AddDirectoryLimitsToPlans (contrato §11).
    'plan_flag'     => 'directory_enabled',
    'plan_limits'   => [
        ['column' => 'max_entries', 'label' => 'Vistas del directorio', 'label_key' => 'Dir.limBoards', 'table' => 'cmp_dir_boards', 'company_column' => 'tenant_id'],
    ],
    'permissions'   => [
        'dir.view'   => 'Ver el directorio del grupo',
        'dir.create' => 'Agregar entradas al directorio',
        'dir.edit'   => 'Editar entradas del directorio',
        'dir.delete' => 'Eliminar entradas del directorio',
    ],
    'menus'         => [
        ['label' => 'Vistas y mapa', 'label_key' => 'Dir.navBoards', 'icon' => 'icon-signpost', 'route' => 'directorio', 'permission' => 'dir.view', 'order' => 120],
    ],
    'routes'        => ['directorio', 'directorio-publico'],
    'settings'      => [],
];
