<?php

/**
 * Componente Copropiedad — el registro censal de una propiedad horizontal.
 *
 * Responde a la pregunta que ningún otro módulo responde: **quién vive dónde, con
 * qué título, desde cuándo y con qué**. Todo lo demás de la administración (cartera,
 * PQRSF, reservas) cuelga de esa respuesta, así que este componente es el maestro y
 * no debe depender de ninguno de ellos.
 *
 * Once cosas que registra, y dónde vive cada una:
 *   1. Torres, bloques, etapas ............ `cmp_cop_blocks`
 *   2. Casas, apartamentos, locales,
 *      parqueaderos y depósitos ........... `cmp_cop_units` (un solo tipo de fila: son todos INMUEBLES)
 *   3. Propietarios, residentes,
 *      arrendatarios y ocupantes .......... `cmp_cop_people` + el `role` de la relación
 *   4. Relación persona ↔ inmueble ........ `cmp_cop_unit_people`
 *   5. Historial de propietarios
 *      y residentes ....................... **la misma tabla**: cerrar una relación le pone
 *                                           `end_date` y `is_current = 0`, nunca la borra
 *   6. Grupos familiares .................. `cmp_cop_families`
 *   7. Mascotas ........................... `cmp_cop_pets`
 *   8. Vehículos .......................... `cmp_cop_vehicles`
 *   9. Contactos de emergencia ............ `cmp_cop_contacts`
 *  10. Estado de ocupación ................ `cmp_cop_units.occupancy`, que el servicio
 *                                           recalcula solo desde las relaciones vigentes
 *  11. Autorizaciones especiales .......... `cmp_cop_authorizations`
 *  (+) Documentos del inmueble ........... `cmp_cop_documents`, polimórfica y privada
 *
 * **Tres decisiones que explican el esquema:**
 *
 * a) **Un parqueadero es un inmueble, no un campo del apartamento.** En propiedad
 *    horizontal tiene su propia matrícula y su propio coeficiente, y puede venderse
 *    aparte. Por eso vive en `cmp_cop_units` como los demás y se adscribe al
 *    apartamento con `parent_unit_id`. Un campo `parqueaderos INT` habría hecho
 *    imposible decir *cuál*.
 *
 * b) **La relación ES el historial.** No hay tabla de histórico: cerrar una relación
 *    la deja en su sitio con fecha de fin. Preguntar «quién vive hoy» es
 *    `is_current = 1`; preguntar «quién vivía en 2019» es un rango de fechas. Con
 *    una tabla aparte, las dos respuestas podrían discrepar. Es la misma decisión que
 *    tomó Networking («la solicitud ES la conexión»).
 *
 * c) **El estado de ocupación se DERIVA, salvo cuando no puede.** `RegistryService`
 *    lo recalcula tras cada cambio de relación (hay arrendatario → arrendado; vive el
 *    propietario → propietario; nadie → desocupado). Pero «en venta» y «en remodelación»
 *    no se deducen de ninguna relación: son una decisión humana, así que si el
 *    administrador los fija a mano, el recálculo **no** los pisa.
 *
 * Multi-empresa: **cada copropiedad es una empresa** de la plataforma. Todas las tablas
 * llevan `company_id` y se filtran por `session('active_company_id')`.
 *
 * Portabilidad (contrato §15): no usa nada que el núcleo no tuviera ya en su versión
 * más vieja — `storeImage()` con tres argumentos, `ImageOptimizer` propio, y el plan
 * se resuelve con `effectivePlan()` **si existe** (llegó con los tenants) o leyendo
 * `companies.plan_id` si no. Comprobable con `spark copropiedad:contrato`.
 *
 * Ver contrato: proyectos/CONTRATO_COMPONENTES.md
 */

return [
    'code'          => 'cop',
    'name'          => 'Copropiedad',
    'name_key'      => 'Cop.compName',
    'version'       => '1.0.0',
    'description'   => 'Registro de la copropiedad: torres y bloques, inmuebles (incluidos parqueaderos y depósitos), propietarios, residentes, arrendatarios y ocupantes con su historial, grupos familiares, mascotas, vehículos, contactos de emergencia, autorizaciones especiales y documentos.',
    'author'        => 'Núcleo',
    'icon'          => 'icon-buildings',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_cop_',

    // Ámbito EMPRESA (el implícito): una copropiedad es una empresa. Se declara
    // explícito para que se lea sin abrir el código — y porque es lo que hace que
    // el componente funcione igual en una instalación con tenants y en una sin
    // ellos (contrato §15.3).
    'scope'         => 'company',

    // Bandera y cupo en `plans`, que añade la migración propia
    // 2026-08-09-000011_AddCopropiedadLimitsToPlans (contrato §11).
    'plan_flag'     => 'copropiedad_enabled',
    'plan_limits'   => [
        ['column' => 'max_units', 'label' => 'Inmuebles', 'label_key' => 'Cop.limUnits', 'table' => 'cmp_cop_units'],
    ],

    'permissions'   => [
        'cop.view'      => 'Ver el registro de la copropiedad',
        'cop.create'    => 'Registrar inmuebles, personas y sus datos asociados',
        'cop.edit'      => 'Editar el registro y abrir o cerrar relaciones',
        'cop.delete'    => 'Eliminar registros y documentos',
        'cop.configure' => 'Gestionar torres, bloques y la estructura del conjunto',
    ],

    // Los iconos son los que EXISTEN en el `public/css/icons.css` del núcleo. El
    // juego no trae uno de coche ni de mascota, y un `icon-` inexistente no da
    // error: simplemente no pinta nada, así que el hueco no se relacionaría con el
    // nombre. Antes que dejar dos menús mudos, se usan los más cercanos.
    // «Torres y bloques» no está en el menú a propósito: se entra desde el listado
    // de Inmuebles, que es donde se echa de menos (mismo criterio que las
    // Categorías de Servicios).
    'menus'         => [
        ['label' => 'Panel',          'label_key' => 'Cop.navPanel',    'icon' => 'icon-speedometer2',  'route' => 'copropiedad',                 'permission' => 'cop.view', 'order' => 10],
        ['label' => 'Inmuebles',      'label_key' => 'Cop.navUnits',    'icon' => 'icon-buildings',     'route' => 'copropiedad/inmuebles',       'permission' => 'cop.view', 'order' => 20],
        ['label' => 'Personas',       'label_key' => 'Cop.navPeople',   'icon' => 'icon-people',        'route' => 'copropiedad/personas',        'permission' => 'cop.view', 'order' => 30],
        ['label' => 'Vehículos',      'label_key' => 'Cop.navVehicles', 'icon' => 'icon-key',           'route' => 'copropiedad/vehiculos',       'permission' => 'cop.view', 'order' => 40],
        ['label' => 'Mascotas',       'label_key' => 'Cop.navPets',     'icon' => 'icon-star',          'route' => 'copropiedad/mascotas',        'permission' => 'cop.view', 'order' => 50],
        ['label' => 'Autorizaciones', 'label_key' => 'Cop.navAuths',    'icon' => 'icon-shield-check',  'route' => 'copropiedad/autorizaciones',  'permission' => 'cop.view', 'order' => 60],
    ],

    'routes'        => ['copropiedad'],
    'settings'      => [],
];
