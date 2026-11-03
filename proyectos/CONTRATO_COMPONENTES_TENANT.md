# Contrato de Componentes de nivel **TENANT**

> **Qué es este documento.** El contrato que debe cumplir un componente cuyos datos pertenecen a un **grupo de
> empresas** (un centro comercial, una cámara de comercio) y no a una empresa concreta. Es un **anexo** del
> [`CONTRATO_COMPONENTES.md`](CONTRATO_COMPONENTES.md): **todo lo que dice el contrato base sigue vigente**
> —namespace propio, prefijo de tablas, portabilidad a 3 motores, i18n, permisos, formularios, seguridad— y aquí
> solo se describe **lo que cambia** por ser de nivel grupo.
>
> Igual que el base, su razón de ser es que puedas **soltar una carpeta en `components/`** y tener un módulo de
> nivel tenant funcionando **sin tocar `app/`**.

---

## 0. Cuándo tu componente es de tenant y cuándo no

Esta es la primera decisión y la más cara de cambiar después, porque determina la columna por la que aíslas y de
dónde sale tu `plan_flag`.

| Pregunta | Ámbito |
|---|---|
| ¿Un dato tuyo pertenece a **una** empresa, y otra empresa del mismo grupo no debería verlo? | **`company`** (el de siempre) |
| ¿Un dato tuyo pertenece al **grupo entero** y lo consulta o mantiene su administrador? | **`tenant`** |

Ejemplos de nivel tenant: **directorio de locales** del centro comercial, **cartelera** de la cámara,
**convocatorias** a sus afiliadas, **indicadores agregados** del grupo, **reglamento** común.

Ejemplos que **no** lo son: la contabilidad de un local, sus cotizaciones, sus PQRSF. Esos son de la empresa
aunque el centro comercial quiera verlos: para eso el administrador del grupo **entra a la empresa** desde el
Panel del Tenant, y el aislamiento por `active_company_id` sigue intacto.

> **No existe un tercer camino.** Un componente declara **un** ámbito. Si necesitas las dos cosas, son dos
> componentes (o uno de empresa y una pantalla agregada en el de tenant que lea **por servicio público**, nunca
> las tablas del otro).

---

## 1. Lo único que declara el Manifest para ser de tenant

```php
'scope' => 'tenant',   // OPCIONAL. Por defecto 'company'.
```

Nada más. Un Manifest sin `scope` es de empresa, que es por lo que los componentes de empresa no tuvieron que
cambiar ni una línea al aparecer este nivel.

Manifest de referencia (el del Directorio real, v3.0.0 — sus entradas escritas a mano se retiraron y el
componente son ahora las **vistas**, `cmp_dir_boards`):

```php
<?php
return [
    'code'          => 'dir',
    'name'          => 'Directorio',
    'name_key'      => 'Dir.compName',
    'version'       => '3.0.0',
    'description'   => 'Vistas públicas de las empresas del grupo, con listado, buscador y mapa por sector.',
    'scope'         => 'tenant',              // ← lo que lo hace de nivel grupo
    'table_prefix'  => 'cmp_dir_',
    'icon'          => 'icon-signpost',
    'permissions'   => [
        'dir.view'   => 'Ver el directorio del grupo',
        'dir.create' => 'Agregar entradas al directorio',
        'dir.edit'   => 'Editar entradas del directorio',
        'dir.delete' => 'Eliminar entradas del directorio',
    ],
    'menus'         => [
        ['label' => 'Vistas y mapa', 'label_key' => 'Dir.navBoards', 'icon' => 'icon-signpost',
         'route' => 'directorio', 'permission' => 'dir.view', 'order' => 120],
    ],
    'routes'        => ['directorio', 'directorio-publico'],
    'plan_flag'     => 'directory_enabled',   // columna de `plans` (en planes de scope 'tenant')
    'plan_limits'   => [
        // En ámbito tenant el núcleo ya cuenta por `tenant_id`; declararlo no hace daño y lo deja explícito.
        ['column' => 'max_entries', 'label' => 'Vistas del directorio', 'label_key' => 'Dir.limBoards',
         'table' => 'cmp_dir_boards', 'company_column' => 'tenant_id'],
    ],
];
```

> La columna del cupo sigue llamándose `max_entries` aunque ahora cuente vistas: renombrarla obligaría a otra
> migración y rompería la simetría de `--drop-data`. Lo que ve el administrador es la etiqueta.

---

## 2. Contexto: los dos ámbitos son **excluyentes**

La plataforma tiene tres contextos y solo uno está activo a la vez:

| Contexto | Sesión | Qué menús se ven |
|---|---|---|
| **Global** | ninguna de las dos | componentes de **empresa** (como siempre) |
| **Empresa** | `active_company_id` | componentes de **empresa** |
| **Grupo** | `active_tenant_id` | componentes de **tenant** |

Fijar uno **limpia el otro** (`CompanySwitcherController`). `ComponentManager::currentScope()` devuelve
`'tenant'` si hay `active_tenant_id` y `'company'` en el resto de casos, y `menuGroups()` y `planStatus()`
filtran por ahí. Consecuencia práctica: **tu menú aparece solo dentro del grupo**, y el de un componente de
empresa desaparece mientras se trabaja en el grupo. Es lo que se quiere: son dos modos de trabajo distintos.

---

## 3. Rutas: usa el filtro **`tenant`** (OBLIGATORIO)

El núcleo trae `App\Filters\TenantFilter`, con alias **`tenant`**. Comprueba tres cosas antes de dejar entrar:
que haya contexto de grupo, que el usuario **administre ese grupo de verdad** (no basta con que el id esté en
sesión) y que la **suscripción del grupo esté vigente**.

```php
<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('directorio', [
    'namespace' => 'Components\Directorio\Controllers',
    'filter'    => ['auth', 'tenant'],          // ← sesión + contexto de grupo comprobado
], static function ($routes) {
    $routes->get('/',                'Boards::index',     ['filter' => 'permission:dir.view']);
    $routes->get('nueva',            'Boards::create',    ['filter' => 'permission:dir.create']);
    $routes->post('nueva',           'Boards::create',    ['filter' => 'permission:dir.create']);
    $routes->get('editar/(:num)',    'Boards::edit/$1',   ['filter' => 'permission:dir.edit']);
    $routes->post('editar/(:num)',   'Boards::edit/$1',   ['filter' => 'permission:dir.edit']);
    $routes->post('eliminar/(:num)', 'Boards::delete/$1', ['filter' => 'permission:dir.delete']);
});

// Público (sin auth ni contexto): el slug identifica al grupo.
$routes->group('directorio-publico', ['namespace' => 'Components\Directorio\Controllers'], static function ($routes) {
    // Assets del componente (Leaflet auto-hospedado), servidos por su propio controlador con lista blanca
    // (contrato base §7). Van ANTES del comodín, o «recurso» se tomaría por el slug de un grupo.
    $routes->get('recurso/(:segment)', 'Asset::show/$1');
    $routes->get('(:segment)', 'PublicDirectory::show/$1');
    $routes->get('(:segment)/(:segment)', 'PublicDirectory::board/$1/$2');
});
```

Sigue valiendo todo lo del contrato base §4: prefijo propio, borrados por POST con CSRF, y **ninguna ruta
interna sin `permission:`**.

---

## 4. Aislamiento: `tenant_id`, y de dónde sale (LO MÁS IMPORTANTE)

**Tus tablas llevan `tenant_id`, no `company_id`**, con FK a `companies(id)` (un tenant es una fila de
`companies` con `type = 'tenant'`).

```php
$this->forge->addField([
    'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
    'tenant_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
    // …lo tuyo…
]);
$this->forge->addKey('id', true);
$this->forge->addKey('tenant_id');
$this->forge->addForeignKey('tenant_id', 'companies', 'id', '', 'CASCADE');
```

**El `tenant_id` sale SIEMPRE de la sesión, nunca del POST ni de la URL:**

```php
$tenantId = (int) session('active_tenant_id');   // lo garantiza el filtro `tenant`
```

Y filtra **todas** tus consultas por él, incluidas las de detalle:

```php
// mal: un id ajeno en la URL enseña la entrada de otro grupo
$entry = $this->model->find($id);

// bien
$entry = $this->model->where('tenant_id', $tenantId)->find($id);
```

> **El permiso no sustituye la comprobación de pertenencia** (contrato base §9). Tener `dir.edit` no autoriza a
> editar la entrada de otro grupo. Prueba explícitamente el caso «cambio el id en la URL/POST».

### 4.1 Las empresas del grupo: pídeselas a `TenantGuard`

Casi todo componente de tenant necesita listar las empresas del grupo. **No consultes `companies` por tu
cuenta**: usa el guard del núcleo, que es la única fuente de verdad del alcance y ya respeta el soft-delete.

```php
use App\Libraries\TenantGuard;

$guard      = new TenantGuard();
$companyIds = $guard->childIdsOfTenants([$tenantId]);   // ids de las empresas del grupo
$tenants    = $guard->tenantsOf($userId);               // grupos que administra el usuario
$left       = $guard->companiesLeft($tenantId);         // cupo de empresas (null = ilimitado)
```

Si guardas una FK a una empresa concreta (`company_id` además de `tenant_id`, como haría un directorio),
**valida que esa empresa sea del grupo** antes de guardar:

```php
if (!in_array($companyId, $guard->childIdsOfTenants([$tenantId]), true)) {
    return redirect()->back()->with('error', lang('Dir.errNotInGroup'));
}
```

---

## 5. Planes: tus banderas van en los planes de **ámbito tenant**

Los planes tienen ámbito (`plans.scope`): los de `tenant` son lo que contrata el grupo, los de `company` lo que
contrata cada empresa. **El formulario de planes muestra los recuadros del ámbito elegido**, así que tus
columnas solo aparecen —y solo se guardan— en los planes de tenant.

Lo que tienes que entregar (mismo espíritu que el §11 del contrato base, con dos diferencias):

1. **La migración es tuya** y va en tu carpeta. Añade solo columnas propias, comprueba `tableExists('plans')` y
   `fieldExists()` antes de cada `addColumn`, y el `down()` las quita:

   ```php
   private const COLUMNS = [
       'directory_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
       'max_entries'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0], // 0 = sin límite
   ];

   public function up()
   {
       if (!$this->db->tableExists('plans')) { return; }
       foreach (self::COLUMNS as $name => $def) {
           if ($this->db->fieldExists($name, 'plans')) { continue; }
           $this->forge->addColumn('plans', [$name => $def]);
           $this->db->resetDataCache(); // fieldExists() cachea las columnas de ANTES del ALTER
       }
   }

   public function down()
   {
       if (!$this->db->tableExists('plans')) { return; }
       foreach (array_keys(self::COLUMNS) as $name) {
           if ($this->db->fieldExists($name, 'plans')) {
               $this->forge->dropColumn('plans', $name);
               $this->db->resetDataCache();
           }
       }
   }
   ```

2. **`plan_flag`** en el Manifest → el núcleo oculta tu menú a los grupos cuyo plan lo tenga en `0`.

3. **`plan_limits` cuenta por `tenant_id`.** En ámbito tenant la columna por defecto ya es `tenant_id`; solo
   declara `company_column` si por algún motivo tu tabla la llama de otro modo.

4. **Un `PlanGuard` propio** que lea el plan **del grupo** y haga valer servicio y cupo **en el servicio**, no
   solo ocultando botones:

   ```php
   namespace Components\Directorio\Libraries;

   class PlanGuard
   {
       private function plan(int $tenantId): ?array
       {
           // El plan efectivo lo resuelve el núcleo (un tenant es una fila de companies).
           // Un núcleo con tenants siempre tiene effectivePlan(), así que aquí no hace falta la caída del base §11.1.
           return (new \App\Models\CompanyModel())->effectivePlan($tenantId) ?: null;
       }

       public function serviceEnabled(int $tenantId): bool
       {
           $plan = $this->plan($tenantId);
           // Sin plan, o con una columna que esta instalación no tiene → permitido (base §11.1).
           if (!$plan || !array_key_exists('directory_enabled', $plan)) { return true; }
           return (int) $plan['directory_enabled'] === 1;
       }

       public function boardsLeft(int $tenantId): ?int   // null = ilimitado
       {
           $plan = $this->plan($tenantId);
           if (!$plan || !array_key_exists('max_entries', $plan)) { return null; }
           $max = (int) $plan['max_entries'];
           if ($max <= 0) { return null; }
           $used = (new \Components\Directorio\Models\BoardModel())->where('tenant_id', $tenantId)->countAllResults();
           return max(0, $max - $used);
       }
   }
   ```

**Lo que NO tienes que hacer** (y que versiones antiguas del contrato base exigían): **no toques
`app/Views/plans/_form.php`, ni `PlanModel::$allowedFields`, ni `PlanController::collect()`.** El formulario de
planes genera un recuadro por componente a partir del Manifest, y el controlador guarda exactamente las columnas
que ese Manifest declara. Para los cupos que **no** se cuentan por grupo (y que por eso no están en
`plan_limits`), declara `plan_fields`, que es solo para el formulario:

```php
'plan_fields' => [
    ['column' => 'max_boards', 'label' => 'Carteleras', 'label_key' => 'Xyz.limBoards', 'hint' => '0 = sin límite'],
],
```

> Si declaras `plan_fields`, **sustituye** a `plan_limits` en el formulario y en lo que se guarda: repite ahí
> también las columnas de `plan_limits` o dejarán de ser editables (base §11.1).

Sin plan asignado al grupo → **permitido e ilimitado**, igual que en el resto de la plataforma.

---

## 6. Permisos: quién los tiene de verdad

Declara los tuyos como siempre (`<code>.<accion>`, verbos en inglés). El gestor los siembra al instalar y los
concede a **Super Admin** y **Admin**.

Eso basta, y conviene entender por qué: el administrador de un grupo **recibe el rol `Admin`** cuando el Super
Admin lo asigna al tenant, así que hereda tus permisos. Lo que le acota a **su** grupo no es el rol —los roles
de esta plataforma son globales y no miran contexto— sino el **vínculo** (`company_users.is_admin` sobre la
fila-tenant), que es justo lo que comprueba el filtro `tenant`.

> Por eso **el filtro `tenant` no es opcional**: es lo único que convierte «tiene el permiso» en «tiene el
> permiso **sobre este grupo**».

---

## 7. Vistas

- **Internas:** sistema de diseño del núcleo (Tailwind, `partials/datatable`, `partials/delete_button`, iconos
  `icon-*`, helper `media_url()`), igual que en el contrato base §7.
- **Públicas:** **autosuficientes** (base §7.3): layout propio con la hoja de estilos en línea, SVG en línea,
  tipografía del sistema, cero CDN. Un directorio público o una cartelera son exactamente el caso: se comparten
  con terceros y uno querría poder llevarse la carpeta a otra plataforma.
- **Marca:** la del **grupo** (`companies.brand_color`, `logo`, `banner` de la fila-tenant), no la de ninguna de
  sus empresas. El hex se valida y se emite **crudo**: `esc($hex,'css')` produce `\23 …` y `esc($hex,'attr')`
  `&#x23;…`, y ninguno es un color válido.
- **i18n obligatorio** (base §7.2): `Language/es` y `Language/en` con **las mismas claves**, cero texto visible
  incrustado.

---

### 7.1 Clasificar por **sector** y pintar mapas

La clasificación de las empresas (`companies.sector_id` → catálogo `sectors`) y su ubicación
(`companies.latitude`/`longitude`) son del **núcleo**. Un componente de grupo las **lee** —son
columnas de `companies`, y agrupar por sector es justo lo que hace útil un directorio— pero **no las
escribe** (§8): clasificar una empresa se hace desde su ficha, con `companies.edit`.

Si tu componente publica un mapa:

- **La biblioteca va auto-hospedada en tu carpeta**, no en un CDN (§7.3). El Directorio trae Leaflet
  1.9.4 en `assets/vendor/leaflet/` y lo sirve con un `Controllers/Asset` de **lista blanca fija**
  (nada de concatenar el nombre pedido a un directorio). Es lo mismo que hace el núcleo con Inter y
  con intl-tel-input, y lo que permite llevarse la carpeta a otra plataforma.
- **Los teselados son la excepción, y hay que declararla.** Un mapa del mundo real necesita imágenes
  del mundo real y eso no se empaqueta. Aíslalos en **una sola línea** para que se puedan apuntar a un
  servidor propio, y haz que la página **degrade sola**: el listado es HTML del servidor y tiene que
  seguir funcionando aunque no cargue ni un teselado.
- **No vuelques la empresa entera en el JSON del mapa.** El correo y el teléfono ya están en las
  fichas; repetirlos en un bloque de datos los deja a mano de cualquier raspador que ni siquiera
  tenga que leer el HTML. El Directorio manda solo nombre, sector, color, dirección y coordenadas.

---

## 8. Lo que NO puede hacer un componente de tenant

- **Leer las tablas de un componente de empresa** para «ver lo de sus locales». El aislamiento por
  `active_company_id` es lo que hace que los componentes de empresa sigan siendo correctos; si un
  componente de grupo se pone a leer `cmp_cont_vouchers` filtrando por las hijas, esa garantía se rompe y cada
  componente nuevo hereda el problema. Si de verdad hace falta un agregado, el componente **de empresa** expone
  un **servicio público** (`Config/Services.php`) que devuelve el dato ya agregado, y tú lo consumes.
- **Escribir en `companies`, `users`, `plans` o `tenant_plans`.** Colgar y descolgar empresas del grupo, la
  lista blanca de planes y el cupo son del núcleo. Relaciónate por FK.
- **Crear su propia noción de grupo.** El `tenant_id` es una fila de `companies`; no dupliques nombre, NIT,
  logo ni canales en una tabla tuya.
- **Anidar grupos.** La plataforma es de **un solo nivel** a propósito (`CompanyModel::canBeParent()`), y todas
  las consultas de alcance lo asumen.

---

## 9. Ciclo de vida

Idéntico al del contrato base §13: `spark components:install <Carpeta>` migra tu namespace, siembra permisos y
lo marca activo; `components:uninstall <Carpeta> [--drop-data] [--purge]` lo revierte. Tu ámbito no cambia nada
de esto — solo dónde aparece tu menú.

Al **desactivar** tu componente su namespace no se registra: tus rutas y menús desaparecen y los datos quedan
intactos.

---

## 10. Checklist de conformidad (además del checklist base)

- [ ] `'scope' => 'tenant'` en el `Manifest.php`.
- [ ] Todas tus tablas llevan **`tenant_id`** (FK a `companies(id)`, con índice), no `company_id`.
- [ ] El `tenant_id` sale de **`session('active_tenant_id')`**, nunca del POST ni de la URL.
- [ ] **Todas** las consultas —incluidas las de detalle y las de borrado— filtran por `tenant_id`. Probado
      cambiando el id en la URL y en el POST.
- [ ] Todas las rutas internas llevan **`['auth', 'tenant']`** y su `permission:`.
- [ ] Las empresas del grupo se piden a **`TenantGuard`**, y una FK a `companies` se valida contra
      `childIdsOfTenants()` antes de guardar.
- [ ] Si eres servicio de plan: migración propia idempotente con `down()`, `plan_flag` en el Manifest,
      `plan_limits`/`plan_fields`, y `PlanGuard` aplicado **en el servicio**. **Sin tocar `app/`.**
- [ ] No lees tablas de otros componentes; si necesitas un agregado, va por servicio público.
- [ ] Vistas internas con la UI del núcleo; vistas públicas **autosuficientes** y con la marca del **grupo**.
- [ ] i18n `es`/`en` con las mismas claves y ninguna clave usada sin definir.
- [ ] Portabilidad (base §8): sin `ENUM`, sin `JSON` nativo, sin SQL crudo; todo por Forge y Query Builder.

---

## 11. Resumen de las diferencias con el contrato base

| | Componente de empresa | Componente de tenant |
|---|---|---|
| Manifest | (nada que declarar) | **`'scope' => 'tenant'`** |
| Columna de aislamiento | `company_id` | **`tenant_id`** |
| De dónde sale el id | `session('active_company_id')` | **`session('active_tenant_id')`** |
| Filtro de rutas | `auth` + `permission:` | **`auth` + `tenant`** + `permission:` |
| Dónde aparece su menú | contexto Empresa y Global | **solo dentro del grupo** |
| Su `plan_flag` vive en | planes de `scope = 'company'` | planes de **`scope = 'tenant'`** |
| `plan_limits` cuenta por | `company_id` | **`tenant_id`** (por defecto) |
| Formulario de planes | generado del Manifest | generado del Manifest |
| Marca de las vistas públicas | la de la empresa | **la del grupo** |

> **Referencia viva:** `app/Libraries/TenantGuard.php` (alcance), `app/Filters/TenantFilter.php` (contexto) y
> `app/Libraries/ComponentManager.php` (`scopeOf()`, `currentScope()`, `planFormGroups()`) son el código que
> hace valer todo lo de arriba. El diseño y por qué de cada decisión está en
> [`PLAN_NIVEL_TENANT.md`](PLAN_NIVEL_TENANT.md).
